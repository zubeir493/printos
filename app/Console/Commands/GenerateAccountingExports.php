<?php

namespace App\Console\Commands;

use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use App\Services\Accounting\GenerateAccountingExport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateAccountingExports extends Command
{
    protected $signature = 'accounting:generate-exports {--integration= : Generate for one integration ID}';

    protected $description = 'Generate due accounting integration exports';

    public function handle(GenerateAccountingExport $generator): int
    {
        $integrations = AccountingIntegration::query()
            ->where('enabled', true)
            ->whereIn('provider', AccountingIntegration::exportableProviders())
            ->when($this->option('integration'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($integrations as $integration) {
            $cutoff = now($integration->timezone);

            if (! $this->option('integration') && ! $this->isDue($integration, $cutoff)) {
                continue;
            }

            $export = $generator->handle($integration, $cutoff);

            if ($export?->status === AccountingExport::STATUS_FAILED) {
                $this->error("{$integration->name}: {$export->error_message}");

                continue;
            }

            $this->info($export
                ? "{$integration->name}: generated {$export->file_name}."
                : "{$integration->name}: no unexported journals.");
        }

        return self::SUCCESS;
    }

    private function isDue(AccountingIntegration $integration, Carbon $now): bool
    {
        $cutoff = Carbon::parse($integration->daily_cutoff, $integration->timezone);

        return $now->format('H:i') === $cutoff->format('H:i');
    }
}
