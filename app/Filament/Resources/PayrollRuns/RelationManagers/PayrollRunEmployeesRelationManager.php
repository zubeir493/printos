<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollRunEmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Calculated Salaries';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('bold')
                    ->description(fn ($record) => $record->employee?->employee_id),
                TextColumn::make('basic_salary')
                    ->label('Basic Salary')
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('time_on_duty')
                    ->label('Time On Duty')
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label('Time On Duty')),
                TextColumn::make('pay_per_hour')
                    ->label('Pay/Hr')
                    ->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('bonus')
                    ->label('Bonus')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Bonus')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('transport_allowance')
                    ->label('Transport')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Transport')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('employer_pension_contribution')
                    ->label('Employer Pension')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Employer Pension')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('overtime_hours')
                    ->label('OT Hrs')
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label('OT Hrs')),
                TextColumn::make('overtime_amount')
                    ->label('OT Amt')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('OT Amt')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('gross_earning')
                    ->label('Gross')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Gross')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('taxable_amount')
                    ->label('Taxable')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Taxable')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('income_tax')
                    ->label('Income Tax')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Income Tax')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('penalty_hours')
                    ->label('Penalty Hrs')
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label('Penalty Hrs')),
                TextColumn::make('penalty_amount')
                    ->label('Penalty')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Penalty')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('pension_contribution')
                    ->label('Pension')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Pension')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('loan')
                    ->label('Loan')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Loan')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('workers_union')
                    ->label('Union')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Union')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('total_deduction')
                    ->label('Deductions')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Deductions')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('net_pay')
                    ->label('Net Pay')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->label('Net Pay')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('payment.payment_number')
                    ->label('Payment')
                    ->placeholder('-'),
            ])
            ->defaultSort('net_pay', 'desc');
    }
}
