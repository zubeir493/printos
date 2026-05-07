<?php

namespace App;

use Filament\Panel;

enum UserRole: string
{
    case Admin = 'admin';
    case Design = 'design';
    case Production = 'production';
    case Finance = 'finance';
    case Sales = 'sales';
    case Warehouse = 'warehouse';
    case HR = 'hr';
    case Retail = 'retail';
    case Operations = 'operations';

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this === self::Admin) {
            return true;
        }

        return match ($panel->getId()) {
            'design' => $this === self::Design,
            'production' => $this === self::Production,
            'finance' => $this === self::Finance,
            'sales' => $this === self::Sales,
            'warehouse' => $this === self::Warehouse,
            'hr' => $this === self::HR,
            'retail' => $this === self::Retail,
            'operations' => $this === self::Operations,
            default => false,
        };
    }

    public function getRedirectPath(): string
    {
        return match ($this) {
            self::Admin => '/',
            self::Design => '/design',
            self::Production => '/production',
            self::Finance => '/finance',
            self::Sales => '/sales',
            self::Warehouse => '/warehouse',
            self::HR => '/hr',
            self::Retail => '/retail',
            self::Operations => '/operations',
        };
    }
}
