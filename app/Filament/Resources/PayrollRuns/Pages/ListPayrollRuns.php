<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\Support\FiscalCalendar;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPayroll')
                ->label('New Payroll')
                ->icon('heroicon-m-plus')
                ->modalWidth('2xl')
                ->modalHeading('New payroll run')
                ->modalDescription('Set the payroll period now. Detailed employee selection can be adjusted after the draft opens.')
                ->schema([
                    Group::make()
                        ->columns(2)
                        ->schema([
                            Select::make('period_type')
                                ->label('Frequency')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'custom' => 'Custom date range',
                                ])
                                ->default('monthly')
                                ->required()
                                ->live(),
                            DatePicker::make('pay_date')
                                ->default(now()),
                            Hidden::make('period_start'),
                            Hidden::make('period_end'),
                            Select::make('payroll_month')
                                ->label('Payroll month')
                                ->options(fn (): array => FiscalCalendar::payrollMonthOptions())
                                ->default(fn (): string => FiscalCalendar::currentPayrollMonthStart()->toDateString())
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('period_type') === 'monthly')
                                ->visible(fn (Get $get): bool => $get('period_type') === 'monthly'),
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
                            Select::make('employment_type')
                                ->options(fn (): array => $this->employmentTypeOptions())
                                ->searchable()
                                ->multiple()
                                ->placeholder('All employment types'),
                        ]),
                ])
                ->action(function (array $data): void {
                    $period = $data['period_type'] === 'monthly'
                        ? FiscalCalendar::payrollPeriodForMonth($data['payroll_month'])
                        : null;
                    $periodRange = PayrollRunResource::parsePeriodRange($data['period_range'] ?? null);
                    $periodStart = $period ? $period['start']->toDateString() : ($periodRange['start'] ?? $data['period_start'] ?? null);
                    $periodEnd = $period ? $period['end']->toDateString() : ($periodRange['end'] ?? $data['period_end'] ?? null);

                    if (! $periodStart || ! $periodEnd) {
                        throw ValidationException::withMessages([
                            'period_range' => 'Select the payroll period.',
                        ]);
                    }

                    $data['employment_type'] = PayrollRun::encodeEmploymentTypes($data['employment_type'] ?? null);

                    $payrollRun = PayrollRun::create([
                        ...collect($data)->except('period_range')->all(),
                        'name' => $data['period_type'] === 'monthly'
                            ? FiscalCalendar::payrollMonthLabel($data['payroll_month']).' payroll'
                            : 'Payroll: '.FiscalCalendar::formatDateRange($periodStart, $periodEnd),
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'pay_date' => $data['pay_date'] ?? ($period['pay_date']?->toDateString() ?? null),
                        'created_by' => auth()->id(),
                    ]);

                    app(PrepareMonthlyPayrollRun::class)->handle($payrollRun);

                    $this->redirect(PayrollRunResource::getUrl('edit', ['record' => $payrollRun]), navigate: true);
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function employmentTypeOptions(): array
    {
        return Employee::query()
            ->whereNotNull('employment_type')
            ->distinct()
            ->orderBy('employment_type')
            ->pluck('employment_type', 'employment_type')
            ->all();
    }
}
