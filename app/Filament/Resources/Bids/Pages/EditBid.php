<?php

namespace App\Filament\Resources\Bids\Pages;

use App\Filament\Resources\Bids\BidResource;
use App\Filament\Resources\Bids\Pages\Concerns\InteractsWithBidActions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditBid extends EditRecord
{
    use InteractsWithBidActions;

    protected static string $resource = BidResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->getRecord()->bid_number;
    }

    protected function getHeaderActions(): array
    {
        return $this->bidHeaderActions(includeDelete: true);
    }
}
