<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\EmployeeShiftOverride;
use App\Models\Shift;
use App\Support\ShiftDisplay;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ShiftOverridesRelationManager extends RelationManager
{
    protected static string $relationship = 'shiftOverrides';

    protected static ?string $title = 'Shift overrides';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->required(),
            Select::make('shift_ids')->label('Shifts')->multiple()->options(fn (): array => Shift::orderBy('start_time')->get()->mapWithKeys(fn (Shift $shift): array => [$shift->id => $shift->name.' ('.ShiftDisplay::time($shift->start_time).' - '.ShiftDisplay::time($shift->end_time).')'])->all())->searchable()->placeholder('Rest day')->afterStateHydrated(fn (Select $component, ?EmployeeShiftOverride $record) => $component->state($record?->shift_ids ?? [])),
            Textarea::make('reason')->label('Approval reason')->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('shift', 'additionalShifts'))->columns([TextColumn::make('date')->date(), TextColumn::make('shifts')->state(fn (EmployeeShiftOverride $record): string => $record->resolvedShifts()->pluck('name')->join(' + ') ?: 'Rest day'), TextColumn::make('reason')->limit(50)])
            ->headerActions([CreateAction::make()->label('Override a work date')->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))->using(fn (array $data): EmployeeShiftOverride => $this->saveOverride($data))])
            ->recordActions([EditAction::make()->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))->using(fn (array $data, EmployeeShiftOverride $record): EmployeeShiftOverride => $this->saveOverride($data, $record))])->defaultSort('date', 'desc');
    }

    /** @param array<string, mixed> $data */
    private function saveOverride(array $data, ?EmployeeShiftOverride $record = null): EmployeeShiftOverride
    {
        try {
            $record ??= $this->getOwnerRecord()->shiftOverrides()->make();
            $record->fill($data)->save();

            return $record;
        } catch (ValidationException $exception) {
            $path = $this->getSchema('mountedActionSchema'.$this->getMountedAction()->getNestingIndex())->getStatePath();
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [$path.'.'.$field => $messages])->all());
        }
    }
}
