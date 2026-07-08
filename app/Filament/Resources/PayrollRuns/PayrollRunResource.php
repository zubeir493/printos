<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayrollRunEmployeesRelationManager;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\PayrollRun;
use App\Support\FiscalCalendar;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payroll';

    protected static ?int $navigationSort = 320;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payroll Period')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    Hidden::make('name')
                        ->default('Payroll')
                        ->required(),
                    Hidden::make('period_start'),
                    Hidden::make('period_end'),
                    Select::make('period_type')
                        ->label('Frequency')
                        ->options([
                            'monthly' => 'Monthly',
                            'custom' => 'Custom date range',
                        ])
                        ->default('monthly')
                        ->required()
                        ->live(),
                    Select::make('payroll_month')
                        ->label('Payroll month')
                        ->options(fn (): array => FiscalCalendar::payrollMonthOptions())
                        ->default(fn (): string => FiscalCalendar::currentPayrollMonthStart()->toDateString())
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('period_type') === 'monthly')
                        ->visible(fn (Get $get): bool => $get('period_type') === 'monthly')
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if (! $state) {
                                return;
                            }

                            $period = FiscalCalendar::payrollPeriodForMonth($state);

                            $set('period_start', $period['start']->toDateString());
                            $set('period_end', $period['end']->toDateString());
                            $set('pay_date', $period['pay_date']->toDateString());
                        }),
                    DatePicker::make('pay_date')->default(now()),
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
                ]),
            Section::make('Payroll Summary')
                ->columns(5)
                ->columnSpanFull()
                ->visible(fn (?PayrollRun $record): bool => filled($record?->id))
                ->schema([
                    Placeholder::make('summary_period')
                        ->label('Period')
                        ->content(fn (?PayrollRun $record): string => $record
                            ? FiscalCalendar::payrollPeriodLabel($record->period_type, $record->payroll_month, $record->period_start, $record->period_end)
                            : '-'),
                    Placeholder::make('summary_base_days')
                        ->label('Base days')
                        ->content(fn (?PayrollRun $record): string => (string) static::baseDays($record)),
                    Placeholder::make('summary_employee_count')
                        ->label('Employees')
                        ->content(fn (?PayrollRun $record): string => number_format((int) $record?->employees()->where('net_pay', '>', 0)->count())),
                    Placeholder::make('summary_payroll_cost')
                        ->label('Payroll cost')
                        ->content(fn (?PayrollRun $record): string => Money::format((float) $record?->employees()->where('net_pay', '>', 0)->sum('gross_earning'), 2)),
                    Placeholder::make('summary_net_pay')
                        ->label('Employees net pay')
                        ->content(fn (?PayrollRun $record): string => Money::format((float) $record?->employees()->where('net_pay', '>', 0)->sum('net_pay'), 2)),
                    Placeholder::make('summary_pay_day')
                        ->label('Pay day')
                        ->content(fn (?PayrollRun $record): string => $record?->pay_date?->format('M j, Y') ?? '-'),
                    Placeholder::make('summary_tax')
                        ->label('Taxes')
                        ->content(fn (?PayrollRun $record): string => Money::format((float) $record?->employees()->where('net_pay', '>', 0)->sum('income_tax'), 2)),
                    Placeholder::make('summary_deductions')
                        ->label('Deductions')
                        ->content(fn (?PayrollRun $record): string => Money::format((float) $record?->employees()->where('net_pay', '>', 0)->sum('total_deduction'), 2)),
                    Placeholder::make('summary_bonuses')
                        ->label('Bonuses')
                        ->content(fn (?PayrollRun $record): string => Money::format((float) $record?->employees()->where('net_pay', '>', 0)->sum('bonus'), 2)),
                    Placeholder::make('summary_benefits')
                        ->label('Benefits')
                        ->content(fn (?PayrollRun $record): string => Money::format((float) $record?->employees()->where('net_pay', '>', 0)->sum('transport_allowance'), 2)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('period_type')->label('Frequency')->badge(),
                TextColumn::make('period_display')
                    ->label('Period')
                    ->state(fn (PayrollRun $record): string => FiscalCalendar::payrollPeriodLabel($record->period_type, $record->payroll_month, $record->period_start, $record->period_end)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('payable_employees_count')
                    ->label('Employees')
                    ->state(fn (PayrollRun $record): string => number_format((int) $record->employees()->where('net_pay', '>', 0)->count())),
                TextColumn::make('payable_employees_net_pay')
                    ->label('Net Pay')
                    ->state(fn (PayrollRun $record): string => Money::format((float) $record->employees()->where('net_pay', '>', 0)->sum('net_pay'), 2)),
            ])
            ->filters([
                DateRangeFilter::make('period', 'period_start', 'Period', 'period_end'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollRuns::route('/'),
            'create' => CreatePayrollRun::route('/create'),
            'edit' => EditPayrollRun::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            PayrollRunEmployeesRelationManager::class,
        ];
    }

    private static function baseDays(?PayrollRun $record): int
    {
        if (! $record?->period_start || ! $record->period_end) {
            return 0;
        }

        $date = $record->period_start->toImmutable();
        $end = $record->period_end->toImmutable();
        $days = 0;

        while ($date->lte($end)) {
            if (! $date->isSunday()) {
                $days++;
            }

            $date = $date->addDay();
        }

        return $days;
    }

    /**
     * @return array{start: string|null, end: string|null}
     */
    public static function parsePeriodRange(?string $periodRange): array
    {
        if (! $periodRange || ! str_contains($periodRange, ' - ')) {
            return ['start' => null, 'end' => null];
        }

        [$start, $end] = explode(' - ', $periodRange, 2);

        return [
            'start' => CarbonImmutable::parse(trim($start))->toDateString(),
            'end' => CarbonImmutable::parse(trim($end))->toDateString(),
        ];
    }
}
