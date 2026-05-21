<?php

namespace App\Filament\Resources\Holidays;

use App\Filament\Resources\Holidays\Pages\ManageHolidays;
use App\Models\Holiday;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 302;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            DatePicker::make('date')->required()->unique(ignoreRecord: true),
            Select::make('type')
                ->options([
                    'public' => 'Public',
                    'company' => 'Company',
                    'one_off' => 'One-off',
                ])
                ->default('public')
                ->required(),
            Toggle::make('is_paid')->default(true),
            Toggle::make('counts_as_holiday_overtime')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('date')->date()->sortable(),
            TextColumn::make('type')->badge(),
            IconColumn::make('is_paid')->boolean(),
            IconColumn::make('counts_as_holiday_overtime')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHolidays::route('/'),
        ];
    }
}
