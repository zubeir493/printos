<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Payments\PaymentResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                str_contains($exception->getMessage(), 'Withholding') ? 'data.withholding_amount' : 'data.bank_id' => $exception->getMessage(),
            ]);
        }
    }
}
