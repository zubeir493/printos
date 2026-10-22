<?php

namespace App\Console\Commands;

use App\Models\EmployeeLoan;
use App\Models\PayrollRun;
use Illuminate\Console\Command;

class AuditHrRecords extends Command
{
    protected $signature = 'hr:audit-records';

    protected $description = 'Read-only reconciliation of legacy HR and payroll workflow records';

    public function handle(): int
    {
        $unverifiedLoans = EmployeeLoan::query()->whereIn('status', ['active', 'partially_paid'])->where('legacy_disbursement_confirmed', false)->whereNull('disbursement_payment_id')->count();
        $this->line('Loans requiring historical funding confirmation: '.$unverifiedLoans);
        $missingSnapshots = 0;
        $overlaps = 0;
        PayrollRun::query()->whereIn('status', ['approved', 'paid'])->with('employees')->chunkById(100, function ($runs) use (&$missingSnapshots, &$overlaps): void {
            foreach ($runs as $run) {
                $missingSnapshots += $run->employees->filter(fn ($row): bool => ! isset($row->calculation_snapshot['beneficiary']))->count();
                $overlaps += PayrollRun::query()->where('id', '>', $run->id)->whereIn('status', ['approved', 'paid'])
                    ->whereDate('period_start', '<=', $run->period_end)->whereDate('period_end', '>=', $run->period_start)
                    ->whereHas('employees', fn ($query) => $query->whereIn('employee_id', $run->employees->pluck('employee_id')))->count();
            }
        });
        $this->line('Posted payroll rows without beneficiary snapshots: '.$missingSnapshots);
        $this->line('Overlapping posted payroll run pairs: '.$overlaps);
        $this->line('No records were changed.');

        return self::SUCCESS;
    }
}
