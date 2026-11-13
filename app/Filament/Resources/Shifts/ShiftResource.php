<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Models\Shift;
use App\Support\ShiftDisplay;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Shifts';

    protected static ?string $navigationParentItem = 'Attendance';

    protected static ?int $navigationSort = 300;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::shiftFields());
    }

    /** @return array<Section|Grid> */
    private static function shiftFields(): array
    {
        return [
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Shift name')->placeholder('e.g. Production day shift')->maxLength(255)->required()->columnSpanFull(),
                TimePicker::make('start_time')->label('Starts at')->native()->seconds(false)->default('08:00:00')->live()->required()->columnSpan(1),
                TimePicker::make('end_time')->label('Ends at')->native()->seconds(false)->default('16:00:00')->live()->required()->columnSpan(1),
                Toggle::make('_has_break')->label('Include a break')->default(false)->dehydrated(false)->live()->columnSpanFull()
                    ->afterStateHydrated(fn (Toggle $component, ?Shift $record) => $component->state(filled($record?->break_start)))
                    ->afterStateUpdated(function (bool $state, Set $set): void {
                        if (! $state) {
                            $set('break_start', null);
                            $set('break_end', null);
                            $set('break_paid', false);
                        }
                    }),
                TimePicker::make('break_start')->label('Break starts')->native()->seconds(false)->live()->visible(fn (Get $get): bool => (bool) $get('_has_break'))->dehydratedWhenHidden()->required(fn (Get $get): bool => (bool) $get('_has_break'))->columnSpan(1),
                TimePicker::make('break_end')->label('Break ends')->native()->seconds(false)->live()->visible(fn (Get $get): bool => (bool) $get('_has_break'))->dehydratedWhenHidden()->required(fn (Get $get): bool => (bool) $get('_has_break'))->columnSpan(1),
                Toggle::make('break_paid')->label('Count break as paid time')->default(false)->live()->visible(fn (Get $get): bool => (bool) $get('_has_break'))->dehydratedWhenHidden()->columnSpanFull(),
                View::make('filament.resources.shifts.shift-preview')->viewData(fn (Get $get): array => ['preview' => ShiftDisplay::preview(collect(['start_time', 'end_time', '_has_break', 'break_start', 'break_end', 'break_paid'])->mapWithKeys(fn (string $key): array => [$key => $get($key)])->all())])->columnSpanFull(),
            ]),
            Section::make('Punch matching')->compact()->collapsible()->collapsed()->columns(2)->columnSpanFull()->schema([
                TextInput::make('grace_minutes')->label('Arrival grace period')->suffix('min')->integer()->minValue(0)->maxValue(120)->default(0)->required()->columnSpanFull(),
                TextInput::make('capture_before_minutes')->label('Match punches before the start')->suffix('min')->integer()->minValue(0)->maxValue(720)->default(120)->required()->columnSpan(1),
                TextInput::make('capture_after_minutes')->label('Match punches after the end')->suffix('min')->integer()->minValue(0)->maxValue(720)->default(240)->required()->columnSpan(1),
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('start_time')->label('Starts')->formatStateUsing(fn (string $state): string => ShiftDisplay::time($state)),
            TextColumn::make('end_time')->label('Ends')->formatStateUsing(fn (string $state): string => ShiftDisplay::time($state))->description(fn (Shift $record): ?string => $record->is_night_shift ? 'Following day' : null),
            TextColumn::make('expected_minutes')->label('Paid hours')->formatStateUsing(fn (int $state): string => ShiftDisplay::duration($state)),
            TextColumn::make('break_minutes')->label('Break')->formatStateUsing(fn (int $state): string => $state ? ShiftDisplay::duration($state) : 'None')->description(fn (Shift $record): ?string => $record->break_minutes ? ($record->break_paid ? 'Paid' : 'Unpaid') : null),
            TextColumn::make('usage')->label('Usage')->state(fn (Shift $record): string => $record->used_in_schedule || $record->used_in_override || $record->used_as_additional || $record->used_as_additional_override ? 'In use' : 'Available')->badge(),
        ])->recordActions([
            ActionGroup::make([
                EditAction::make()->icon(null)->modalHeading('Edit')->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()
                    ->using(function (Shift $record, array $data, Action $action, Component $livewire): Shift {
                        try {
                            $record->update($data);
                        } catch (ValidationException $exception) {
                            $path = $livewire->getSchema('mountedActionSchema'.$action->getNestingIndex())->getStatePath();
                            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [$path.'.'.$field => $messages])->all());
                        }

                        return $record;
                    }),
                Action::make('duplicate')->authorize(fn (): bool => auth()->user()->can('create', Shift::class))
                    ->modalHeading('Create a shift variant')
                    ->modalSubmitActionLabel('Create variant')->stickyModalHeader()->stickyModalFooter()
                    ->modalWidth('3xl')->fillForm(fn (Shift $record): array => [...$record->fresh()->attributesToArray(), 'name' => $record->name.' (copy)'])->schema(self::shiftFields())
                    ->action(function (Shift $record, array $data): void {
                        $copy = $record->fresh()->replicate()->unsetRelations();
                        $copy->fill($data)->save();
                        Notification::make()->title('Shift variant created')->success()->send();
                    }),
                DeleteAction::make()->icon(null)
                    ->failureNotificationTitle('Shift is in use')
                    ->failureNotificationBody('Remove it from shift assignments before deleting it.')
                    ->using(function (Shift $record): bool {
                        try {
                            return (bool) $record->delete();
                        } catch (QueryException $exception) {
                            if (! in_array($exception->errorInfo[0] ?? null, ['23000', '23503'], true)) {
                                throw $exception;
                            }

                            return false;
                        }
                    }),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShifts::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withExists(['scheduleDays as used_in_schedule' => fn ($query) => $query->whereHas('workSchedule.assignments'), 'shiftOverrides as used_in_override', 'additionalScheduleDays as used_as_additional' => fn ($query) => $query->whereHas('workSchedule.assignments'), 'additionalOverrides as used_as_additional_override']);
    }
}
