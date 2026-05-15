<?php

namespace App\Filament\Resources\TextFiles;

use App\Filament\Resources\TextFiles\Pages\CreateTextFile;
use App\Filament\Resources\TextFiles\Pages\ListTextFiles;
use App\Filament\Resources\TextFiles\Schemas\TextFileForm;
use App\Filament\Resources\TextFiles\Tables\TextFilesTable;
use App\Models\TextFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TextFileResource extends Resource
{
    protected static ?string $model = TextFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Text Files';

    protected static ?int $navigationSort = 150;

    public static function form(Schema $schema): Schema
    {
        return TextFileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TextFilesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['jobOrderTask.jobOrder', 'uploader']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTextFiles::route('/'),
            // 'create' => CreateTextFile::route('/create'),
        ];
    }
}
