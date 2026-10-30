<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportFileReader
{
    public function read(string $path, string $extension): array
    {
        try {
            $reader = $extension === 'csv' ? IOFactory::createReader('Csv') : IOFactory::createReaderForFile($path);
            if (method_exists($reader, 'setInputEncoding')) $reader->setInputEncoding('UTF-8');
            if (method_exists($reader, 'setDelimiter')) $reader->setDelimiter(',');
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (\Throwable) {
            throw new ImportFileException('We could not open this file. Save it again as an Excel workbook or CSV, then try once more.');
        }

        try {
            if ($extension !== 'csv') {
                $sheet = $book->getSheetByName('Data');
                if (! $sheet) throw new ImportFileException('This workbook needs a sheet named “Data”. Download a fresh template and copy your records into that sheet.');
            } else {
                $sheet = $book->getSheet(0);
            }

            if ($sheet->getMergeCells() !== []) throw new ImportFileException('Merged cells are not supported. Unmerge cells on the Data sheet and try again.');
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                if ($sheet->getCell($coordinate)->isFormula()) {
                    throw new ImportFileException('Formulas are not supported. Replace formula cells with their displayed values and upload again.');
                }
            }

            $grid = $sheet->toArray(null, true, true, false);
            if ($grid === []) throw new ImportFileException('This file is empty. Add a header row and at least one record.');
            $headers = array_map(fn ($value) => $this->cellText($value), array_values($grid[0] ?? []));
            while ($headers !== [] && end($headers) === '') array_pop($headers);
            if ($headers === [] || count(array_filter($headers, fn ($value) => $value !== '')) === 0) {
                throw new ImportFileException('We could not find column headings in the first row. Use the template and keep its header row at the top.');
            }

            $rows = [];
            foreach (array_slice($grid, 1) as $offset => $row) {
                $row = array_slice(array_values($row), 0, count($headers));
                $row = array_pad($row, count($headers), null);
                $cells = array_map(fn ($value) => $this->cellValue($value), $row);
                if (count(array_filter($cells, fn ($value) => $value !== null && $value !== '')) === 0) continue;
                $rows[] = ['row_number' => $offset + 2, 'cells' => $cells];
            }
            if ($rows === []) throw new ImportFileException('This file has headings but no records. Add at least one customer or item below the headings.');
            if (count($rows) > (int) config('imports.max_rows', 5000)) {
                throw new ImportFileException('This file has more than '.number_format((int) config('imports.max_rows', 5000)).' records. Split it into smaller files and try again.');
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function cellText(mixed $value): string
    {
        if ($value === null) return '';
        return trim(ltrim((string) $value, "\xEF\xBB\xBF"));
    }

    private function cellValue(mixed $value): mixed
    {
        if ($value === null) return null;
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1.0e15) return number_format($value, 0, '.', '');
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return trim(ltrim((string) $value, "\xEF\xBB\xBF"));
    }
}
