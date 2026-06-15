<?php

namespace App\Filament\Resources\Bids\Pages;

use App\Filament\Resources\Bids\BidResource;
use App\Filament\Resources\Bids\Pages\Concerns\InteractsWithBidActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewBid extends ViewRecord
{
    use InteractsWithBidActions;

    protected static string $resource = BidResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->bid_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->sendBidBondAction(),
            $this->returnBidBondAction(),
            $this->sendPerformanceBondAction(),
            $this->returnPerformanceBondAction(),
            $this->submitBidAction(),
            $this->awardBidAction(),
            $this->loseBidAction(),
            EditAction::make(),
        ];
    }
}
