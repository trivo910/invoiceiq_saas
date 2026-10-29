<?php
namespace App\Jobs;

use App\Models\AccountExport;
use App\Services\{TenantLogoStorage, TenantWork, WorkspaceExport};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class BuildAccountExport implements ShouldQueue
{
    use Queueable;
    public int $tries = 2;
    public int $timeout = 60;
    public function __construct(public int $tenantId, public int $exportId) {}

    public function handle(WorkspaceExport $writer): void
    {
        app(TenantWork::class)->run($this->tenantId, function ($tenant) use ($writer) {
            DB::transaction(function () use ($tenant, $writer) {
                $export = AccountExport::whereKey($this->exportId)->lockForUpdate()->firstOrFail();
                if ($export->status === 'ready') return;
                if (! $tenant->users()->whereKey($export->requested_by)->wherePivot('role', 'owner')->exists()) {
                    $export->update(['status' => 'failed']); return;
                }
                $disk = Storage::disk('tenant_private');
                $directory = "tenants/{$tenant->id}/exports";
                $disk->makeDirectory($directory);
                $relative = $directory.'/'.Str::uuid().'.zip';
                $zip = new \ZipArchive();
                if ($zip->open($disk->path($relative), \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Cannot create export.');
                try {
                    foreach (WorkspaceExport::RESOURCES as $resource) {
                        $csv = fopen('php://temp/maxmemory:2097152', 'w+');
                        try {
                            $writer->writeCsv($resource, $csv); rewind($csv);
                            $zip->addFromString($resource.'.csv', stream_get_contents($csv));
                        } finally { fclose($csv); }
                    }
                    $zip->addFromString('workspace.json', json_encode($writer->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $logo = app(TenantLogoStorage::class)->contents(\App\Models\Setting::get('company_logo'), $tenant->id);
                    if ($logo !== null) $zip->addFromString('branding/logo.'.pathinfo(\App\Models\Setting::get('company_logo'), PATHINFO_EXTENSION), $logo);
                    $zip->close();
                    $export->update(['status' => 'ready', 'path' => $relative, 'expires_at' => now()->addDay()]);
                } catch (\Throwable $e) {
                    $zip->close(); $disk->delete($relative);
                    throw new \RuntimeException('Workspace export generation failed.');
                }
            });
        });
    }

    public function failed(?\Throwable $error): void
    {
        try {
            app(TenantWork::class)->run($this->tenantId, function () {
                $export = AccountExport::find($this->exportId);
                if ($export && $export->status !== 'ready') $export->update(['status' => 'failed']);
            });
            return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            // Suspended or soft-deleted workspaces cannot establish normal tenant context.
        }
        DB::table('account_exports')->where('tenant_id', $this->tenantId)->where('id', $this->exportId)
            ->where('status', '!=', 'ready')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
