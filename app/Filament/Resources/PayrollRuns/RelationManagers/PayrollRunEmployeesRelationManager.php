<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Models\PayrollRunEmployee;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('time_on_duty')
                    ->label('Paid Days')
                    ->formatStateUsing(fn (PayrollRunEmployee $record): string => number_format((float) data_get($record->calculation_snapshot, 'paid_days', ((float) $record->time_on_duty / 8)), 2))
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label('Paid Days')),
                TextColumn::make('gross_earning')
                    ->label('Gross')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Gross')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('transport_allowance')
                    ->label('Benefits')
                    ->formatStateUsing(fn (PayrollRunEmployee $record): string => Money::format((float) $record->transport_allowance + (float) $record->bonus + (float) $record->employer_pension_contribution, 2))
                    ->summarize(Sum::make()->label('Benefits')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('total_deduction')
                    ->label('Deductions')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Deductions')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('income_tax')
                    ->label('Tax')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Income Tax')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('net_pay')
                    ->label('Net')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->label('Net Pay')->formatStateUsing(fn ($state) => Money::format($state))),
                IconColumn::make('payment_id')
                    ->label('Payment')
                    ->boolean()
                    ->state(fn (PayrollRunEmployee $record): bool => filled($record->payment_id)),
            ])
            ->actions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->slideOver()
                    ->schema(fn (PayrollRunEmployee $record): array => [
                        Section::make($record->employee?->full_name ?? 'Employee')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('basic_salary')->label('Basic')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('pay_per_hour')->label('Hourly rate')->formatStateUsing(fn ($state) => Money::format($state, 4)),
                                TextEntry::make('time_on_duty')->label('Hours paid')->numeric(decimalPlaces: 2),
                                TextEntry::make('overtime_hours')->label('Overtime hours')->numeric(decimalPlaces: 2),
                                TextEntry::make('overtime_amount')->label('Overtime amount')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('penalty_hours')->label('Penalty hours')->numeric(decimalPlaces: 2),
                                TextEntry::make('penalty_amount')->label('Penalty amount')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('pension_contribution')->label('Pension')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('loan')->label('Loan')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('workers_union')->label('Union')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('taxable_amount')->label('Taxable')->formatStateUsing(fn ($state) => Money::format($state)),
                                TextEntry::make('income_tax')->label('Income tax')->formatStateUsing(fn ($state) => Money::format($state)),
                            ]),
                    ]),
            ])
            ->defaultSort('net_pay', 'desc');
    }
}
