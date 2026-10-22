<?php

namespace App\Support;

class PaymentMethod
{
    /** @return list<string> */
    public static function bankMethods(): array
    {
        return ['bank', 'bank_transfer', 'cheque', 'check', 'cpo'];
    }

    public static function requiresBank(?string $method): bool
    {
        return in_array($method, self::bankMethods(), true);
    }
}
