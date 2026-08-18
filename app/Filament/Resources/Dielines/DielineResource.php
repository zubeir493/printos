<?php

namespace App\Filament\Resources\Dielines;

use App\Filament\Resources\Dielines\Pages\EditDieline;
use App\Filament\Resources\Dielines\Pages\ListDielines;
use App\Filament\Resources\Dielines\Schemas\DielineForm;
use App\Filament\Resources\Dielines\Tables\DielinesTable;
use App\Models\Dieline;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DielineResource extends Resource
{
    protected static ?string $model = Dieline::class;

    //To be removed once calculator is finished
    // protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    public static function form(Schema $schema): Schema
    {
        return DielineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DielinesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['creator', 'jobOrderTask.jobOrder']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDielines::route('/'),
            'edit' => EditDieline::route('/{record}/edit'),
        ];
    }
}
