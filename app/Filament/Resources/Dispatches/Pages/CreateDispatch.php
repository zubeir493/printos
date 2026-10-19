<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Services\SaveDispatch;
use Illuminate\Validation\ValidationException;

class CreateDispatch extends CreateRecord
{
    protected static string $resource = DispatchResource::class;

    protected static bool $canCreateAnother = false;

    protected ?bool $hasDatabaseTransactions = true;

    protected float $quantity = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->quantity = (float) ($data['quantity'] ?? 0);
        if ($this->quantity <= 0) {
            throw ValidationException::withMessages(['data.quantity' => 'Enter a positive delivery quantity.']);
        }
        unset($data['quantity']);
        $data['status'] = 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SaveDispatch::class)->handle($this->record, $this->quantity);
    }
}
