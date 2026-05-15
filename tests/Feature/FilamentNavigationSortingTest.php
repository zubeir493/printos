<?php

test('filament navigation keeps the intended menu order', function (string $path, int $sort): void {
    expect(file_get_contents(base_path($path)))
        ->toContain("protected static ?int \$navigationSort = {$sort};");
})->with([
    'sales orders first' => ['app/Filament/Resources/SalesOrders/SalesOrderResource.php', 10],
    'job orders next' => ['app/Filament/Resources/JobOrders/JobOrderResource.php', 20],
    'partners after active work' => ['app/Filament/Resources/Partners/PartnerResource.php', 40],
    'stock overview near warehouse work' => ['app/Filament/Pages/StockOverview.php', 65],
    'trial balance starts financial reports' => ['app/Filament/Finance/Pages/TrialBalanceReport.php', 310],
    'users near admin support' => ['app/Filament/Resources/Users/UserResource.php', 900],
    'activity logs after email logs' => ['app/Filament/Resources/ActivityLogs/ActivityLogResource.php', 920],
    'settings last' => ['app/Filament/Pages/Settings.php', 1000],
]);
