<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;

class PanelAccess
{
    public static function panelId(): ?string
    {
        return Filament::getCurrentOrDefaultPanel()?->getId();
    }

    public static function canAccessWarehouseSection(): bool
    {
        return in_array(self::panelId(), ['admin', 'warehouse'], true);
    }

    public static function canAccessFinanceSection(): bool
    {
        return in_array(self::panelId(), ['admin', 'finance'], true);
    }

    public static function canManageBidBonds(): bool
    {
        return self::panelId() === 'admin';
    }

    public static function canSeeMoneyValues(): bool
    {
        return in_array(self::panelId(), ['admin', 'finance', 'operations'], true);
    }

    public static function canApproveMaterialOverIssues(): bool
    {
        return in_array(self::panelId(), ['admin', 'operations'], true);
    }

    public static function canManageJobOrders(): bool
    {
        return in_array(self::panelId(), ['admin', 'operations'], true);
    }

    public static function canManageJobOrderTasks(): bool
    {
        return in_array(self::panelId(), ['admin', 'operations'], true);
    }

    public static function canManagePartners(): bool
    {
        return in_array(self::panelId(), ['admin', 'operations'], true);
    }

    public static function canManagePurchaseOrders(): bool
    {
        return in_array(self::panelId(), ['admin', 'operations'], true);
    }

    public static function canManageSalesOrders(): bool
    {
        return in_array(self::panelId(), ['admin', 'finance', 'sales', 'retail'], true);
    }
}
