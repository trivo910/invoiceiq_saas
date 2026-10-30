<?php

namespace App\Http\Controllers;

use App\Exports\ImportTemplateDefinition;
use App\Exports\ImportTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\{Response, StreamedResponse};

class ImportTemplateController extends Controller
{
    public function excel(string $entity): Response
    {
        ImportTemplateDefinition::for($entity);
        return Excel::download(new ImportTemplateExport($entity), $entity.'-import-template.xlsx');
    }

    public function csv(string $entity): StreamedResponse
    {
        $definition = ImportTemplateDefinition::for($entity);
        $filename = $entity.'-import-template.csv';

        return response()->streamDownload(function () use ($definition): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_column($definition['fields'], 0));
            foreach ($definition['examples'] as $example) fputcsv($output, $example);
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
