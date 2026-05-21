<?php

namespace App\Filament\Resources\LeaveTypes;

use App\Filament\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\LeaveType;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Leave';

    protected static ?int $navigationSort = 329;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Toggle::make('is_paid')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            IconColumn::make('is_paid')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveTypes::route('/'),
        ];
    }
}
