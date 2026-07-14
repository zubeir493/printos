<?php

namespace App\Filament\Support;

use App\Filament\Resources\Bids\BidResource;
use App\Filament\Resources\Bids\RelationManagers\BidBondsRelationManager;
use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\RelationManagers\CostEstimateLinesRelationManager;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\RelationManagers\AttendanceSegmentsRelationManager;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrders\RelationManagers\JobOrderArtworksRelationManager;
use App\Filament\Resources\JobOrders\RelationManagers\MaterialsOverviewRelationManager;
use App\Filament\Resources\JobOrders\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Filament\Resources\JobOrderTasks\RelationManagers\ArtworksRelationManager;
use App\Filament\Resources\JobOrderTasks\RelationManagers\MaterialRequestsRelationManager;
use App\Filament\Resources\JobOrderTasks\RelationManagers\TextFilesRelationManager;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Partners\RelationManagers\JobOrdersRelationManager;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayrollRunEmployeesRelationManager;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\RelationManagers\GoodsReceiptsRelationManager;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\Warehouses\RelationManagers\StockMovementsRelationManager;
use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;

class RelationManagerToolbarTabs
{
    /**
     * @var array<class-string, array<class-string>>
     */
    private const RESOURCE_RELATION_MANAGERS = [
        BidResource::class => [
            BidBondsRelationManager::class,
        ],
        CostEstimateResource::class => [
            CostEstimateLinesRelationManager::class,
        ],
        EmployeeResource::class => [
            AttendanceSegmentsRelationManager::class,
        ],
        JobOrderResource::class => [
            MaterialsOverviewRelationManager::class,
            JobOrderArtworksRelationManager::class,
            PaymentsRelationManager::class,
        ],
        JobOrderTaskResource::class => [
            ArtworksRelationManager::class,
            TextFilesRelationManager::class,
            MaterialRequestsRelationManager::class,
        ],
        PartnerResource::class => [
            JobOrdersRelationManager::class,
        ],
        PayrollRunResource::class => [
            PayrollRunEmployeesRelationManager::class,
        ],
        PurchaseOrderResource::class => [
            GoodsReceiptsRelationManager::class,
            \App\Filament\Resources\PurchaseOrders\RelationManagers\PaymentsRelationManager::class,
        ],
        SalesOrderResource::class => [
            \App\Filament\Resources\SalesOrders\RelationManagers\PaymentsRelationManager::class,
        ],
        WarehouseResource::class => [
            StockMovementsRelationManager::class,
        ],
    ];

    /**
     * @return array<class-string>
     */
    public static function renderHookScopes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::RESOURCE_RELATION_MANAGERS))));
    }

    /**
     * @return array<int, array{key: string, label: string, manager: class-string}>
     */
    public static function tabsForManager(?string $activeManager): array
    {
        $resource = self::resourceForManager($activeManager);

        if (! $resource) {
            return [];
        }

        return self::tabsForResource($resource);
    }

    /**
     * @param  class-string  $resource
     * @return array<int, array{key: string, label: string, manager: class-string}>
     */
    public static function tabsForResource(string $resource): array
    {
        if (! method_exists($resource, 'getRelations')) {
            return [];
        }

        return collect($resource::getRelations())
            ->map(function (string|RelationManagerConfiguration $relation, int|string $key): ?array {
                $manager = $relation instanceof RelationManagerConfiguration
                    ? $relation->relationManager
                    : $relation;

                if (! is_subclass_of($manager, RelationManager::class)) {
                    return null;
                }

                return [
                    'key' => (string) $key,
                    'label' => $manager::getRelationshipTitle(),
                    'manager' => $manager,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function resourceForManager(?string $activeManager): ?string
    {
        if (! $activeManager) {
            return null;
        }

        foreach (self::RESOURCE_RELATION_MANAGERS as $resource => $managers) {
            if (in_array($activeManager, $managers, strict: true)) {
                return $resource;
            }
        }

        return null;
    }
}
