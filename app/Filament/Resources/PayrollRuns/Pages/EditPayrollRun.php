<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\PayrollLineItem;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRunEmployee;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\ExportPayrollBankAdvice;
use App\Services\Hr\ExportPayrollRegisterCsv;
use App\Services\Hr\GeneratePayrollOvertimeEntries;
use App\Services\Hr\GeneratePayrollPayments;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\Services\Hr\RecalculatePayrollRegisterRow;
use App\Support\FiscalCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use RuntimeException;

class EditPayrollRun extends EditRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected string $view = 'filament.resources.payroll-runs.pages.edit-payroll-run';

    public ?string $employeeSearch = null;

    public ?int $detailsEmployeeId = null;

    public bool $reviewingOvertime = false;

    /**
     * @var array<string, mixed>
     */
    public array $detailForm = [];

    public string $newEarningType = 'bonus';

    public string $newDeductionType = 'advance';

    public ?string $editingPayrollDetailLine = null;

    public function getTitle(): string|Htmlable
    {
        $status = e((string) $this->record->status);
        $title = e((string) $this->record->name);
        $badgeClasses = match ($this->record->status) {
            'approved' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-800 dark:bg-warning-950 dark:text-warning-300',
            'paid' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-800 dark:bg-success-950 dark:text-success-300',
            default => 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };

        return new HtmlString(<<<HTML
            <span class="inline-flex flex-wrap items-center gap-2">
                <span>{$title}</span>
                <span class="rounded-md border px-2 py-0.5 text-xs font-medium uppercase {$badgeClasses}">{$status}</span>
            </span>
        HTML);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->periodLabel();
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setupPayroll')
                ->label('Settings')
                ->icon('heroicon-m-cog')
                ->color(Color::Indigo)
                ->slideOver()
                ->modalWidth('md')
                ->fillForm(fn (): array => [
                    'period_type' => $this->record->period_type,
                    'payroll_month' => $this->record->payroll_month?->toDateString(),
                    'pay_date' => $this->record->pay_date?->toDateString(),
                    'period_start' => $this->record->period_start?->toDateString(),
                    'period_end' => $this->record->period_end?->toDateString(),
                    'period_range' => $this->record->period_start && $this->record->period_end
                        ? $this->record->period_start->toDateString().' - '.$this->record->period_end->toDateString()
                        : null,
                ])
                ->schema([
                    Select::make('period_type')
                        ->label('Frequency')
                        ->options([
                            'monthly' => 'Monthly',
                            'custom' => 'Custom date range',
                        ])
                        ->required()
                        ->live(),
                    Select::make('payroll_month')
                        ->label('Payroll month')
                        ->options(fn (): array => FiscalCalendar::payrollMonthOptions())
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('period_type') === 'monthly')
                        ->visible(fn (Get $get): bool => $get('period_type') === 'monthly'),
                    Hidden::make('period_start'),
                    Hidden::make('period_end'),
                    DateRangePicker::make('period_range')
                        ->label('Period')
                        ->required()
                        ->format('Y-m-d')
                        ->disableRanges()
                        ->alwaysShowCalendar()
                        ->autoApply()
                        ->startDate(fn (Get $get) => $get('period_start') ? CarbonImmutable::parse($get('period_start')) : now()->startOfMonth())
                        ->endDate(fn (Get $get) => $get('period_end') ? CarbonImmutable::parse($get('period_end')) : now()->endOfMonth())
                        ->visible(fn (Get $get): bool => $get('period_type') === 'custom'),
                    DatePicker::make('pay_date'),
                ])
                ->action(function (array $data): void {
                    if (($data['period_type'] ?? null) === 'custom') {
                        $periodRange = PayrollRunResource::parsePeriodRange($data['period_range'] ?? null);
                        $data['period_start'] = $periodRange['start'] ?? $data['period_start'] ?? null;
                        $data['period_end'] = $periodRange['end'] ?? $data['period_end'] ?? null;
                    }

                    unset($data['period_range']);

                    $this->record->update($data);
                    app(PrepareMonthlyPayrollRun::class)->handle($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Payroll settings applied')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->modalDescription(fn (): string => $this->approveModalDescription())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->ensureCalculatedPayrollExists();

                    app(PostPayrollRun::class)->handle($this->record);
                    $this->record->refresh();

                    Notification::make()->title('Payroll approved and journal posted')->success()->send();
                }),
            Action::make('generatePayments')
                ->label('Send payments')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'approved')
                ->schema([
                    Select::make('bank_id')
                        ->label('Pay From Bank')
                        ->options(fn (): array => $this->bankOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Callout::make('Insufficient bank balance')
                        ->description(fn (Get $get): string => $this->insufficientBankBalanceMessage((int) $get('bank_id')))
                        ->danger()
                        ->visible(fn (Get $get): bool => $this->selectedBankCannotCoverPayroll((int) $get('bank_id'))),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    app(GeneratePayrollPayments::class)->handle($this->record, 'bank', (int) $data['bank_id']);
                    $this->record->refresh();
                    Notification::make()->title('Payroll payments generated')->success()->send();
                }),
            ActionGroup::make([
                Action::make('reviewOvertime')
                    ->label('Review overtime')
                    ->icon('heroicon-m-clock')
                    ->color(fn (): string => $this->pendingOvertimeApprovalCount() > 0 ? 'warning' : 'gray')
                    ->visible(fn (): bool => $this->record->overtimeEntries()->exists())
                    ->action(fn (): bool => $this->reviewingOvertime = true),
                Action::make('addEmployee')
                    ->label('Add employee')
                    ->modalSubmitActionLabel('Add employee')
                    ->modalWidth('md')
                    ->visible(fn (): bool => $this->record->status === 'draft')
                    ->schema([
                        Select::make('employee_ids')
                            ->label('Employees')
                            ->options(fn (): array => $this->addableEmployeeOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->addPayrollEmployees($data['employee_ids'] ?? []);
                    }),
                Action::make('downloadBankAdvice')
                    ->label('Bank advice')
                    ->color('info')
                    ->visible(fn (): bool => $this->record->employees()->where('net_pay', '>', 0)->exists())
                    ->schema([
                        Select::make('bank_id')
                            ->label('Bank format')
                            ->options(fn (): array => $this->bankOptions())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(fn (array $data) => app(ExportPayrollBankAdvice::class)->download(
                        $this->record,
                        Bank::query()->findOrFail((int) $data['bank_id']),
                    )),
                Action::make('exportRegister')
                    ->label('Export CSV')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->employees()->exists())
                    ->action(fn () => app(ExportPayrollRegisterCsv::class)->download($this->record)),
            ]),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->status !== 'draft') {
            return;
        }

        app(PrepareMonthlyPayrollRun::class)->handle($this->record);
        $this->record->refresh();
        $this->fillForm();

        Notification::make()
            ->title('Payroll refreshed')
            ->body('Employee rows were recalculated from the current payroll setup.')
            ->success()
            ->send();
    }

    private function ensureCalculatedPayrollExists(): void
    {
        if (! $this->record->employees()->exists() || (float) $this->record->employees()->sum('net_pay') <= 0) {
            throw new RuntimeException('Calculate payroll and review employee salary rows before approval.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function bankOptions(): array
    {
        return Bank::query()
            ->where('status', 'active')
            ->orderBy('bank_name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Bank $bank): array => [
                $bank->id => $bank->name.' (available: '.Money::abbreviate($bank->current_balance, 2).')',
            ])
            ->all();
    }

    private function selectedBankCannotCoverPayroll(int $bankId): bool
    {
        if (! $bankId) {
            return false;
        }

        $bankBalance = (float) Bank::query()->whereKey($bankId)->value('current_balance');

        return $bankBalance < $this->remainingPayrollPaymentTotal();
    }

    private function insufficientBankBalanceMessage(int $bankId): string
    {
        $bankBalance = (float) Bank::query()->whereKey($bankId)->value('current_balance');
        $required = $this->remainingPayrollPaymentTotal();

        return 'Available balance is '.Money::abbreviate($bankBalance, 2).', but this payroll needs '.Money::abbreviate($required, 2).'. Select another bank or fund this account first.';
    }

    private function remainingPayrollPaymentTotal(): float
    {
        return (float) $this->record
            ->employees()
            ->whereNull('payment_id')
            ->sum('net_pay');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $employees = $this->summaryRows();

        return [
            'period' => $this->periodLabel(),
            'base_days' => (int) $employees->max(fn (PayrollRunEmployee $employee): float => (float) data_get($employee->calculation_snapshot, 'base_days', 0)),
            'employee_count' => $employees->count(),
            'payroll_cost' => (float) $employees->sum('gross_earning'),
            'net_pay' => (float) $employees->sum('net_pay'),
            'pay_day' => $this->payDateLabel(),
            'taxes' => (float) $employees->sum('income_tax'),
            'deductions' => (float) $employees->sum('total_deduction'),
            'bonuses' => (float) $employees->sum('bonus'),
            'benefits' => (float) $employees->sum('transport_allowance'),
        ];
    }

    /**
     * @return Collection<int, PayrollRunEmployee>
     */
    private function summaryRows(): Collection
    {
        $selectedEmployeeIds = $this->selectedEmployeeIds();

        return $this->record
            ->employees()
            ->when(
                $selectedEmployeeIds !== [],
                fn ($query) => $query->whereIn('employee_id', $selectedEmployeeIds),
                fn ($query) => $query->where('net_pay', '>', 0),
            )
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function selectedEmployeeIds(): array
    {
        return collect($this->record->selected_employee_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PayrollRunEmployee>
     */
    public function payrollRows(): Collection
    {
        $selectedEmployeeIds = $this->selectedEmployeeIds();

        return $this->record
            ->employees()
            ->with('employee')
            ->when($this->employeeSearch, function ($query): void {
                $search = '%'.str($this->employeeSearch)->lower()->value().'%';

                $query->whereHas('employee', function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(first_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(employee_id) LIKE ?', [$search]);
                });
            })
            ->when(
                $selectedEmployeeIds !== [],
                fn ($query) => $query->whereIn('employee_id', $selectedEmployeeIds),
                fn ($query) => $query->where('net_pay', '>', 0),
            )
            ->orderByDesc('net_pay')
            ->get();
    }

    public function payrollDetail(): ?PayrollRunEmployee
    {
        if (! $this->detailsEmployeeId) {
            return null;
        }

        return PayrollRunEmployee::query()
            ->with(['employee', 'lineItems'])
            ->whereBelongsTo($this->record)
            ->find($this->detailsEmployeeId);
    }

    public function showPayrollDetails(int $payrollRunEmployeeId): void
    {
        $this->detailsEmployeeId = $payrollRunEmployeeId;
        $this->fillPayrollDetailForm();
    }

    public function closePayrollDetails(): void
    {
        $this->detailsEmployeeId = null;
        $this->detailForm = [];
        $this->editingPayrollDetailLine = null;
        $this->resetNewLineItemInputs();
    }

    public function closeOvertimeReview(): void
    {
        $this->reviewingOvertime = false;
    }

    public function approveOvertimeEntry(int $entryId): void
    {
        $this->setOvertimeEntryStatus($entryId, PayrollOvertimeEntry::STATUS_APPROVED);
    }

    public function rejectOvertimeEntry(int $entryId): void
    {
        $this->setOvertimeEntryStatus($entryId, PayrollOvertimeEntry::STATUS_REJECTED);
    }

    public function addPayrollDetailLineItem(string $type, ?string $selectedCode = null): void
    {
        $isEarning = $type === 'earning';
        $code = $selectedCode ?: ($isEarning ? $this->newEarningType : $this->newDeductionType);

        if ($code === '') {
            return;
        }

        if ($this->isBuiltInAdjustment($code)) {
            $this->detailForm[$code] = '0';
            $this->editingPayrollDetailLine = $type.':'.$code;
        } else {
            $key = $isEarning ? 'manual_earnings' : 'manual_deductions';
            $this->detailForm[$key] ??= [];
            $this->detailForm[$key][] = [
                'code' => $code,
                'description' => $this->lineItemLabel($code),
                'amount' => 0.0,
            ];
            $this->editingPayrollDetailLine = $type.':'.$code.':'.array_key_last($this->detailForm[$key]);
        }

        $this->resetNewLineItemInputs();
    }

    public function editPayrollDetailLine(string $type, string $code, ?int $index = null): void
    {
        $this->editingPayrollDetailLine = $type.':'.$code.($index === null ? '' : ':'.$index);
    }

    public function removePayrollDetailLineItem(string $type, string $code, ?int $index = null): void
    {
        if ($this->isBuiltInAdjustment($code)) {
            $this->detailForm[$code] = '0';
        } else {
            $key = $type === 'earning' ? 'manual_earnings' : 'manual_deductions';
            $items = $this->detailForm[$key] ?? [];

            if ($index !== null) {
                unset($items[$index]);
            }

            $this->detailForm[$key] = array_values($items);
        }

        $this->editingPayrollDetailLine = null;
        $this->savePayrollDetails(false);
    }

    public function updatePayrollDetailLine(): void
    {
        $this->editingPayrollDetailLine = null;
        $this->savePayrollDetails(false);
    }

    public function removePayrollEmployee(int $payrollRunEmployeeId): void
    {
        $detail = PayrollRunEmployee::query()
            ->whereBelongsTo($this->record)
            ->find($payrollRunEmployeeId);

        if (! $detail || $this->record->status !== 'draft') {
            return;
        }

        $employeeIds = collect($this->rosterEmployeeIds())
            ->reject(fn (int $employeeId): bool => $employeeId === (int) $detail->employee_id)
            ->values()
            ->all();

        if ($employeeIds !== []) {
            $this->record->forceFill([
                'selected_employee_ids' => $employeeIds,
                'excluded_employee_ids' => [],
            ])->save();
            app(CalculatePayrollRun::class)->handle($this->record->refresh(), $employeeIds);
            $this->record->forceFill(['prepared_at' => now()])->save();
        } else {
            $this->record->forceFill([
                'selected_employee_ids' => [],
                'excluded_employee_ids' => [],
            ])->save();
            $detail->delete();
        }

        $this->record->refresh();

        if ($this->detailsEmployeeId === $payrollRunEmployeeId) {
            $this->closePayrollDetails();
        }
    }

    /**
     * @return array<int, int>
     */
    private function rosterEmployeeIds(): array
    {
        $selectedEmployeeIds = $this->selectedEmployeeIds();

        if ($selectedEmployeeIds !== []) {
            return $selectedEmployeeIds;
        }

        return $this->record
            ->employees()
            ->where('net_pay', '>', 0)
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     */
    public function addPayrollEmployees(array $employeeIds): void
    {
        if ($this->record->status !== 'draft') {
            return;
        }

        $employeeIds = collect($this->rosterEmployeeIds())
            ->merge($employeeIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($employeeIds === []) {
            return;
        }

        $this->record->forceFill([
            'selected_employee_ids' => $employeeIds,
            'excluded_employee_ids' => [],
        ])->save();

        app(CalculatePayrollRun::class)->handle($this->record->refresh(), $employeeIds);
        $this->record->forceFill(['prepared_at' => now()])->save();
        $this->record->refresh();
    }

    public function savePayrollDetails(bool $notify = true): void
    {
        $detail = $this->payrollDetail();

        if (! $detail || $this->record->status !== 'draft') {
            return;
        }

        $snapshot = is_array($detail->calculation_snapshot) ? $detail->calculation_snapshot : [];
        $snapshot['manual_earnings'] = $this->normalizedManualItems($this->detailForm['manual_earnings'] ?? []);
        $snapshot['manual_deductions'] = $this->normalizedManualItems($this->detailForm['manual_deductions'] ?? []);

        $detail->forceFill([
            'basic_salary' => max(0, (float) ($this->detailForm['basic_salary'] ?? $detail->basic_salary)),
            'bonus' => max(0, (float) ($this->detailForm['bonus'] ?? 0)),
            'transport_allowance' => max(0, (float) ($this->detailForm['transport_allowance'] ?? 0)),
            'overtime_amount' => max(0, (float) ($this->detailForm['overtime_amount'] ?? $detail->overtime_amount)),
            'penalty_hours' => max(0, (float) ($this->detailForm['penalty_hours'] ?? 0)),
            'loan' => max(0, (float) ($this->detailForm['loan'] ?? 0)),
            'calculation_snapshot' => $snapshot,
        ]);

        app(RecalculatePayrollRegisterRow::class)->forModel($detail, $this->record);
        $detail->save();
        $this->syncPayrollDetailLineItems($detail->refresh());
        $this->record->refresh();
        $this->fillPayrollDetailForm();

        if ($notify) {
            Notification::make()->title('Payroll detail updated')->success()->send();
        }
    }

    public function money(float|int|string|null $amount, int $precision = 2): string
    {
        return Money::format((float) $amount, $precision);
    }

    /**
     * @return Collection<int, PayrollOvertimeEntry>
     */
    public function overtimeReviewEntries(): Collection
    {
        return $this->record
            ->overtimeEntries()
            ->with(['employee', 'overtimeRule'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    public function pendingOvertimeApprovalCount(): int
    {
        return $this->record
            ->overtimeEntries()
            ->where('status', PayrollOvertimeEntry::STATUS_PENDING)
            ->count();
    }

    public function approveModalDescription(): string
    {
        return $this->pendingOvertimeApprovalCount() > 0
            ? $this->pendingOvertimeApprovalCount().' pending overtime candidates will remain unpaid unless approved. You can still approve this payroll.'
            : 'Approve this payroll and post its journal entry.';
    }

    public function overtimeStatusColor(string $status): string
    {
        return match ($status) {
            PayrollOvertimeEntry::STATUS_APPROVED => 'success',
            PayrollOvertimeEntry::STATUS_REJECTED => 'danger',
            default => 'warning',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function earningBreakdown(PayrollRunEmployee $detail): array
    {
        $form = $this->detailsEmployeeId === $detail->id ? $this->detailForm : [];
        $manualItems = $form['manual_earnings'] ?? $detail->calculation_snapshot['manual_earnings'] ?? [];

        $manual = collect($manualItems)
            ->map(fn (array $item, int $index): array => [
                'label' => $item['description'] ?? $this->lineItemLabel($item['code'] ?? 'other_earning'),
                'amount' => (float) ($item['amount'] ?? 0),
                'code' => $item['code'] ?? 'other_earning',
                'removable' => true,
                'editable' => true,
                'index' => $index,
            ]);

        $fixed = collect([
            ['label' => 'Basic', 'amount' => (float) ($form['basic_salary'] ?? $detail->basic_salary), 'code' => 'basic_salary', 'removable' => false, 'editable' => false],
        ]);

        $adjustments = collect([
            ['label' => 'Bonus', 'amount' => (float) ($form['bonus'] ?? $detail->bonus), 'code' => 'bonus', 'removable' => true, 'editable' => true, 'field' => 'bonus'],
            ['label' => 'Allowance', 'amount' => (float) ($form['transport_allowance'] ?? $detail->transport_allowance), 'code' => 'transport_allowance', 'removable' => true, 'editable' => true, 'field' => 'transport_allowance'],
            ['label' => 'Overtime', 'amount' => (float) ($form['overtime_amount'] ?? $detail->overtime_amount), 'code' => 'overtime_amount', 'removable' => true, 'editable' => true, 'field' => 'overtime_amount'],
        ]);

        return $fixed
            ->merge($adjustments)
            ->merge($manual)
            ->filter(fn (array $item): bool => (float) $item['amount'] !== 0.0 || ! $item['removable'] || $this->isEditingLine('earning', $item))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deductionBreakdown(PayrollRunEmployee $detail): array
    {
        $form = $this->detailsEmployeeId === $detail->id ? $this->detailForm : [];
        $manualItems = $form['manual_deductions'] ?? $detail->calculation_snapshot['manual_deductions'] ?? [];

        $manual = collect($manualItems)
            ->map(fn (array $item, int $index): array => [
                'label' => $item['description'] ?? $this->lineItemLabel($item['code'] ?? 'other_deduction'),
                'amount' => (float) ($item['amount'] ?? 0),
                'code' => $item['code'] ?? 'other_deduction',
                'removable' => true,
                'editable' => true,
                'index' => $index,
            ]);

        $fixed = collect([
            ['label' => 'Income tax', 'amount' => (float) $detail->income_tax, 'code' => 'income_tax', 'removable' => false, 'editable' => false],
            ['label' => 'Pension', 'amount' => (float) $detail->pension_contribution, 'code' => 'pension', 'removable' => false, 'editable' => false],
            ['label' => 'Workers union', 'amount' => (float) $detail->workers_union, 'code' => 'workers_union', 'removable' => false, 'editable' => false],
        ]);

        $adjustments = collect([
            ['label' => 'Penalty', 'amount' => (float) $detail->penalty_amount, 'code' => 'penalty_hours', 'removable' => true, 'editable' => true, 'field' => 'penalty_hours', 'suffix' => 'hrs'],
            ['label' => 'Loan', 'amount' => (float) ($form['loan'] ?? $detail->loan), 'code' => 'loan', 'removable' => true, 'editable' => true, 'field' => 'loan'],
        ]);

        return $fixed
            ->merge($adjustments)
            ->merge($manual)
            ->filter(fn (array $item): bool => (float) $item['amount'] !== 0.0 || ! $item['removable'] || $this->isEditingLine('deduction', $item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function earningTypeOptions(): array
    {
        return $this->availableLineItemOptions($this->allEarningTypeOptions(), 'earning');
    }

    /**
     * @return array<string, string>
     */
    public function deductionTypeOptions(): array
    {
        return $this->availableLineItemOptions($this->allDeductionTypeOptions(), 'deduction');
    }

    public function periodLabel(): string
    {
        return FiscalCalendar::payrollPeriodLabel($this->record->period_type, $this->record->payroll_month, $this->record->period_start, $this->record->period_end);
    }

    public function payDateLabel(): string
    {
        if (! $this->record->pay_date) {
            return '-';
        }

        if ($this->record->period_type === 'monthly') {
            return FiscalCalendar::payrollMonthLabel($this->record->pay_date) ?? $this->record->pay_date->format('M j, Y');
        }

        return $this->record->pay_date->format('M j, Y');
    }

    /**
     * @return array<int, string>
     */
    public function addableEmployeeOptions(?string $search = null): array
    {
        if ($search !== null && blank(trim($search))) {
            return [];
        }

        $currentEmployeeIds = $this->rosterEmployeeIds();

        return Employee::query()
            ->whereNotIn('id', $currentEmployeeIds)
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->when($search, function ($query, string $search): void {
                $search = '%'.str($search)->lower()->value().'%';

                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(first_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(employee_id) LIKE ?', [$search]);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(8)
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->full_name])
            ->all();
    }

    private function fillPayrollDetailForm(): void
    {
        $detail = $this->payrollDetail();

        if (! $detail) {
            return;
        }

        $snapshot = is_array($detail->calculation_snapshot) ? $detail->calculation_snapshot : [];

        $this->detailForm = [
            'basic_salary' => (string) $detail->basic_salary,
            'bonus' => (string) $detail->bonus,
            'transport_allowance' => (string) $detail->transport_allowance,
            'overtime_amount' => (string) $detail->overtime_amount,
            'penalty_hours' => (string) $detail->penalty_hours,
            'loan' => (string) $detail->loan,
            'manual_earnings' => $this->normalizedManualItems($snapshot['manual_earnings'] ?? []),
            'manual_deductions' => $this->normalizedManualItems($snapshot['manual_deductions'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{code: string, description: string, amount: float}>
     */
    private function normalizedManualItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'code' => (string) ($item['code'] ?? 'other'),
                'description' => (string) ($item['description'] ?? $this->lineItemLabel($item['code'] ?? 'other')),
                'amount' => round(max(0, (float) ($item['amount'] ?? 0)), 2),
            ])
            ->filter(fn (array $item): bool => $item['amount'] > 0)
            ->values()
            ->all();
    }

    private function resetNewLineItemInputs(): void
    {
        $this->newEarningType = (string) array_key_first($this->earningTypeOptions());
        $this->newDeductionType = (string) array_key_first($this->deductionTypeOptions());
    }

    private function lineItemLabel(string $code): string
    {
        return $this->allEarningTypeOptions()[$code]
            ?? $this->allDeductionTypeOptions()[$code]
            ?? str((string) $code)->replace('_', ' ')->title()->value();
    }

    /**
     * @return array<string, string>
     */
    private function allEarningTypeOptions(): array
    {
        return [
            'bonus' => 'Bonus',
            'transport_allowance' => 'Allowance',
            'overtime_amount' => 'Overtime',
            'commission' => 'Commission',
            'reimbursement' => 'Reimbursement',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function allDeductionTypeOptions(): array
    {
        return [
            'loan' => 'Loan',
            'penalty_hours' => 'Penalty',
            'advance' => 'Salary advance',
            'damage' => 'Damage deduction',
            'fine' => 'Fine',
            'other_deduction' => 'Other deduction',
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private function availableLineItemOptions(array $options, string $type): array
    {
        $detail = $this->payrollDetail();

        if (! $detail) {
            return $options;
        }

        $existing = collect($type === 'earning' ? $this->earningBreakdown($detail) : $this->deductionBreakdown($detail))
            ->pluck('code')
            ->all();

        return collect($options)
            ->reject(fn (string $label, string $code): bool => in_array($code, $existing, true))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isEditingLine(string $type, array $item): bool
    {
        return $this->editingPayrollDetailLine === $type.':'.$item['code']
            || (isset($item['index']) && $this->editingPayrollDetailLine === $type.':'.$item['code'].':'.$item['index']);
    }

    private function isBuiltInAdjustment(string $code): bool
    {
        return in_array($code, ['bonus', 'transport_allowance', 'overtime_amount', 'loan', 'penalty_hours'], true);
    }

    private function syncPayrollDetailLineItems(PayrollRunEmployee $detail): void
    {
        $detail->lineItems()->delete();

        foreach ($this->earningBreakdown($detail) as $item) {
            if ((float) $item['amount'] === 0.0) {
                continue;
            }

            $this->createPayrollLineItem($detail, 'earning', $item);
        }

        foreach ($this->deductionBreakdown($detail) as $item) {
            if ((float) $item['amount'] === 0.0) {
                continue;
            }

            $this->createPayrollLineItem($detail, 'deduction', $item);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createPayrollLineItem(PayrollRunEmployee $detail, string $type, array $item): void
    {
        PayrollLineItem::query()->create([
            'payroll_run_employee_id' => $detail->id,
            'type' => $type,
            'code' => $item['code'],
            'description' => $item['label'],
            'amount' => $item['amount'],
        ]);
    }

    private function setOvertimeEntryStatus(int $entryId, string $status): void
    {
        if ($this->record->status !== 'draft') {
            return;
        }

        $entry = PayrollOvertimeEntry::query()
            ->whereBelongsTo($this->record)
            ->find($entryId);

        if (! $entry) {
            return;
        }

        $entry->update([
            'status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $detail = PayrollRunEmployee::query()
            ->whereBelongsTo($this->record)
            ->where('employee_id', $entry->employee_id)
            ->first();

        if ($detail) {
            $this->refreshPayrollOvertimeTotals($detail);
        }

        $this->record->refresh();
    }

    private function refreshPayrollOvertimeTotals(PayrollRunEmployee $detail): void
    {
        $totals = app(GeneratePayrollOvertimeEntries::class)->approvedTotals($this->record, $detail);
        $snapshot = is_array($detail->calculation_snapshot) ? $detail->calculation_snapshot : [];
        $snapshot['approved_overtime_entry_ids'] = $totals['ids'];

        $detail->forceFill([
            'overtime_hours' => $totals['hours'],
            'overtime_amount' => $totals['amount'],
            'calculation_snapshot' => $snapshot,
        ]);

        app(RecalculatePayrollRegisterRow::class)->forModel($detail, $this->record);
        $detail->save();
        $this->syncPayrollDetailLineItems($detail->refresh());

        if ($this->detailsEmployeeId === $detail->id) {
            $this->fillPayrollDetailForm();
        }
    }
}
