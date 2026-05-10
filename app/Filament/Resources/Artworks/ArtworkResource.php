<?php

namespace App\Filament\Resources\Artworks;

use App\Filament\Resources\Artworks\Pages\ListArtworks;
use App\Filament\Resources\Artworks\Schemas\ArtworkForm;
use App\Filament\Resources\Artworks\Tables\ArtworksTable;
use App\Models\Artwork;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArtworkResource extends Resource
{
    protected static ?string $model = Artwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderArrowDown;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ArtworkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArtworksTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['jobOrder', 'uploader']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArtworks::route('/'),
        ];
    }
}
