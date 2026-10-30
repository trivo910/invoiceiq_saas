<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportTemplateInstructionsSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private array $definition) {}

    public function title(): string { return 'Instructions'; }

    public function array(): array
    {
        $rows = [
            ['Important: delete the two light-grey sample rows from the Data sheet before using your own information.'],
            ['This template contains only fields supported by the current InvoiceIQ data model.'],
            ['Column', 'Required?', 'What this means', 'Format or example', 'Common mistake to avoid'],
        ];
        foreach ($this->definition['fields'] as $field) {
            $rows[] = [$field[0], $field[1] ? 'Required' : 'Optional', $field[2], $field[3], $field[4]];
        }
        foreach ($this->definition['notes'] as $note) $rows[] = [$note];
        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A4');
        $sheet->getStyle('A3:E3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '263548']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle('A1:E2')->getFont()->setBold(true);
        $sheet->getStyle('A1:E'.($sheet->getHighestRow()))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 14, 'C' => 42, 'D' => 30, 'E' => 42];
    }
}
