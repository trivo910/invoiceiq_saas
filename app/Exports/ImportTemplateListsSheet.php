<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportTemplateListsSheet implements FromArray, WithTitle, WithStyles
{
    public function title(): string { return 'Lists'; }

    public function array(): array
    {
        $lists = ImportTemplateDefinition::lists();
        $rows = [['State', 'State Code', 'Unit', 'GST Rate (%)', 'Item Type']];
        $count = max(count($lists['states']), count($lists['units']), count($lists['gst_rates']), count($lists['item_types']));
        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                $lists['states'][$index][0] ?? null,
                $lists['states'][$index][1] ?? null,
                $lists['units'][$index] ?? null,
                $lists['gst_rates'][$index] ?? null,
                $lists['item_types'][$index] ?? null,
            ];
        }
        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return ['1:1' => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '263548']],
        ]];
    }
}
