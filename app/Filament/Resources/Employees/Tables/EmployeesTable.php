<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->description(fn ($record) => $record->employee_id)
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->searchable(),
                TextColumn::make('payroll_readiness')->label('Payroll setup')->state(fn ($record): string => collect(['Attendance device' => $record->attendance_device_id, 'Bank name' => $record->bank_name, 'Account number' => $record->account_number, 'Tax ID' => $record->tax_id])->filter(fn ($value): bool => blank($value))->keys()->join(', ') ?: 'Ready')->description('Missing fields'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('hire_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('hire_date_range', 'hire_date', 'Hire date'),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'terminated' => 'Terminated',
                    ]),
                SelectFilter::make('department'),
            ])
            ->defaultSort('hire_date', 'desc')
            ->actions([])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
