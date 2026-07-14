<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\UserRole;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class GenerateMonthlyPayrollDrafts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:generate-monthly-drafts {--month= : Payroll month start date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and calculate the monthly payroll draft.';

    /**
     * Execute the console command.
     */
    public function handle(PrepareMonthlyPayrollRun $preparer): int
    {
        $month = $this->option('month')
            ? CarbonImmutable::parse($this->option('month'))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $payrollRun = $preparer->createDraftForMonth($month);
        $recipients = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Finance->value])
            ->get();

        Notification::make()
            ->title('Payroll draft is ready')
            ->body($payrollRun->name.' has been generated and calculated.')
            ->success()
            ->sendToDatabase($recipients, isEventDispatched: true);

        $this->info("Payroll draft ready: {$payrollRun->name}");

        return self::SUCCESS;
    }
}
