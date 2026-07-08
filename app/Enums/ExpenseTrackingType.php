<?php

namespace App\Enums;

enum ExpenseTrackingType: string
{
    case NONE = 'none';
    case VEHICLE = 'vehicle';
    case DEPARTMENT = 'department';
    case PROJECT = 'project';
    case EMPLOYEE = 'employee';
    case BID = 'bid';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'No tracking',
            self::VEHICLE => 'Vehicle',
            self::DEPARTMENT => 'Department',
            self::PROJECT => 'Project',
            self::EMPLOYEE => 'Employee',
            self::BID => 'Bid',
            self::OTHER => 'Other',
        };
    }

    public function usesTrackingItem(): bool
    {
        return in_array($this, [
            self::VEHICLE,
            self::DEPARTMENT,
            self::PROJECT,
            self::OTHER,
        ], true);
    }

    public function usesEmployee(): bool
    {
        return $this === self::EMPLOYEE;
    }

    public function usesBid(): bool
    {
        return $this === self::BID;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function trackingItemOptions(): array
    {
        return collect([
            self::VEHICLE,
            self::DEPARTMENT,
            self::PROJECT,
            self::OTHER,
        ])
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
