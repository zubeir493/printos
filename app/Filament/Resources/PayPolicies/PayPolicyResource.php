<?php

namespace App\Filament\Resources\PayPolicies;

use App\Filament\Resources\PayPolicies\Pages\ManagePayPolicies;
use App\Models\PayPolicy;
use Filament\Resources\Resource;

class PayPolicyResource extends Resource
{
    protected static ?string $model = PayPolicy::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => ManagePayPolicies::route('/')];
    }
}
