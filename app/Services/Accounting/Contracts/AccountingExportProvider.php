<?php

namespace App\Services\Accounting\Contracts;

use App\Models\JournalEntry;
use Illuminate\Support\Collection;

interface AccountingExportProvider
{
    public function provider(): string;

    /**
     * @param  Collection<int, JournalEntry>  $journalEntries
     * @param  array<int, string>  $accountMappings
     */
    public function write(Collection $journalEntries, array $accountMappings, string $outputPath): int;
}
