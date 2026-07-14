<?php

namespace App\Services\Accounting;

use App\Models\AccountingIntegration;
use App\Services\Accounting\Contracts\AccountingExportProvider;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class PeachtreeDesktopExportProvider implements AccountingExportProvider
{
    public function provider(): string
    {
        return AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP;
    }

    public function write(Collection $journalEntries, array $accountMappings, string $outputPath): int
    {
        $writer = new Writer;
        $writer->openToFile($outputPath);
        $writer->getCurrentSheet()->setName('General Journal');
        $writer->addRow(Row::fromValues([
            'Date', 'Account ID', 'Reference', 'Trans Description', 'Debit Amt', 'Credit Amt',
        ]));
        $dateStyle = (new Style)->setFormat('m/d/yyyy');

        $rowCount = 0;

        foreach ($journalEntries as $journalEntry) {
            foreach ($journalEntry->journalItems as $item) {
                $writer->addRow(Row::fromValuesWithStyles([
                    $journalEntry->date->startOfDay(),
                    $accountMappings[$item->account_id],
                    'JV',
                    filled($journalEntry->narration) ? $journalEntry->narration : $item->account->name,
                    (float) $item->debit ?: null,
                    (float) $item->credit ?: null,
                ], columnStyles: [0 => $dateStyle]));
                $rowCount++;
            }
        }

        $writer->close();

        return $rowCount;
    }
}
