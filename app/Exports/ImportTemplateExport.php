<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeWriting;
use PhpOffice\PhpSpreadsheet\NamedRange;

class ImportTemplateExport implements WithMultipleSheets, WithEvents
{
    public function __construct(private string $entity)
    {
        $this->definition = ImportTemplateDefinition::for($entity);
    }

    private array $definition;

    public function sheets(): array
    {
        return [
            new ImportTemplateDataSheet($this->definition),
            new ImportTemplateInstructionsSheet($this->definition),
            new ImportTemplateListsSheet(),
        ];
    }

    public function registerEvents(): array
    {
        return [BeforeWriting::class => function (BeforeWriting $event): void {
            $spreadsheet = $event->getWriter()->getDelegate();
            $listsSheet = $spreadsheet->getSheetByName('Lists');
            $lists = ImportTemplateDefinition::lists();
            $spreadsheet->addNamedRange(new NamedRange('StateNames', $listsSheet, '$A$2:$A$'.(count($lists['states']) + 1)));
            $spreadsheet->addNamedRange(new NamedRange('StateCodes', $listsSheet, '$B$2:$B$'.(count($lists['states']) + 1)));
            $spreadsheet->addNamedRange(new NamedRange('UnitNames', $listsSheet, '$C$2:$C$'.(count($lists['units']) + 1)));
            $spreadsheet->addNamedRange(new NamedRange('GstRates', $listsSheet, '$D$2:$D$'.(count($lists['gst_rates']) + 1)));
            $spreadsheet->addNamedRange(new NamedRange('ItemTypes', $listsSheet, '$E$2:$E$'.(count($lists['item_types']) + 1)));
        }];
    }
}
