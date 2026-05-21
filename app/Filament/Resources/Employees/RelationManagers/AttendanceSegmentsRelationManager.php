<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Services\Hr\RebuildAttendanceDailySummaries;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceSegmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceSegments';

    protected static ?string $title = 'Attendance / Punch Logs';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->required(),
            TextInput::make('fp_no')->label('FP No')->required(),
            TextInput::make('schedule_name')->label('Schedule'),
            TimePicker::make('clock_in')->seconds(false),
            TimePicker::make('clock_out')->seconds(false),
            TextInput::make('late_minutes')->numeric()->default(0),
            TextInput::make('early_minutes')->numeric()->default(0),
            TextInput::make('worked_minutes')->numeric()->default(0),
            TextInput::make('overtime_minutes')->numeric()->default(0),
            TextInput::make('day_fraction')->numeric()->default(0),
            Select::make('status')->options([
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('schedule_name')->label('Schedule'),
                TextColumn::make('clock_in'),
                TextColumn::make('clock_out'),
                TextColumn::make('late_minutes')->numeric(),
                TextColumn::make('early_minutes')->numeric(),
                TextColumn::make('worked_minutes')->numeric(),
                TextColumn::make('overtime_minutes')->numeric(),
                TextColumn::make('status')->badge(),
                TextColumn::make('correction_reason')->label('Reason')->limit(32),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date))
                        ->when($data['until'], fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date))),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['fp_no'] ??= $this->getOwnerRecord()->attendance_device_id;

                        return $data;
                    })
                    ->after(fn ($record) => app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$record]))),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn ($record) => app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$record]))),
            ])
            ->defaultSort('date', 'desc');
    }
}
