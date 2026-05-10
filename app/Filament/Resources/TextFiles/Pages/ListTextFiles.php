<?php

namespace App\Filament\Resources\TextFiles\Pages;

use App\Filament\Resources\TextFiles\TextFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTextFiles extends ListRecords
{
    protected static string $resource = TextFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
