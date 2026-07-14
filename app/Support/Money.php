<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

class Money
{
    /**
     * @return array<string, array{name: string, suffix: string}>
     */
    public static function currencies(): array
    {
        return [
            'ETB' => ['name' => 'Ethiopian Birr', 'suffix' => 'Birr'],
            'USD' => ['name' => 'US Dollar', 'suffix' => 'USD'],
            'MYR' => ['name' => 'Malaysian Ringgit', 'suffix' => 'MYR'],
            'SAR' => ['name' => 'Saudi Riyal', 'suffix' => 'SAR'],
            'AED' => ['name' => 'UAE Dirham', 'suffix' => 'AED'],
            'KSH' => ['name' => 'Kenyan Shilling', 'suffix' => 'KSH'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        return collect(self::currencies())
            ->map(fn (array $currency, string $code): string => "{$code} - {$currency['name']}")
            ->all();
    }

    public static function currencyCode(): string
    {
        if (! app()->bound('config')) {
            return 'ETB';
        }

        try {
            if (! Schema::hasTable('settings')) {
                return 'ETB';
            }

            return strtoupper(Setting::getSettings()->currency_code ?: 'ETB');
        } catch (\Throwable) {
            return 'ETB';
        }
    }

    public static function suffix(): string
    {
        return self::currencies()[self::currencyCode()]['suffix'] ?? self::currencyCode();
    }

    public static function label(): string
    {
        return self::currencies()[self::currencyCode()]['name'] ?? self::currencyCode();
    }

    public static function format(float|int|string|null $amount, int $precision = 0): string
    {
        return number_format((float) ($amount ?? 0), $precision).' '.self::suffix();
    }

    public static function abbreviate(float|int|string|null $amount, int $precision): string
    {
        return Number::abbreviate((float) ($amount ?? 0), precision: $precision).' '.self::suffix();
    }
}
