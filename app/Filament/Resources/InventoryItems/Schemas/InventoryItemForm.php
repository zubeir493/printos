<?php

namespace App\Filament\Resources\InventoryItems\Schemas;

use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InventoryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Select::make('type')
                        ->options([
                            'raw_material' => 'Raw Material',
                            'finished_good' => 'Finished Good',
                            'tools' => 'Tools',
                            'spare_parts' => 'Spare Parts',
                        ])
                        ->required()
                        ->default('raw_material')
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('category', $state === 'finished_good' ? 'quran' : 'paper')),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('sku')
                        ->required()
                        ->unique(ignoreRecord: true),
                    TextInput::make('unit')
                        ->label('Base Unit'),
                    Select::make('category')
                        ->label('Category')
                        ->options(fn ($get): array => self::categoryOptions($get('type')))
                        ->hidden(fn ($get) => ! in_array($get('type'), ['raw_material', 'finished_good'], true))
                        ->required(fn ($get) => in_array($get('type'), ['raw_material', 'finished_good'], true))
                        ->default(fn ($get): string => $get('type') === 'finished_good' ? 'quran' : 'paper')
                        ->live(),
                    TextInput::make('purchase_unit')
                        ->label('Purchase Unit')
                        ->hidden(fn ($get) => $get('type') !== 'raw_material'),
                    TextInput::make('conversion_factor')
                        ->numeric()
                        ->hidden(fn ($get) => $get('type') !== 'raw_material'),
                    TextInput::make('price')
                        ->label(fn ($get) => $get('type') === 'raw_material' && filled($get('purchase_unit'))
                            ? 'Price per '.($get('purchase_unit') ?: 'purchase unit')
                            : 'Selling price')
                        ->numeric()
                        ->hidden(fn ($get) => in_array($get('type'), ['tools', 'spare_parts']) || ! PanelAccess::canSeeMoneyValues())
                        ->required(fn ($get) => ! in_array($get('type'), ['tools', 'spare_parts']) && PanelAccess::canSeeMoneyValues())
                        ->suffix('Birr')
                        ->dehydratedWhenHidden(),
                    TextInput::make('gsm')
                        ->label('GSM')
                        ->numeric()
                        ->hidden(fn ($get) => ! self::isPaperLike($get('type'), $get('category'))),
                    TextInput::make('width')
                        ->numeric()
                        ->suffix('cm')
                        ->hidden(fn ($get) => ! self::isPaperLike($get('type'), $get('category'))),
                    TextInput::make('height')
                        ->numeric()
                        ->suffix('cm')
                        ->hidden(fn ($get) => ! self::isPaperLike($get('type'), $get('category'))),
                    TextInput::make('default_waste_percent')
                        ->numeric()
                        ->suffix('%')
                        ->default(5)
                        ->hidden(fn ($get) => $get('type') !== 'raw_material'),
                    Toggle::make('is_sellable')
                        ->label('Is Sellable')
                        ->hidden(fn ($get) => in_array($get('type'), ['tools', 'spare_parts']))
                        ->default(false),
                ])->columnSpan(4)->columns(2),
                Group::make([
                    FileUpload::make('image')
                        ->image()
                        ->imageEditor()
                        ->imageAspectRatio('1:1')
                        ->maxSize(1024)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('s3')
                        ->visibility('private')
                        ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                        ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                        ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                        ->directory('inventory/items')
                        ->previewable(false)
                        ->hiddenOn('view'),
                ])->columnSpan(2),
            ])->columns(6);
    }

    private static function categoryOptions(?string $type): array
    {
        return match ($type) {
            'raw_material' => InventoryItem::RAW_MATERIAL_CATEGORIES,
            'finished_good' => InventoryItem::FINISHED_GOOD_CATEGORIES,
            default => [],
        };
    }

    private static function isPaperLike(?string $type, ?string $category): bool
    {
        return $type === 'raw_material' && in_array($category, ['paper', 'board'], true);
    }
}
