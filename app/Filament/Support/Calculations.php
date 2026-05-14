<?php

namespace App\Filament\Support;

use App\Models\Setting;

class Calculations
{
    /**
     * Recalculates the total for a repeater based on a quantity and unit price field.
     */
    public static function updateSubtotal($get, $set, $repeaterField, $subtotalField, $qtyField = 'quantity', $priceField = 'unit_price')
    {
        $items = $get($repeaterField) ?? [];

        $subtotal = collect($items)->reduce(function ($carry, $item) use ($qtyField, $priceField) {
            $qty = (float) ($item[$qtyField] ?? 0);
            $price = (float) ($item[$priceField] ?? 0);

            return $carry + ($qty * $price);
        }, 0);

        $set($subtotalField, $subtotal);
    }

    /**
     * Recalculates the total for a repeater by summing up a specific field.
     */
    public static function sumRepeater($get, $set, $repeaterField, $targetField, $sumField)
    {
        $items = $get($repeaterField) ?? [];
        $total = collect($items)->sum(fn ($item) => (float) ($item[$sumField] ?? 0));
        $set($targetField, $total);
    }

    public static function updateTaxedTotal($get, $set, string $subtotalField, string $taxField, string $totalField): void
    {
        $subtotal = (float) $get($subtotalField);
        $settings = Setting::getSettings();
        $taxRate = $settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0.0;
        $tax = round($subtotal * $taxRate, 2);

        $set($taxField, $tax);
        $set($totalField, $subtotal + $tax);
    }
}
