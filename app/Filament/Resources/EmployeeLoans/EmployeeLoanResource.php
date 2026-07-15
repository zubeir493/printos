<?php

namespace App\Filament\Resources\EmployeeLoans;

use App\Filament\Resources\EmployeeLoans\Pages\ManageEmployeeLoans;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bank;
use App\Models\EmployeeLoan;
use App\Services\Hr\RepayEmployeeLoan;
use App\Support\FiscalCalendar;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeLoanResource extends Resource
{
    protected static ?string $model = EmployeeLoan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationParentItem = 'Payroll';

    protected static ?int $navigationSort = 315;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->label('Employee')
                ->relationship('employee', 'first_name')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('loan_date')
                ->label('Loan Date')
                ->default(now())
                ->required(),
            Select::make('return_date')
                ->label('Deduct In Payroll Month')
                ->options(fn (): array => FiscalCalendar::payrollMonthOptions())
                ->default(fn (): string => FiscalCalendar::currentFiscalYearStart()->toDateString())
                ->native(false)
                ->required(),
            TextInput::make('amount')
                ->numeric()
                ->suffix(fn (): string => Money::suffix())
                ->required(),
            TextInput::make('installment_count')
                ->label('Installments')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'partially_paid' => 'Partially Paid',
                    'deducted' => 'Deducted',
                    'cancelled' => 'Cancelled',
                ])
                ->default('active')
                ->required(),
            Textarea::make('reason')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee', 'installments']))
            ->searchable(true)
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee'),
                TextColumn::make('loan_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('return_date')
                    ->label('Payroll Month')
                    ->formatStateUsing(fn ($state): ?string => FiscalCalendar::payrollMonthLabel($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'info',
                        'partially_paid' => 'warning',
                        'deducted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('remaining_balance')
                    ->label('Remaining')
                    ->state(fn (EmployeeLoan $record): string => Money::format($record->remainingBalance())),
                TextColumn::make('reason')
                    ->limit(40),
                TextColumn::make('amount')
                    ->suffix(fn (): string => Money::suffix())
                    ->summarize(Sum::make()->suffix(fn (): string => Money::suffix())),
            ])
            ->filters([
                DateRangeFilter::make('loan_date_range', 'loan_date', 'Loan date'),

                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'partially_paid' => 'Partially Paid',
                        'deducted' => 'Deducted',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('repay')
                        ->label('Repay')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('gray')
                        ->visible(fn (EmployeeLoan $record): bool => in_array($record->status, ['active', 'partially_paid'], true) && $record->remainingBalance() > 0)
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('method')
                                        ->label('Payment Method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'bank' => 'Bank',
                                            'cheque' => 'Cheque',
                                        ])
                                        ->default('cash')
                                        ->live()
                                        ->required(),
                                    TextInput::make('amount')
                                        ->label('Repayment Amount')
                                        ->numeric()
                                        ->suffix(fn (): string => Money::suffix())
                                        ->required()
                                        ->default(fn (EmployeeLoan $record): float => $record->remainingBalance())
                                        ->minValue(0.01)
                                        ->maxValue(fn (EmployeeLoan $record): float => $record->remainingBalance())
                                        ->helperText('Leave the default to repay the full outstanding balance.'),
                                ]),
                            Select::make('bank_id')
                                ->label('Bank Account')
                                ->options(fn (): array => Bank::query()
                                    ->where('status', 'active')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->visible(fn ($get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                ->required(fn ($get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                        ])
                        ->action(fn (EmployeeLoan $record, array $data) => app(RepayEmployeeLoan::class)->handle(
                            $record,
                            $data['method'],
                            $data['bank_id'] ?? null,
                            (float) ($data['amount'] ?? 0),
                        )),
                ]),
            ])
            ->defaultSort('return_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeLoans::route('/'),
        ];
    }
}
