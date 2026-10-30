<?php

namespace App\Http\Controllers;

use App\Exports\ImportTemplateDefinition;
use App\Models\{ImportBatch, ImportRow};
use App\Services\Import\{ImportFileException, ImportFileReader, ImportPreviewService, ImportProcessingException, ImportProcessingService};
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Storage};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Illuminate\View\View;

class ImportController extends Controller
{
    private const MIME_TYPES = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/octet-stream'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
    ];

    public function upload(Request $request, string $entity, ImportFileReader $reader, ImportPreviewService $preview): RedirectResponse
    {
        $definition = ImportTemplateDefinition::for($entity);
        $request->validate(['file' => ['required', 'file']]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) return back()->withErrors(['file' => 'This file type is not supported. Upload an .xlsx, .xls, or .csv file.']);
        if (! $file->isValid()) return back()->withErrors(['file' => 'The file did not finish uploading. Choose it again and retry.']);
        if ($file->getSize() > ((int) config('imports.max_file_kb', 5120) * 1024)) return back()->withErrors(['file' => 'This file is larger than 5 MB. Split it into smaller files and try again.']);
        if (! in_array(strtolower((string) $file->getMimeType()), self::MIME_TYPES[$extension], true)) {
            return back()->withErrors(['file' => 'The file contents do not match its file type. Save a fresh Excel or CSV file and try again.']);
        }

        $tenantId = app(TenantContext::class)->id();
        $relativePath = 'tenants/'.$tenantId.'/imports/'.Str::uuid().'.'.$extension;
        $storedPath = $file->storeAs('tenants/'.$tenantId.'/imports', basename($relativePath), 'tenant_private');
        if (! $storedPath) return back()->withErrors(['file' => 'We could not save this file securely. Please try again.']);

        $batch = null;
        try {
            $absolutePath = Storage::disk('tenant_private')->path($storedPath);
            $parsed = $reader->read($absolutePath, $extension);
            $batch = ImportBatch::create([
                'user_id' => $request->user()->id,
                'entity' => $entity,
                'original_filename' => Str::limit(basename($file->getClientOriginalName()), 190, ''),
                'stored_path' => $storedPath,
                'file_hash' => hash_file('sha256', $absolutePath),
                'status' => 'uploaded',
                'total_rows' => count($parsed['rows']),
                'headers' => $parsed['headers'],
            ]);
            $auto = $preview->autoMap($entity, $parsed['headers']);
            $batch->forceFill(['header_map' => $auto['mapping']])->save();
            if ($preview->mappingNeeded($entity, $parsed['headers'], $auto['mapping'], $auto['unused'])) {
                return redirect()->route($entity.'.import.preview', $batch);
            }
            $preview->validateAndSave($batch, $auto['mapping']);
            return redirect()->route($entity.'.import.preview', $batch)->with('success', 'Your file is checked. Review the results below. No records have been added yet.');
        } catch (ImportFileException $exception) {
            if ($batch) $batch->delete();
            Storage::disk('tenant_private')->delete($storedPath);
            return back()->withErrors(['file' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::warning('Import preview could not be prepared.', ['entity' => $definition['entity'], 'exception' => $exception::class]);
            if ($batch) $batch->delete();
            Storage::disk('tenant_private')->delete($storedPath);
            return back()->withErrors(['file' => 'We could not check this file. Download a fresh template and try again.']);
        }
    }

    public function preview(string $batch, string $entity, ImportPreviewService $preview): View
    {
        ImportTemplateDefinition::for($entity);
        $batch = ImportBatch::where('entity', $entity)->findOrFail((int) $batch);
        $expected = $preview->headers($entity);
        $auto = $preview->autoMap($entity, $batch->headers ?? []);
        $mappingNeeded = $batch->status === 'uploaded';
        $tab = request()->query('tab', 'all');
        abort_unless(in_array($tab, ['all', 'ready', 'warnings', 'errors', 'imported', 'skipped'], true), 404);
        $rows = ImportRow::where('import_batch_id', $batch->id)
            ->when($tab !== 'all', fn ($query) => $query->where('status', rtrim($tab, 's')))
            ->orderBy('row_number')->paginate((int) config('imports.preview_page_size', 50))->withQueryString();

        $resultCounts = $batch->status === 'completed' ? [
            'imported' => ImportRow::where('import_batch_id', $batch->id)->where('status', 'imported')->count(),
            'skipped' => ImportRow::where('import_batch_id', $batch->id)->whereIn('status', ['skipped', 'error'])->count(),
        ] : null;

        return view('imports.preview', compact('batch', 'entity', 'expected', 'auto', 'mappingNeeded', 'rows', 'tab', 'resultCounts'));
    }

    public function map(Request $request, string $batch, string $entity, ImportPreviewService $preview): RedirectResponse
    {
        ImportTemplateDefinition::for($entity);
        $batch = ImportBatch::where('entity', $entity)->findOrFail((int) $batch);
        abort_unless($batch->status === 'uploaded', 409);
        $request->validate(['mapping' => ['required', 'array']]);
        $mapping = $request->input('mapping', []);
        try {
            $preview->validateAndSave($batch, $mapping);
            return redirect()->route($entity.'.import.preview', $batch)->with('success', 'Your file is checked. Review the results below. No records have been added yet.');
        } catch (ImportFileException $exception) {
            return back()->withErrors(['mapping' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::warning('Import header mapping could not be applied.', ['entity' => $entity, 'exception' => $exception::class]);
            return back()->withErrors(['mapping' => 'We could not apply those headings. Refresh and try the mapping again.']);
        }
    }

    public function process(Request $request, string $batch, string $entity, ImportProcessingService $processor): RedirectResponse
    {
        ImportTemplateDefinition::for($entity);
        $request->validate(['mode' => ['required', 'in:import_valid,stop_on_issues']]);
        try {
            $result = $processor->process((int) $batch, $entity, (string) $request->input('mode'));
            return redirect()->route($entity.'.import.preview', $batch)->with('success',
                'Import complete: '.$result['imported'].' added, '.$result['skipped'].' skipped.');
        } catch (ImportProcessingException $exception) {
            if ($exception->status === 403) abort(403, $exception->getMessage());
            return back()->withErrors(['process' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::warning('Import transaction failed; no partial records were kept.', ['entity' => $entity, 'exception' => $exception::class]);
            return back()->withErrors(['process' => 'The import could not be completed. No partial records were kept. Check for a record that may have changed since preview, then try again.']);
        }
    }
}
