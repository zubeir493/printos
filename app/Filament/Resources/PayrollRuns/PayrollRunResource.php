<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayrollRunEmployeesRelationManager;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bank;
use App\Models\PayrollRun;
use App\Services\Hr\CorrectPayrollRun;
use App\Services\Hr\ExportPayrollBankAdvice;
use App\Services\Hr\ExportPayrollDocuments;
use App\Services\Hr\ExportPayrollRegisterCsv;
use App\Services\Hr\ManagePayrollPaymentBatch;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\RecordPayrollRemittance;
use App\Support\FiscalCalendar;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Select as ActionSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
        $currency = Money::currencyCode();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('employees')->withCount(['employees as payable_count' => fn ($employees) => $employees->where('net_pay', '>', 0)])->withSum('employees', 'net_pay'))
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
                    ->state(fn (PayrollRun $record): string => number_format((int) $record->payable_count)),
                TextColumn::make('payable_employees_net_pay')
                    ->label('Net Pay')
                    ->state(fn (PayrollRun $record): string => Money::format((float) $record->employees_sum_net_pay, 2, $currency)),
            ])
            ->filters([
                DateRangeFilter::make('period', 'period_start', 'Period', 'period_end'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ...static::batchActions(),
                    EditAction::make()
                        ->color('gray'),
                    Action::make('approve')
                        ->label('Approve')
                        ->color('gray')
                        ->visible(fn (PayrollRun $record): bool => $record->status === 'draft')
                        ->requiresConfirmation()
                        ->action(function (PayrollRun $record): void {
                            app(PostPayrollRun::class)->handle($record);

                            Notification::make()->title('Payroll approved and journal posted')->success()->send();
                        }),
                    Action::make('generatePayments')
                        ->label('Prepare payment batch')
                        ->color('gray')
                        ->visible(fn (PayrollRun $record): bool => $record->status === 'approved')
                        ->schema([
                            ActionSelect::make('bank_id')->getSearchResultsUsing(fn (string $search): array => Bank::query()->where('status', 'active')->where('name', 'like', '%'.$search.'%')->limit(50)->pluck('name', 'id')->all())->getOptionLabelUsing(fn ($value): ?string => Bank::find($value)?->name)
                                ->label('Pay From Bank')
                                ->options(fn (): array => Bank::query()
                                    ->where('status', 'active')
                                    ->orderBy('bank_name')
                                    ->orderBy('name')
                                    ->limit(50)->get()
                                    ->mapWithKeys(fn (Bank $bank): array => [
                                        $bank->id => $bank->name.' (available: '.Money::abbreviate($bank->current_balance, 2).')',
                                    ])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function (PayrollRun $record, array $data): void {
                            app(ManagePayrollPaymentBatch::class)->prepare($record, Bank::findOrFail((int) $data['bank_id']));

                            Notification::make()->title('Payment batch prepared; no funds recorded as paid')->success()->send();
                        }),
                    Action::make('downloadBankAdvice')
                        ->label('Bank advice')
                        ->color('gray')
                        ->visible(fn (PayrollRun $record): bool => $record->payable_count > 0)
                        ->schema([
                            ActionSelect::make('bank_id')->getSearchResultsUsing(fn (string $search): array => Bank::query()->where('status', 'active')->where('name', 'like', '%'.$search.'%')->limit(50)->pluck('name', 'id')->all())->getOptionLabelUsing(fn ($value): ?string => Bank::find($value)?->name)
                                ->label('Bank format')
                                ->options(fn (): array => Bank::query()
                                    ->where('status', 'active')
                                    ->orderBy('bank_name')
                                    ->orderBy('name')
                                    ->limit(50)->get()
                                    ->mapWithKeys(fn (Bank $bank): array => [
                                        $bank->id => $bank->name.' (available: '.Money::abbreviate($bank->current_balance, 2).')',
                                    ])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(fn (PayrollRun $record, array $data) => app(ExportPayrollBankAdvice::class)->download(
                            $record,
                            Bank::query()->findOrFail((int) $data['bank_id']),
                        )),
                    Action::make('exportRegister')
                        ->label('Export CSV')
                        ->color('gray')
                        ->visible(fn (PayrollRun $record): bool => $record->employees_count > 0)
                        ->action(fn (PayrollRun $record) => app(ExportPayrollRegisterCsv::class)->download($record)),
                ]),
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

    /** @return array<Action> */
    public static function batchActions(?PayrollRun $run = null): array
    {
        $actions = [
            Action::make('recordRemittance')->label('Record tax / pension / union remittance')->visible(fn (?PayrollRun $record): bool => in_array(($record ?? $run)?->status, ['approved', 'paid'], true))->schema([ActionSelect::make('type')->options(['payroll_tax_remittance' => 'PAYE', 'payroll_pension_remittance' => 'Pension', 'payroll_union_remittance' => 'Workers union'])->required(), TextInput::make('amount')->numeric()->minValue(0.01)->required(), ActionSelect::make('bank_id')->getSearchResultsUsing(fn (string $search): array => Bank::query()->where('status', 'active')->where('name', 'like', '%'.$search.'%')->limit(50)->pluck('name', 'id')->all())->getOptionLabelUsing(fn ($value): ?string => Bank::find($value)?->name)->options(fn () => Bank::query()->where('status', 'active')->limit(50)->pluck('name', 'id'))->searchable()->required(), TextInput::make('reference')->required()])->requiresConfirmation()->action(fn (?PayrollRun $record, array $data) => app(RecordPayrollRemittance::class)->handle($record ?? $run, $data['type'], (float) $data['amount'], (int) $data['bank_id'], $data['reference'])),
            Action::make('correctPayroll')->label('Reverse and create correction')->visible(fn (?PayrollRun $record): bool => ($record ?? $run)?->status === 'approved')->schema([Textarea::make('reason')->required()])->requiresConfirmation()->action(function (?PayrollRun $record, array $data) use ($run): void {
                app(CorrectPayrollRun::class)->handle($record ?? $run, $data['reason']);
            }),
            Action::make('exportRemittances')->label('Tax, pension and union summary')->action(fn (?PayrollRun $record) => app(ExportPayrollDocuments::class)->remittances($record ?? $run)),
        ];
        foreach (['submitted' => 'Record bank submission', 'settled' => 'Confirm bank settlement', 'failed' => 'Record failed batch'] as $status => $label) {
            $actions[] = Action::make('batch_'.$status)->label($label)
                ->visible(fn (?PayrollRun $record): bool => ($record ?? $run)?->status === 'approved')
                ->schema([
                    ActionSelect::make('batch_id')->label('Batch')->options(fn (?PayrollRun $record): array => ($record ?? $run)->paymentBatches()->whereIn('status', $status === 'submitted' ? ['generated'] : ($status === 'settled' ? ['submitted'] : ['generated', 'submitted']))->get()->mapWithKeys(fn ($batch): array => [$batch->id => '#'.$batch->id.' '.$batch->status.' '.Money::format($batch->amount)])->all())->required(),
                    TextInput::make('reference')->label('Bank reference')->required(),
                    Textarea::make('note')->required($status === 'failed'),
                ])->requiresConfirmation()->action(function (?PayrollRun $record, array $data) use ($run, $status): void {
                    $batch = ($record ?? $run)->paymentBatches()->findOrFail($data['batch_id']);
                    app(ManagePayrollPaymentBatch::class)->transition($batch, $status, $data['reference'], $data['note'] ?? '');
                });
        }
        $actions[] = Action::make('downloadHistoricalAdvice')->label('Payment batch history / download')->schema([
            ActionSelect::make('batch_id')->label('Batch')->options(fn (?PayrollRun $record): array => ($record ?? $run)->paymentBatches()->latest('id')->get()->mapWithKeys(fn ($batch): array => [$batch->id => '#'.$batch->id.' '.$batch->status.' '.($batch->reference ?? '')])->all())->required(),
        ])->action(function (?PayrollRun $record, array $data) use ($run) {
            $payroll = $record ?? $run;
            $batch = $payroll->paymentBatches()->findOrFail($data['batch_id']);

            return response()->streamDownload(fn () => print app(ExportPayrollBankAdvice::class)->csv($payroll, $batch->bank, $batch->id), 'payroll-batch-'.$batch->id.'.csv');
        });

        return $actions;
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
