<?php

namespace App\Filament\Resources\Bids\Schemas;

use App\Models\Bid;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BidForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bid Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('tender_reference')
                                    ->label('Tender / Reference #')
                                    ->maxLength(255),
                                Select::make('partner_id')
                                    ->label('Procuring Entity')
                                    ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('phone'),
                                        TextInput::make('email')->email(),
                                        TextInput::make('tin_number'),
                                        Hidden::make('is_customer')->default(true),
                                    ])
                                    ->required(),
                                TextInput::make('estimated_value')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('Birr')
                                    ->required(),
                                DatePicker::make('deadline_date')
                                    ->label('Closing Date'),
                                TextInput::make('bid_bond_amount')
                                    ->label('Bid Amount')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('status')
                                    ->formatStateUsing(fn (?string $state): string => Bid::statusOptions()[$state ?? Bid::STATUS_DRAFT] ?? 'Draft')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                        Textarea::make('notes')
                            ->label('Notes / Summary')
                            ->columnSpanFull(),
                        FileUpload::make('bid_files')
                            ->label('Bid Files')
                            ->disk(config('filesystems.private_disk', 's3'))
                            ->visibility('private')
                            ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                            ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                            ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                            ->directory('bids')
                            ->multiple()
                            ->reorderable()
                            ->preserveFilenames()
                            ->previewable(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
