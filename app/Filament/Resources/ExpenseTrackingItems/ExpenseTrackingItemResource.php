<?php

namespace App\Filament\Resources\ExpenseTrackingItems;

use App\Enums\ExpenseTrackingType;
use App\Filament\Resources\ExpenseTrackingItems\Pages\ManageExpenseTrackingItems;
use App\Filament\Support\PanelAccess;
use App\Models\ExpenseTrackingItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpenseTrackingItemResource extends Resource
{
    protected static ?string $model = ExpenseTrackingItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Tracking Item';

    protected static ?string $navigationLabel = 'Tracking Items';

    protected static ?int $navigationSort = 214;

    public static function canViewAny(): bool
    {
        return PanelAccess::canAccessFinanceSection();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(ExpenseTrackingType::trackingItemOptions())
                    ->required()
                    ->searchable(),
                TextInput::make('code')
                    ->maxLength(255),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->options(ExpenseTrackingItem::statusOptions())
                    ->default(ExpenseTrackingItem::STATUS_ACTIVE)
                    ->required(),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ExpenseTrackingType::tryFrom($state)?->label() ?? str($state)->headline()->toString())
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === ExpenseTrackingItem::STATUS_ACTIVE ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ExpenseTrackingType::trackingItemOptions()),
                SelectFilter::make('status')
                    ->options(ExpenseTrackingItem::statusOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenseTrackingItems::route('/'),
        ];
    }
}
