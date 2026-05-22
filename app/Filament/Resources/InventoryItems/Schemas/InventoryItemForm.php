<?php

namespace App\Filament\Resources\InventoryItems\Schemas;

use App\Filament\Support\PanelAccess;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
                        ->live(),
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
                        ->options([
                            'quran' => 'Quran',
                            'hadeeth' => 'Hadeeth',
                            'aqeedah' => 'Aqeedah',
                            'fiqh' => 'Fiqh',
                            'external' => 'External',
                        ])
                        ->hidden(fn ($get) => $get('type') !== 'finished_good')
                        ->required(fn ($get) => $get('type') === 'finished_good')
                        ->default('quran'),
                    TextInput::make('purchase_unit')
                        ->label('Purchase Unit')
                        ->hidden(fn ($get) => $get('type') !== 'raw_material'),
                    TextInput::make('conversion_factor')
                        ->numeric()
                        ->hidden(fn ($get) => $get('type') !== 'raw_material'),
                    TextInput::make('price')
                        ->label('Price / Value')
                        ->helperText(fn ($get) => $get('type') === 'raw_material' && filled($get('purchase_unit'))
                            ? 'Price per '.($get('purchase_unit') ?: 'purchase unit')
                            : 'Selling price for finished goods, or stock value per purchase unit for raw materials.'
                        )
                        ->numeric()
                        ->hidden(fn ($get) => in_array($get('type'), ['tools', 'spare_parts']) || ! PanelAccess::canSeeMoneyValues())
                        ->required(fn ($get) => ! in_array($get('type'), ['tools', 'spare_parts']) && PanelAccess::canSeeMoneyValues())
                        ->suffix('Birr')
                        ->dehydratedWhenHidden(),
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
                        ->disk('s3')
                        ->visibility('private')
                        ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                        ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                        ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                        ->directory('inventory/items')
                        ->previewable(false)
                        ->hiddenOn('view')
                        ->hidden(fn ($get) => $get('type') === 'spare_parts'),
                ])->columnSpan(2),
            ])->columns(6);
    }
}
