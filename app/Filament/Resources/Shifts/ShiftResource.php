<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Models\Shift;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationParentItem = 'Attendance';

    protected static ?int $navigationSort = 300;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TimePicker::make('start_time')->seconds(false)->required(),
            TimePicker::make('end_time')->seconds(false)->required(),
            TextInput::make('break_minutes')->numeric()->default(0),
            TextInput::make('grace_minutes')->numeric()->default(0),
            TextInput::make('expected_minutes')->numeric()->default(480),
            Toggle::make('is_night_shift'),
            Toggle::make('overtime_eligible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('start_time'),
            TextColumn::make('end_time'),
            TextColumn::make('expected_minutes')->numeric(),
            IconColumn::make('is_night_shift')->boolean(),
            IconColumn::make('overtime_eligible')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShifts::route('/'),
        ];
    }
}
