<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportTemplateDataSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    public function __construct(private array $definition) {}

    public function title(): string { return 'Data'; }

    public function array(): array
    {
        $headers = array_column($this->definition['fields'], 0);
        return array_merge([$headers], $this->definition['examples']);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->definition['fields']));
        return [
            '1:1' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '263548']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->definition['fields'] as $index => $field) {
            $widths[Coordinate::stringFromColumnIndex($index + 1)] = in_array($field[0], ['Notes', 'Description', 'Billing Address', 'Shipping Address'], true) ? 30 : 21;
        }
        return $widths;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->definition['fields']));
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastColumn}3");
            $sheet->getRowDimension(1)->setRowHeight(38);
            $sheet->getStyle('A2:'.$lastColumn.'3')->applyFromArray([
                'font' => ['color' => ['rgb' => '687386'], 'italic' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F3F5']],
            ]);
            foreach ([2, 3] as $row) {
                $sheet->getComment("A{$row}")->getText()->createTextRun('SAMPLE ROW: This is example data. Delete this light-grey row before uploading.');
            }

            $validations = $this->definition['entity'] === 'customers'
                ? ['Billing State' => 'StateNames', 'Billing State Code' => 'StateCodes', 'Shipping State' => 'StateNames', 'Shipping State Code' => 'StateCodes']
                : ['Type *' => 'ItemTypes', 'GST Rate (%) *' => 'GstRates', 'Unit *' => 'UnitNames'];

            foreach ($validations as $header => $rangeName) {
                $column = array_search($header, array_column($this->definition['fields'], 0), true);
                if ($column === false) continue;
                $column = Coordinate::stringFromColumnIndex($column + 1);
                for ($row = 2; $row <= 5001; $row++) {
                    $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setFormula1('='.$rangeName);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(false);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Choose a listed value');
                    $validation->setError('Choose a value from the dropdown list.');
                    $validation->setShowInputMessage(true);
                    $validation->setPromptTitle('Choose a value');
                    $validation->setPrompt('Select a value from the dropdown.');
                }
            }
        }];
    }
}
