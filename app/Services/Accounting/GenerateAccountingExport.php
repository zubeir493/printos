<?php

namespace App\Services\Accounting;

use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use App\Models\JournalEntry;
use App\Models\User;
use App\Notifications\AccountingExportFailed;
use App\Services\Accounting\Contracts\AccountingExportProvider;
use App\UserRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateAccountingExport
{
    public function __construct(private PeachtreeDesktopExportProvider $peachtreeDesktop) {}

    public function handle(
        AccountingIntegration $integration,
        CarbonInterface $cutoff,
        ?User $generatedBy = null,
        ?AccountingExport $retry = null,
    ): ?AccountingExport {
        return Cache::lock("accounting-export:{$integration->id}", 300)->block(
            1,
            fn (): ?AccountingExport => $this->generate($integration, $cutoff, $generatedBy, $retry),
        );
    }

    private function generate(
        AccountingIntegration $integration,
        CarbonInterface $cutoff,
        ?User $generatedBy,
        ?AccountingExport $retry,
    ): ?AccountingExport {
        $journalEntries = $this->eligibleJournalEntries($integration, $cutoff);

        if ($journalEntries->isEmpty()) {
            return null;
        }

        $export = $retry ?? new AccountingExport;
        $export->fill([
            'accounting_integration_id' => $integration->id,
            'generated_by' => $generatedBy?->id,
            'cutoff_at' => $cutoff,
            'status' => AccountingExport::STATUS_PROCESSING,
            'file_path' => null,
            'file_name' => null,
            'journal_count' => $journalEntries->count(),
            'row_count' => 0,
            'total_debit' => $journalEntries->sum(fn (JournalEntry $entry): float => (float) $entry->total_debit),
            'total_credit' => $journalEntries->sum(fn (JournalEntry $entry): float => (float) $entry->total_credit),
            'checksum' => null,
            'error_message' => null,
            'generated_at' => null,
        ])->save();

        $temporaryPath = tempnam(sys_get_temp_dir(), 'peachtree-');
        $filePath = null;

        try {
            if ($temporaryPath === false) {
                throw new RuntimeException('A temporary workbook could not be created.');
            }

            $mappings = $this->validatedMappings($integration, $journalEntries);
            $fileName = 'peachtree-general-journal-'.$cutoff->format('Y-m-d-His').'.xlsx';
            $filePath = "accounting-exports/{$integration->provider}/{$fileName}";
            $rowCount = $this->providerFor($integration)->write($journalEntries, $mappings, $temporaryPath);
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated workbook could not be read.');
            }

            Storage::disk($this->disk())->put($filePath, $contents, ['visibility' => 'private']);

            DB::transaction(function () use ($export, $integration, $journalEntries, $fileName, $filePath, $rowCount, $contents): void {
                $export->update([
                    'status' => AccountingExport::STATUS_COMPLETED,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'row_count' => $rowCount,
                    'checksum' => hash('sha256', $contents),
                    'generated_at' => now(),
                ]);

                $export->journalEntries()->attach(
                    $journalEntries->mapWithKeys(fn (JournalEntry $entry): array => [
                        $entry->id => ['accounting_integration_id' => $integration->id],
                    ])->all(),
                );
            });

            return $export->fresh(['integration', 'journalEntries']);
        } catch (Throwable $exception) {
            if (filled($filePath) && Storage::disk($this->disk())->exists($filePath)) {
                Storage::disk($this->disk())->delete($filePath);
            }

            $export->update([
                'status' => AccountingExport::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);
            $this->notifyFailure($export);

            return $export->fresh();
        } finally {
            if (is_string($temporaryPath) && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @return Collection<int, JournalEntry> */
    private function eligibleJournalEntries(AccountingIntegration $integration, CarbonInterface $cutoff): Collection
    {
        return JournalEntry::query()
            ->where('status', 'posted')
            ->whereNotNull('posted_at')
            ->where('posted_at', '<=', $cutoff)
            ->whereDoesntHave('accountingExports', fn ($query) => $query
                ->where('accounting_export_journal_entry.accounting_integration_id', $integration->id))
            ->with(['journalItems.account'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, JournalEntry>  $journalEntries
     * @return array<int, string>
     */
    private function validatedMappings(AccountingIntegration $integration, Collection $journalEntries): array
    {
        foreach ($journalEntries as $entry) {
            $itemsDebit = round((float) $entry->journalItems->sum('debit'), 2);
            $itemsCredit = round((float) $entry->journalItems->sum('credit'), 2);

            if ($entry->journalItems->isEmpty() || $itemsDebit !== $itemsCredit || round((float) $entry->total_debit, 2) !== $itemsDebit || round((float) $entry->total_credit, 2) !== $itemsCredit) {
                throw new RuntimeException("Journal entry {$entry->reference} is not balanced.");
            }
        }

        $accounts = $journalEntries->flatMap->journalItems
            ->pluck('account')
            ->unique('id')
            ->values();
        $accountIds = $accounts->pluck('id');
        $mappings = $accounts
            ->mapWithKeys(fn ($account): array => [$account->id => $account->code])
            ->all();

        foreach ($integration->mappings()->whereIn('account_id', $accountIds)->get() as $mapping) {
            $mappings[$mapping->account_id] = $mapping->external_account_id;
        }

        $missingNames = $journalEntries->flatMap->journalItems
            ->filter(fn ($item): bool => blank($mappings[$item->account_id] ?? null))
            ->pluck('account.name')
            ->unique()
            ->sort()
            ->values();

        if ($missingNames->isNotEmpty()) {
            throw new RuntimeException('Missing Peachtree account codes or mappings: '.$missingNames->join(', ').'.');
        }

        return $mappings;
    }

    private function providerFor(AccountingIntegration $integration): AccountingExportProvider
    {
        return match ($integration->provider) {
            AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP => $this->peachtreeDesktop,
            default => throw new RuntimeException("Unsupported accounting provider: {$integration->provider}."),
        };
    }

    private function notifyFailure(AccountingExport $export): void
    {
        $recipients = User::query()->whereIn('role', [UserRole::Admin->value, UserRole::Finance->value])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AccountingExportFailed($export));
        }
    }

    private function disk(): string
    {
        return config('filesystems.private_disk', 'local');
    }
}
