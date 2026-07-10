<?php

use App\Models\ProductionPlan;
use App\Models\ProductionReport;
use App\Models\User;
use App\Notifications\ProductionPlanApprovedNotification;
use App\Notifications\ProductionReportGeneratedNotification;
use App\Notifications\ProductionReportSubmittedNotification;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('production users are notified when a production plan is approved', function (): void {
    Notification::fake();

    $productionUser = User::factory()->create([
        'role' => UserRole::Production,
    ]);
    $plan = ProductionPlan::create([
        'week_start' => now()->startOfWeek(),
        'week_end' => now()->endOfWeek(),
        'status' => 'draft',
    ]);

    $plan->update(['status' => 'approved']);

    Notification::assertSentTo(
        $productionUser,
        ProductionPlanApprovedNotification::class,
        fn (ProductionPlanApprovedNotification $notification): bool => $notification
            ->toWebPush($productionUser, new stdClass)
            ->toArray()['data']['url'] === route('filament.production.resources.production-plans.view', ['record' => $plan])
    );
});

test('operations users are notified when production reports are generated and submitted', function (): void {
    Notification::fake();

    $operationsUser = User::factory()->create([
        'role' => UserRole::Operations,
    ]);
    $plan = ProductionPlan::create([
        'week_start' => now()->startOfWeek(),
        'week_end' => now()->endOfWeek(),
        'status' => 'approved',
    ]);

    $report = ProductionReport::create([
        'production_plan_id' => $plan->id,
        'status' => 'draft',
    ]);

    Notification::assertSentTo(
        $operationsUser,
        ProductionReportGeneratedNotification::class,
        fn (ProductionReportGeneratedNotification $notification): bool => $notification
            ->toWebPush($operationsUser, new stdClass)
            ->toArray()['data']['url'] === route('filament.operations.resources.production-reports.view', ['record' => $report])
    );

    $report->update(['status' => 'submitted']);

    Notification::assertSentTo($operationsUser, ProductionReportSubmittedNotification::class);
});
