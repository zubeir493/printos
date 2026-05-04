<?php

namespace App\Filament\Hr\Widgets;

use App\Models\EmployeeSalaryHistory;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SalaryRevisionHistoryTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = 'Salary Revision History';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EmployeeSalaryHistory::query()
                    ->with('employee')
                    ->latest('effective_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('effective_date')
                    ->date(),
                Tables\Columns\TextColumn::make('basic_salary')
                    ->label('Basic Salary')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('change_reason')
                    ->label('Reason')
                    ->wrap(),
            ])
            ->paginated(false);
    }
}
