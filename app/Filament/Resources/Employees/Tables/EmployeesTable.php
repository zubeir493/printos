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
            ->modifyQueryUsing(fn ($query) => $query->with(['workAssignments' => fn ($assignments) => $assignments->with(['workSchedule', 'payPolicy'])->whereDate('effective_from', '<=', today())->where(fn ($dates) => $dates->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))]))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->description(fn ($record) => $record->employee_id)
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('department')
                    ->searchable()
                    ->description(fn ($record) => $record->position)
                    ->sortable(),
                TextColumn::make('current_schedule')->label('Schedule')->state(fn ($record): string => $record->workAssignments->first()?->workSchedule->name ?? 'Not assigned')
                    ->description(fn ($record): ?string => $record->workAssignments->first()?->payPolicy->name),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'terminated' => 'danger',
                        default => 'gray',
                    })
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
