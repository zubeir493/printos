<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\EmployeeWorkAssignment;
use App\Models\Shift;
use App\Services\Hr\AssignEmployeeWork;
use App\Services\Hr\EndEmployeeWorkAssignment;
use App\Services\Hr\ResolveAssignmentPattern;
use App\Services\Hr\UpdateEmployeeWorkAssignment;
use App\Support\ShiftDisplay;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'workAssignments';

    protected static ?string $title = 'Shift assignments';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('work_schedule_id'),
            Repeater::make('days')->hiddenLabel()->columnSpanFull()
                ->table([TableColumn::make('Day')->width('25%'), TableColumn::make('Shifts')])
                ->schema([
                    Hidden::make('day_of_week')->required()->rules(['integer', 'between:0,6']),
                    Placeholder::make('_day')->hiddenLabel()->content(fn (Get $get): string => self::weekdays()[(int) $get('day_of_week')] ?? ''),
                    Select::make('shift_ids')->multiple()->hiddenLabel()->searchable()->placeholder('Rest')
                        ->options(fn (): array => once(fn (): array => Shift::orderBy('start_time')->get()->mapWithKeys(fn (Shift $shift): array => [$shift->id => $shift->name.' ('.ShiftDisplay::time($shift->start_time).' - '.ShiftDisplay::time($shift->end_time).')'])->all())),
                ])->default(fn (): array => self::defaultWeek())
                ->afterStateHydrated(function (Repeater $component, ?EmployeeWorkAssignment $record): void {
                    if ($record) {
                        $component->state($record->workSchedule->load('days.shift', 'days.additionalShifts')->days->map(fn ($day): array => ['day_of_week' => $day->day_of_week, 'shift_ids' => $day->shift_ids])->all());
                    }
                })->addable(false)->deletable(false)->reorderable(false)->minItems(7)->maxItems(7),
            Grid::make(2)->columnSpanFull()->schema([
                DatePicker::make('effective_from')->label('From')->default(today())->required(),
                DatePicker::make('effective_to')->label('Until')->afterOrEqual('effective_from')->placeholder('Ongoing'),
            ]),
            Textarea::make('reason')->placeholder('Optional')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('workSchedule.days.shift', 'workSchedule.days.additionalShifts'))->columns([
            TextColumn::make('assignment_status')->label('Status')->state(fn (EmployeeWorkAssignment $record): string => $record->effective_from->gt(today()) ? 'Upcoming' : (($record->effective_to && $record->effective_to->lt(today())) ? 'Ended' : 'Current'))->badge()->color(fn (string $state): string => match ($state) {
                'Current' => 'success', 'Upcoming' => 'info', default => 'gray'
            }),
            TextColumn::make('shifts')->state(fn (EmployeeWorkAssignment $record): string => $record->workSchedule->days->flatMap(fn ($day) => $day->resolvedShifts()->pluck('name'))->unique()->join(' / ')),
            TextColumn::make('effective_from')->date(), TextColumn::make('effective_to')->date()->placeholder('Ongoing'), TextColumn::make('reason')->limit(40),
        ])->headerActions([
            CreateAction::make()->label('Assign shifts')->modalHeading('Assign shifts')->modalWidth('3xl')->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
                ->using(fn (array $data): EmployeeWorkAssignment => $this->saveAssignment(fn (): EmployeeWorkAssignment => app(AssignEmployeeWork::class)->handleCompanyRules($this->getOwnerRecord(), app(ResolveAssignmentPattern::class)->handle($data['days'])->id, $data['effective_from'], $data['reason'] ?? null, ($data['effective_to'] ?? null) ?: null))),
        ])->recordActions([
            EditAction::make()->label('Edit')->modalHeading('Edit work assignment')->modalWidth('3xl')->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
                ->using(fn (EmployeeWorkAssignment $record, array $data): EmployeeWorkAssignment => $this->saveAssignment(fn (): EmployeeWorkAssignment => app(UpdateEmployeeWorkAssignment::class)->handle($record, [...$data, 'work_schedule_id' => app(ResolveAssignmentPattern::class)->handle($data['days'], $record->work_schedule_id)->id]))),
            Action::make('endAssignment')->label('End assignment')->color('danger')->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
                ->schema([])
                ->visible(fn (EmployeeWorkAssignment $record): bool => $record->effective_from->lte(today()) && (! $record->effective_to || $record->effective_to->gt(today())))
                ->requiresConfirmation()->modalHeading('End this assignment today?')
                ->modalSubmitActionLabel('End assignment today')->action(function (EmployeeWorkAssignment $record, Action $action): void {
                    try {
                        app(EndEmployeeWorkAssignment::class)->handle($record);
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Assignment could not be ended')->body(collect($exception->errors())->flatten()->first())->danger()->send();
                        $action->halt();
                    }
                    Notification::make()->title('Assignment ends today')->success()->send();
                }),
        ])
            ->defaultSort('effective_from', 'desc');
    }

    /** @return array<int, string> */
    private static function weekdays(): array
    {
        return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
    }

    /** @return array<int, array{day_of_week: int, shift_ids: array<int, int>}> */
    private static function defaultWeek(): array
    {
        $shifts = Shift::orderBy('start_time')->get();
        $full = $shifts->first(fn (Shift $shift): bool => $shift->expected_minutes >= 480 && ! $shift->is_night_shift);
        $half = $shifts->first(fn (Shift $shift): bool => $shift->expected_minutes > 0 && $shift->expected_minutes <= 300 && ! $shift->is_night_shift);

        return collect(array_keys(self::weekdays()))->map(fn (int $day): array => ['day_of_week' => $day, 'shift_ids' => $day === 0 ? [] : array_values(array_filter([$day === 6 ? $half?->id : $full?->id]))])->all();
    }

    /** @param Closure(): EmployeeWorkAssignment $save */
    private function saveAssignment(Closure $save): EmployeeWorkAssignment
    {
        try {
            return DB::transaction($save);
        } catch (ValidationException $exception) {
            $path = $this->getSchema('mountedActionSchema'.$this->getMountedAction()->getNestingIndex())->getStatePath();
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [$path.'.'.match ($field) {
                'work_schedule_id' => 'days', 'pay_policy_id' => 'effective_from', default => $field
            } => $messages])->all());
        }
    }
}
