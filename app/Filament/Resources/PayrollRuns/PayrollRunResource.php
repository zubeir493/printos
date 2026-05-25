<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\Hr\RecalculatePayrollRegisterRow;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

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
                    TextInput::make('name')->required(),
                    DatePicker::make('pay_date')->default(now()),
                    DatePicker::make('period_start')->required()->default(now()->subDays(30)),
                    DatePicker::make('period_end')->required()->default(now()),
                ]),
            Group::make()
                ->columnSpanFull()
                ->visible(fn(?PayrollRun $record, string $operation): bool => $operation !== 'create' && filled($record?->id))
                ->schema([
                    Repeater::make('employees')
                        ->extraAttributes(['class' => 'payrollTable'])
                        ->relationship()
                        ->label('')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->disabled(fn(?PayrollRun $record): bool => $record?->status !== 'draft')
                        ->compact()
                        ->table([
                            TableColumn::make('Employee'),
                            TableColumn::make('Basic'),
                            TableColumn::make('Duty'),
                            TableColumn::make('Rate'),
                            TableColumn::make('Bonus'),
                            TableColumn::make('Transport'),
                            TableColumn::make('Employer Pension'),
                            TableColumn::make('OT Hrs'),
                            TableColumn::make('OT Amt'),
                            TableColumn::make('Gross'),
                            TableColumn::make('Taxable'),
                            TableColumn::make('Tax'),
                            TableColumn::make('Penalty Hrs'),
                            TableColumn::make('Penalty'),
                            TableColumn::make('Pension'),
                            TableColumn::make('Loan'),
                            TableColumn::make('Union'),
                            TableColumn::make('Deductions'),
                            TableColumn::make('Net')->width('9rem'),
                        ])
                        ->schema([
                            Hidden::make('calculation_snapshot'),
                            Select::make('employee_id')
                                ->relationship('employee', 'first_name')
                                ->getOptionLabelFromRecordUsing(fn(Employee $record): string => $record->full_name)
                                ->searchable()
                                ->preload()
                                ->disabled()
                                ->extraAttributes(['class' => 'payroll-register-employee-field'])
                                ->dehydrated(),
                            static::moneyInput('basic_salary')->disabled(),
                            static::numberInput('time_on_duty', 2)->disabled(),
                            static::moneyInput('pay_per_hour')->disabled(),
                            static::editableMoneyInput('bonus'),
                            static::editableMoneyInput('transport_allowance'),
                            static::moneyInput('employer_pension_contribution')->disabled(),
                            static::editableNumberInput('overtime_hours', 2),
                            static::moneyInput('overtime_amount')->disabled(),
                            static::moneyInput('gross_earning')->disabled(),
                            static::moneyInput('taxable_amount')->disabled(),
                            static::moneyInput('income_tax')->disabled(),
                            static::editableNumberInput('penalty_hours', 2),
                            static::moneyInput('penalty_amount')->disabled(),
                            static::moneyInput('pension_contribution')->disabled(),
                            static::editableMoneyInput('loan'),
                            static::moneyInput('workers_union')->disabled(),
                            static::moneyInput('total_deduction')->disabled(),
                            static::moneyInput('net_pay')->disabled()->suffix('Birr'),
                        ])
                        ->mutateRelationshipDataBeforeSaveUsing(fn(array $data, Model $record): array => app(RecalculatePayrollRegisterRow::class)->forData($data, $record->payrollRun()->first())),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('period_start')->date(),
                TextColumn::make('period_end')->date(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('employees_count')->counts('employees')->label('Employees'),
                TextColumn::make('employees_sum_net_pay')->sum('employees', 'net_pay')->money('ETB')->label('Net Pay'),
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
        return [];
    }

    private static function moneyInput(string $name): TextInput
    {
        return static::numberInput($name, 2);
    }

    private static function editableMoneyInput(string $name): TextInput
    {
        return static::editableNumberInput($name, 2);
    }

    private static function editableNumberInput(string $name, int $decimalPlaces): TextInput
    {
        return static::numberInput($name, $decimalPlaces);
    }

    private static function numberInput(string $name, int $decimalPlaces): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->step($decimalPlaces === 2 ? '0.01' : '0.0001')
            ->extraInputAttributes(['class' => 'payroll-register-number-input'])
            ->dehydrated()
            ->live(onBlur: true)
            ->afterStateUpdated(fn(Get $get, Set $set): null => static::recalculatePayrollRow($get, $set));
    }

    private static function recalculatePayrollRow(Get $get, Set $set): null
    {
        $data = [];

        foreach (
            [
                'basic_salary',
                'time_on_duty',
                'pay_per_hour',
                'bonus',
                'transport_allowance',
                'employer_pension_contribution',
                'overtime_hours',
                'overtime_amount',
                'gross_earning',
                'taxable_amount',
                'income_tax',
                'penalty_hours',
                'penalty_amount',
                'pension_contribution',
                'loan',
                'workers_union',
                'total_deduction',
                'net_pay',
                'calculation_snapshot',
            ] as $field
        ) {
            $data[$field] = $get($field);
        }

        $calculated = app(RecalculatePayrollRegisterRow::class)->forData($data);

        foreach (
            [
                'pay_per_hour',
                'employer_pension_contribution',
                'overtime_amount',
                'gross_earning',
                'taxable_amount',
                'income_tax',
                'penalty_amount',
                'pension_contribution',
                'workers_union',
                'total_deduction',
                'net_pay',
                'calculation_snapshot',
            ] as $field
        ) {
            $set($field, $calculated[$field]);
        }

        return null;
    }
}
