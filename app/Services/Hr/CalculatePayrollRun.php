<?php

namespace App\Services\Hr;

use App\Models\AttendanceDailySummary;
use App\Models\AttendancePeriodSummary;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\EmployeeLoanInstallment;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CalculatePayrollRun
{
    public function __construct(
        private CalculatePayrollTax $taxCalculator,
        private RebuildAttendanceDailySummaries $dailySummaryBuilder,
        private GeneratePayrollOvertimeEntries $overtimeEntries,
    ) {}

    /**
     * @param  array<int>|null  $employeeIds
     */
    public function handle(PayrollRun $payrollRun, ?array $employeeIds = null): PayrollRun
    {
        if ($payrollRun->status !== 'draft') {
            throw new RuntimeException('Only draft payroll runs can be recalculated.');
        }

        return DB::transaction(function () use ($payrollRun, $employeeIds): PayrollRun {
            $periodStart = CarbonImmutable::parse($payrollRun->getRawOriginal('period_start'))->toDateString();
            $periodEnd = CarbonImmutable::parse($payrollRun->getRawOriginal('period_end'))->toDateString();
            $employees = $this->employees($periodStart, $periodEnd, $employeeIds);

            if ($employees->isEmpty()) {
                $payrollRun->employees()->delete();

                return $payrollRun->refresh();
            }

            $this->rebuildSegmentSummaries($employees, $periodStart, $periodEnd);

            $payrollRun->employees()->delete();

            foreach ($employees as $employee) {
                $this->calculateEmployee($payrollRun, $employee);
            }

            return $payrollRun->refresh();
        });
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function rebuildSegmentSummaries(Collection $employees, string $periodStart, string $periodEnd): void
    {
        $employeeIds = $employees->pluck('id')->all();
        $employeesWithSegments = AttendanceSegment::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $periodStart)
            ->whereDate('date', '<=', $periodEnd)
            ->distinct()
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($employeesWithSegments === []) {
            return;
        }

        $this->dailySummaryBuilder->forPeriod($employeesWithSegments, $periodStart, $periodEnd);
    }

    /**
     * @param  array<int>|null  $employeeIds
     * @return Collection<int, Employee>
     */
    private function employees(string $periodStart, string $periodEnd, ?array $employeeIds): Collection
    {
        return Employee::query()
            ->whereDate('hire_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart): void {
                $query
                    ->whereRaw('LOWER(status) = ?', ['active'])
                    ->orWhereDate('termination_date', '>=', $periodStart);
            })
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('id', $employeeIds))
            ->get();
    }

    private function calculateEmployee(PayrollRun $payrollRun, Employee $employee): PayrollRunEmployee
    {
        $periodStart = CarbonImmutable::parse($payrollRun->getRawOriginal('period_start'))->toDateString();
        $periodEnd = CarbonImmutable::parse($payrollRun->getRawOriginal('period_end'))->toDateString();
        $fullBasicSalary = $this->salaryForPeriod($employee, $periodEnd);
        $employment = $this->employmentWindow($employee, $periodStart, $periodEnd);
        $dailySummaries = AttendanceDailySummary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $periodStart)
            ->whereDate('date', '<=', $periodEnd)
            ->get();
        $periodSummaries = AttendancePeriodSummary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->get();
        $attendance = $dailySummaries->isNotEmpty()
            ? $this->attendanceFromDailySummaries($dailySummaries, $periodStart, $periodEnd, $employment['employed_days'])
            : $this->attendanceFromPeriodSummaries($periodSummaries, $periodStart, $periodEnd);
        $leave = $this->approvedLeaveMinutes($employee, $periodStart, $periodEnd);
        $paidLeaveDays = round($leave['paid_minutes'] / 480, 2);
        $paidDays = $attendance['has_attendance_data']
            ? min($attendance['base_days'], round($attendance['attended_days'] + $paidLeaveDays, 2))
            : $paidLeaveDays;
        $payRatio = $attendance['base_days'] > 0 ? min(1, round($paidDays / $attendance['base_days'], 6)) : 0.0;
        $basicSalary = round($fullBasicSalary * $payRatio, 2);
        $absenceMinutes = (int) round($attendance['absent_days'] * 480);
        $deductibleAbsentMinutes = $dailySummaries->isNotEmpty()
            ? $this->deductibleAbsentMinutesFromDailySummaries($dailySummaries, $leave['by_date'])
            : max(max(0, $absenceMinutes - $leave['paid_minutes']), $leave['unpaid_minutes']);
        $penaltyMinutes = $attendance['late_minutes'] + $deductibleAbsentMinutes;
        $penaltyHours = round($penaltyMinutes / 60, 2);
        $payPerHour = $this->regularHourlyRate($fullBasicSalary, $attendance['base_days']);
        $penaltyAmount = round($this->penaltyHourlyRate($fullBasicSalary) * $penaltyHours, 2);
        $this->overtimeEntries->syncForEmployee($payrollRun, $employee, $dailySummaries, $periodSummaries, $fullBasicSalary, $attendance['base_days']);
        $approvedOvertime = $this->overtimeEntries->approvedTotals($payrollRun, $employee);
        $overtimeHours = $approvedOvertime['hours'];
        $overtimeAmount = $approvedOvertime['amount'];
        $bonus = 0.0;
        $transportAllowance = round((float) ($employee->transport_allowance ?? 0) * $payRatio, 2);
        $settings = Setting::getSettings();
        $employeePensionRate = (float) $settings->employee_pension_rate;
        $employerPensionRate = (float) $settings->employer_pension_rate;
        $employerPensionContribution = $employee->pension_enabled ? round($basicSalary * ($employerPensionRate / 100), 2) : 0.0;
        $grossEarning = round($basicSalary + $bonus + $transportAllowance + $employerPensionContribution + $overtimeAmount, 2);
        $taxableAmount = max(0, round($grossEarning - $employerPensionContribution - $penaltyAmount, 2));
        $tax = $this->taxCalculator->handle($taxableAmount, $periodEnd);
        $pensionContribution = $employee->pension_enabled
            ? round($basicSalary * (($employeePensionRate + $employerPensionRate) / 100), 2)
            : 0.0;
        $loanInstallments = $this->loanInstallments($employee, $periodStart, $periodEnd);
        $loan = round($loanInstallments->sum(fn ($installment): float => max(0.0, (float) $installment->amount - (float) $installment->paid_amount)), 2);
        $workersUnion = $settings->workers_union_enabled
            ? round($basicSalary * ((float) $settings->workers_union_rate / 100), 2)
            : 0.0;
        $totalDeduction = round($tax['amount'] + $penaltyAmount + $pensionContribution + $loan + $workersUnion, 2);
        $netPay = max(0, round($grossEarning - $totalDeduction, 2));

        $payrollEmployee = $payrollRun->employees()->create([
            'employee_id' => $employee->id,
            'basic_salary' => $basicSalary,
            'time_on_duty' => $attendance['time_on_duty'],
            'pay_per_hour' => round($payPerHour, 4),
            'bonus' => $bonus,
            'transport_allowance' => $transportAllowance,
            'employer_pension_contribution' => $employerPensionContribution,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtimeAmount,
            'gross_earning' => $grossEarning,
            'taxable_amount' => $taxableAmount,
            'income_tax' => $tax['amount'],
            'penalty_hours' => $penaltyHours,
            'penalty_amount' => $penaltyAmount,
            'pension_contribution' => $pensionContribution,
            'loan' => $loan,
            'workers_union' => $workersUnion,
            'total_deduction' => $totalDeduction,
            'net_pay' => $netPay,
            'calculation_snapshot' => [
                'daily_summary_ids' => $dailySummaries->pluck('id')->all(),
                'period_summary_ids' => $periodSummaries->pluck('id')->all(),
                'loan_installment_ids' => $loanInstallments->pluck('id')->all(),
                'loan_ids' => $loanInstallments->pluck('employee_loan_id')->unique()->values()->all(),
                'full_basic_salary' => $fullBasicSalary,
                'employment_period_start' => $employment['start'],
                'employment_period_end' => $employment['end'],
                'employment_business_days' => $employment['employed_days'],
                'period_business_days' => $employment['period_days'],
                'employment_ratio' => $employment['ratio'],
                'base_days' => $attendance['base_days'],
                'work_days' => $attendance['work_days'],
                'attended_days' => $attendance['attended_days'],
                'actual_days' => $attendance['actual_days'],
                'has_attendance_data' => $attendance['has_attendance_data'],
                'paid_days' => $paidDays,
                'pay_ratio' => $payRatio,
                'time_on_duty' => $attendance['time_on_duty'],
                'absent_days' => $attendance['absent_days'],
                'paid_leave_minutes' => $leave['paid_minutes'],
                'unpaid_leave_minutes' => $leave['unpaid_minutes'],
                'deductible_absent_minutes' => $deductibleAbsentMinutes,
                'penalty_minutes' => $penaltyMinutes,
                'penalty_hourly_rate' => $this->penaltyHourlyRate($fullBasicSalary),
                'penalty_day_divisor' => 30,
                'late_minutes' => $attendance['late_minutes'],
                'normal_overtime_minutes' => $attendance['normal_overtime_minutes'],
                'night_overtime_minutes' => $attendance['night_overtime_minutes'],
                'holiday_overtime_minutes' => (int) round(($attendance['holiday_days'] + $attendance['dayoff_days']) * 480),
                'holiday_days' => $attendance['holiday_days'],
                'dayoff_days' => $attendance['dayoff_days'],
                'approved_overtime_entry_ids' => $approvedOvertime['ids'],
                'pension_enabled' => (bool) $employee->pension_enabled,
                'employee_pension_rate' => $employeePensionRate,
                'employer_pension_rate' => $employerPensionRate,
                'union_rate' => (float) $settings->workers_union_rate,
                'union_enabled' => (bool) $settings->workers_union_enabled,
                'tax' => $tax,
            ],
        ]);

        $this->overtimeEntries->linkEntriesToPayrollRow($payrollEmployee);
        $this->createLineItems($payrollEmployee);

        return $payrollEmployee;
    }

    private function salaryForPeriod(Employee $employee, string $periodEnd): float
    {
        $history = $employee->salaryHistories()
            ->whereDate('effective_date', '<=', $periodEnd)
            ->latest('effective_date')
            ->first();

        return (float) ($history?->basic_salary ?? $employee->basic_salary);
    }

    /**
     * @return array{start: string, end: string, employed_days: int, period_days: int, ratio: float}
     */
    private function employmentWindow(Employee $employee, string $periodStart, string $periodEnd): array
    {
        $start = CarbonImmutable::parse($employee->hire_date)->greaterThan(CarbonImmutable::parse($periodStart))
            ? CarbonImmutable::parse($employee->hire_date)->toDateString()
            : $periodStart;
        $end = $employee->termination_date && CarbonImmutable::parse($employee->termination_date)->lessThan(CarbonImmutable::parse($periodEnd))
            ? CarbonImmutable::parse($employee->termination_date)->toDateString()
            : $periodEnd;
        $periodDays = $this->businessDays($periodStart, $periodEnd);
        $employedDays = CarbonImmutable::parse($start)->lte(CarbonImmutable::parse($end))
            ? $this->businessDays($start, $end)
            : 0;

        return [
            'start' => $start,
            'end' => $end,
            'employed_days' => $employedDays,
            'period_days' => $periodDays,
            'ratio' => round($employedDays / max(1, $periodDays), 6),
        ];
    }

    private function businessDays(string $start, string $end): int
    {
        $date = CarbonImmutable::parse($start);
        $last = CarbonImmutable::parse($end);
        $days = 0;

        while ($date->lte($last)) {
            if (! $date->isSunday()) {
                $days++;
            }

            $date = $date->addDay();
        }

        return max(1, $days);
    }

    private function regularHourlyRate(float $fullBasicSalary, float $baseDays): float
    {
        return $fullBasicSalary / max(1, $baseDays) / 8;
    }

    private function penaltyHourlyRate(float $fullBasicSalary): float
    {
        return $fullBasicSalary / 30 / 8;
    }

    /**
     * @param  Collection<int, AttendanceDailySummary>  $summaries
     * @return array{base_days: float, work_days: float, attended_days: float, actual_days: float, has_attendance_data: bool, time_on_duty: float, absent_days: float, late_minutes: int, normal_overtime_minutes: int, night_overtime_minutes: int, holiday_days: float, dayoff_days: float}
     */
    private function attendanceFromDailySummaries(Collection $summaries, string $periodStart, string $periodEnd, int $employedDays): array
    {
        $workDays = max(1.0, round((float) $summaries->sum('expected_minutes') / 480, 2) ?: $this->businessDays($periodStart, $periodEnd));
        $baseDays = max($workDays, $employedDays, 1.0);
        $timeOnDuty = round((float) $summaries->sum('worked_minutes') / 60, 2);
        $attendedDays = (float) $summaries
            ->filter(fn (AttendanceDailySummary $summary): bool => (int) $summary->worked_minutes > 0)
            ->count();

        return [
            'base_days' => $baseDays,
            'work_days' => $workDays,
            'attended_days' => $attendedDays,
            'actual_days' => round($timeOnDuty / 8, 2),
            'has_attendance_data' => true,
            'time_on_duty' => $timeOnDuty,
            'absent_days' => round((float) $summaries->sum('absence_minutes') / 480, 2),
            'late_minutes' => (int) $summaries->sum('late_minutes'),
            'normal_overtime_minutes' => (int) $summaries->sum('overtime_minutes'),
            'night_overtime_minutes' => 0,
            'holiday_days' => round((float) $summaries->where('status', 'holiday')->sum('holiday_minutes') / 480, 2),
            'dayoff_days' => round((float) $summaries->where('status', 'dayoff')->sum('holiday_minutes') / 480, 2),
        ];
    }

    /**
     * @param  Collection<int, AttendancePeriodSummary>  $summaries
     * @return array{base_days: float, work_days: float, attended_days: float, actual_days: float, has_attendance_data: bool, time_on_duty: float, absent_days: float, late_minutes: int, normal_overtime_minutes: int, night_overtime_minutes: int, holiday_days: float, dayoff_days: float}
     */
    private function attendanceFromPeriodSummaries(Collection $summaries, string $periodStart, string $periodEnd): array
    {
        $workDays = max(1.0, (float) $summaries->max('work_days') ?: $this->businessDays($periodStart, $periodEnd));
        $hasAttendanceData = $summaries->isNotEmpty();
        $attendedDays = (float) $summaries->sum('actual_days');

        return [
            'base_days' => $workDays,
            'work_days' => $workDays,
            'attended_days' => $attendedDays,
            'actual_days' => $attendedDays,
            'has_attendance_data' => $hasAttendanceData,
            'time_on_duty' => round((float) $summaries->sum('work_time_hours'), 2),
            'absent_days' => $hasAttendanceData ? (float) $summaries->sum('absent_days') : 0.0,
            'late_minutes' => (int) $summaries->sum('late_minutes') + (int) $summaries->sum('early_minutes'),
            'normal_overtime_minutes' => (int) $summaries->sum('overtime_minutes'),
            'night_overtime_minutes' => 0,
            'holiday_days' => (float) $summaries->sum('holiday_days'),
            'dayoff_days' => (float) $summaries->sum('dayoff_days'),
        ];
    }

    /**
     * @return Collection<int, EmployeeLoanInstallment>
     */
    private function loanInstallments(Employee $employee, string $periodStart, string $periodEnd): Collection
    {
        return EmployeeLoanInstallment::query()
            ->whereHas('loan', fn ($query) => $query
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['active', 'partially_paid']))
            ->dueForPeriod($periodStart, $periodEnd)
            ->get();
    }

    /**
     * @return array{paid_minutes: int, unpaid_minutes: int, by_date: array<string, array{paid_minutes: int, unpaid_minutes: int}>}
     */
    private function approvedLeaveMinutes(Employee $employee, string $periodStart, string $periodEnd): array
    {
        $paidMinutes = 0;
        $unpaidMinutes = 0;
        $byDate = [];

        $employee->leaveRequests()
            ->with('leaveType')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->get()
            ->each(function ($leaveRequest) use (&$paidMinutes, &$unpaidMinutes, &$byDate, $periodStart, $periodEnd): void {
                $start = $leaveRequest->start_date->greaterThan(CarbonImmutable::parse($periodStart))
                    ? CarbonImmutable::parse($leaveRequest->start_date)->toDateString()
                    : $periodStart;
                $end = $leaveRequest->end_date->lessThan(CarbonImmutable::parse($periodEnd))
                    ? CarbonImmutable::parse($leaveRequest->end_date)->toDateString()
                    : $periodEnd;
                $days = max(1, CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1);
                $minutesPerDay = $leaveRequest->minutes
                    ? (int) round((int) $leaveRequest->minutes / $days)
                    : 480;
                $date = CarbonImmutable::parse($start);
                $last = CarbonImmutable::parse($end);

                while ($date->lte($last)) {
                    $key = $date->toDateString();
                    $byDate[$key] ??= ['paid_minutes' => 0, 'unpaid_minutes' => 0];

                    if ($leaveRequest->leaveType->is_paid) {
                        $paidMinutes += $minutesPerDay;
                        $byDate[$key]['paid_minutes'] += $minutesPerDay;
                    } else {
                        $unpaidMinutes += $minutesPerDay;
                        $byDate[$key]['unpaid_minutes'] += $minutesPerDay;
                    }

                    $date = $date->addDay();
                }
            });

        return [
            'paid_minutes' => $paidMinutes,
            'unpaid_minutes' => $unpaidMinutes,
            'by_date' => $byDate,
        ];
    }

    /**
     * @param  Collection<int, AttendanceDailySummary>  $summaries
     * @param  array<string, array{paid_minutes: int, unpaid_minutes: int}>  $leaveByDate
     */
    private function deductibleAbsentMinutesFromDailySummaries(Collection $summaries, array $leaveByDate): int
    {
        return (int) $summaries->sum(function (AttendanceDailySummary $summary) use ($leaveByDate): int {
            $leave = $leaveByDate[$summary->date->toDateString()] ?? ['paid_minutes' => 0, 'unpaid_minutes' => 0];

            return max(
                max(0, (int) $summary->absence_minutes - $leave['paid_minutes']),
                $leave['unpaid_minutes'],
            );
        });
    }

    private function createLineItems(PayrollRunEmployee $employee): void
    {
        foreach ([
            ['earning', 'basic_salary', 'Basic salary', $employee->basic_salary],
            ['earning', 'bonus', 'Bonus', $employee->bonus],
            ['earning', 'transport_allowance', 'Transport allowance', $employee->transport_allowance],
            ['earning', 'employer_pension', 'Employer pension contribution', $employee->employer_pension_contribution],
            ['earning', 'overtime_amount', 'Overtime', $employee->overtime_amount],
            ['deduction', 'income_tax', 'Income tax', $employee->income_tax],
            ['deduction', 'penalty', 'Penalty', $employee->penalty_amount],
            ['deduction', 'pension', 'Pension contribution', $employee->pension_contribution],
            ['deduction', 'loan', 'Loan / recurring deductions', $employee->loan],
            ['deduction', 'workers_union', 'Workers union', $employee->workers_union],
        ] as [$type, $code, $description, $amount]) {
            if ((float) $amount === 0.0) {
                continue;
            }

            $employee->lineItems()->create([
                'type' => $type,
                'code' => $code,
                'description' => $description,
                'amount' => $amount,
            ]);
        }

        foreach ($employee->calculation_snapshot['manual_earnings'] ?? [] as $item) {
            if ((float) ($item['amount'] ?? 0) === 0.0) {
                continue;
            }

            $employee->lineItems()->create([
                'type' => 'earning',
                'code' => $item['code'] ?? 'other_earning',
                'description' => $item['description'] ?? 'Other earning',
                'amount' => (float) ($item['amount'] ?? 0),
            ]);
        }

        foreach ($employee->calculation_snapshot['manual_deductions'] ?? [] as $item) {
            if ((float) ($item['amount'] ?? 0) === 0.0) {
                continue;
            }

            $employee->lineItems()->create([
                'type' => 'deduction',
                'code' => $item['code'] ?? 'other_deduction',
                'description' => $item['description'] ?? 'Other deduction',
                'amount' => (float) ($item['amount'] ?? 0),
            ]);
        }
    }
}
