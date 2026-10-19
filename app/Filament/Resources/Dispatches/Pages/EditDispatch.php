<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Services\SaveDispatch;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDispatch extends EditRecord
{
    protected static string $resource = DispatchResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected float $quantity = 0;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['quantity'] = $this->record->dispatchItems()->sum('quantity');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->quantity = (float) ($data['quantity'] ?? 0);
        unset($data['quantity']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(SaveDispatch::class)->handle($this->record, $this->quantity);
    }
}
