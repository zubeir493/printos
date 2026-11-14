<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\Hr\BulkAssignEmployeeShift;
use App\Support\ShiftDisplay;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

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
                    BulkAction::make('assignShift')->label('Assign shift')->icon('heroicon-o-calendar-days')
                        ->authorize(fn (): bool => auth()->user()->can('create', Employee::class))
                        ->modalHeading('Assign shift')->modalSubmitActionLabel('Assign')->modalWidth('lg')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('shift_id')->label('Shift')->required()->searchable()
                                    ->options(fn (): array => Shift::orderBy('start_time')->get()->mapWithKeys(fn (Shift $shift): array => [$shift->id => $shift->name.' ('.ShiftDisplay::time($shift->start_time).' - '.ShiftDisplay::time($shift->end_time).')'])->all()),
                                DatePicker::make('effective_from')->label('From')->default(today())->required(),
                            ]),
                            CheckboxList::make('workdays')->label('Working days')->options([
                                1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun',
                            ])->columns(4)->default([1, 2, 3, 4, 5, 6])->required()->minItems(1),
                            TextInput::make('reason')->placeholder('Optional')->maxLength(255),
                        ])
                        ->action(function (Collection $records, array $data, BulkAction $action, Component $livewire): void {
                            try {
                                $count = app(BulkAssignEmployeeShift::class)->handle($records, $data);
                            } catch (ValidationException $exception) {
                                $path = $livewire->getSchema('mountedActionSchema'.$action->getNestingIndex())->getStatePath();
                                throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [$path.'.'.$field => $messages])->all());
                            }
                            Notification::make()->title('Shift assigned to '.$count.' employees')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
