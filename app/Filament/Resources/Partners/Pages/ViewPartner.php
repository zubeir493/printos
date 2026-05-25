<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class ViewPartner extends ViewRecord
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('statement')
                ->label('Statement')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color(Color::Indigo)
                ->url(fn () => static::getResource()::getUrl('statement', ['record' => $this->record])),
            EditAction::make(),
        ];
    }
}
