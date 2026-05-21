<?php

namespace App\Filament\Resources\WorkSchedules;

use App\Filament\Resources\WorkSchedules\Pages\ManageWorkSchedules;
use App\Models\Employee;
use App\Models\WorkSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class WorkScheduleResource extends Resource
{
    protected static ?string $model = WorkSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 301;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Toggle::make('is_default'),
            Repeater::make('days')
                ->relationship()
                ->table([
                    TableColumn::make('Day')->alignLeft(),
                    TableColumn::make('Shift')->alignLeft(),
                    TableColumn::make('Working Day?')->alignLeft(),
                ])
                ->compact()
                ->schema([
                    Select::make('day_of_week')
                        ->options([
                            0 => 'Sunday',
                            1 => 'Monday',
                            2 => 'Tuesday',
                            3 => 'Wednesday',
                            4 => 'Thursday',
                            5 => 'Friday',
                            6 => 'Saturday',
                        ])
                        ->required(),
                    Select::make('shift_id')
                        ->relationship('shift', 'name'),
                    Toggle::make('is_working_day')->default(true),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->addable(false)
                ->cloneable(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                IconColumn::make('is_default')->boolean(),
                TextColumn::make('days_count')->counts('days')->label('Days'),
            ])
            ->recordActions([
                Action::make('assignEmployees')
                    ->label('Assign Employees')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Select::make('employee_ids')
                            ->label('Employees')
                            ->options(fn () => Employee::query()
                                ->whereRaw('LOWER(status) = ?', ['active'])
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->full_name])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->required(),
                        DatePicker::make('effective_from')
                            ->required()
                            ->default(now()),
                        DatePicker::make('effective_until'),
                    ])
                    ->action(function (WorkSchedule $record, array $data): void {
                        foreach ($data['employee_ids'] as $employeeId) {
                            $record->assignments()->updateOrCreate(
                                [
                                    'employee_id' => $employeeId,
                                    'effective_from' => $data['effective_from'],
                                ],
                                [
                                    'effective_until' => $data['effective_until'] ?? null,
                                ],
                            );
                        }

                        Notification::make()
                            ->title('Schedule assigned')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkSchedules::route('/'),
        ];
    }
}
