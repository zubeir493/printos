<?php

namespace App\Filament\Resources\AttendanceSegments;

use App\Filament\Resources\AttendanceSegments\Pages\ManageAttendanceSegments;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceSegmentResource extends Resource
{
    protected static ?string $model = AttendanceSegment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $modelLabel = 'attendance entry';

    protected static ?string $pluralModelLabel = 'Attendance';

    protected static ?int $navigationSort = 311;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->relationship('employee', 'first_name')
                ->searchable()
                ->required(),
            DatePicker::make('date')->required(),
            TextInput::make('fp_no')->label('FP No')->required(),
            TextInput::make('schedule_name')->label('Source schedule')->disabled(),
            TimePicker::make('clock_in')->seconds(false),
            TimePicker::make('clock_out')->seconds(false),
            Select::make('status')
                ->options([
                    'Present' => 'Present',
                    'Absent' => 'Absent',
                    'Late' => 'Late',
                    'Early' => 'Early',
                    'Holiday' => 'Holiday',
                    'Dayoff' => 'Dayoff',
                ]),
            TextInput::make('exception'),
            Textarea::make('correction_reason')
                ->label('Manual edit reason')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.first_name')->label('Employee')
                    ->formatStateUsing(fn (AttendanceSegment $record): ?string => $record->employee?->full_name)
                    ->searchable(['first_name', 'last_name', 'employee_id', 'attendance_device_id'])->sortable(),
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('schedule_name')->label('Schedule')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clock_in')->label('In')->formatStateUsing(fn (?string $state): ?string => $state ? substr($state, 0, 5) : null),
                TextColumn::make('clock_out')->label('Out')->formatStateUsing(fn (?string $state): ?string => $state ? substr($state, 0, 5) : null)
                    ->description(fn (AttendanceSegment $record): ?string => isset($record->raw_data['estimated_checkout']) ? 'Estimated' : null),
                TextColumn::make('late_minutes')->label('Late (min)')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('early_minutes')->label('Early (min)')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('worked_minutes')->label('Worked')->formatStateUsing(fn (int $state): string => self::duration($state)),
                TextColumn::make('overtime_minutes')->label('Overtime')->formatStateUsing(fn (int $state): string => self::duration($state)),
                TextColumn::make('status')->badge(),
                TextColumn::make('correction_reason')->label('Reason')->limit(32)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->searchPlaceholder('Search name, employee ID or FP number')
            ->filters([
                SelectFilter::make('employee')->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record): string => $record->full_name.' ('.$record->employee_id.')')
                    ->searchable(['first_name', 'last_name', 'employee_id', 'attendance_device_id']),
                SelectFilter::make('status')
                    ->options([
                        'Present' => 'Present',
                        'Absent' => 'Absent',
                        'Late' => 'Late',
                        'Early' => 'Early',
                        'Holiday' => 'Holiday',
                        'Dayoff' => 'Dayoff',
                    ])
                    ->multiple()
                    ->selectablePlaceholder(false),
                DateRangeFilter::make('date_range', 'date', 'Date'),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn ($record) => app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$record]))),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee']);
    }

    private static function duration(int $minutes): string
    {
        return $minutes >= 60 ? intdiv($minutes, 60).'h '.($minutes % 60).'m' : $minutes.'m';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceSegments::route('/'),
        ];
    }
}
