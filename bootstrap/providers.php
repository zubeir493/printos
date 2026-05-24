<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\DesignPanelProvider;
use App\Providers\Filament\FinancePanelProvider;
use App\Providers\Filament\HrPanelProvider;
use App\Providers\Filament\OperationsPanelProvider;
use App\Providers\Filament\ProductionPanelProvider;
use App\Providers\Filament\RetailPanelProvider;
use App\Providers\Filament\SalesPanelProvider;
use App\Providers\Filament\TypistPanelProvider;
use App\Providers\Filament\WarehousePanelProvider;
use App\Providers\MailtrapServiceProvider;

return [
    AppServiceProvider::class,
    MailtrapServiceProvider::class,
    AdminPanelProvider::class,
    DesignPanelProvider::class,
    FinancePanelProvider::class,
    HrPanelProvider::class,
    OperationsPanelProvider::class,
    ProductionPanelProvider::class,
    TypistPanelProvider::class,
    RetailPanelProvider::class,
    SalesPanelProvider::class,
    WarehousePanelProvider::class,
];
