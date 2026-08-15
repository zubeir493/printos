<?php

use App\Models\AccountingExport;
use App\Models\JobOrderTask;
use App\Models\User;
use App\Notifications\AccountingExportFailed;
use App\Notifications\DesignerAssignedToTask;
use App\Notifications\TaskSentToProductionNotification;
use App\UserRole;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

test('database notifications also include the web push channel', function () {
    $designer = User::factory()->create();
    $task = JobOrderTask::factory()->create();
    $notification = new DesignerAssignedToTask($task);

    expect($notification)
        ->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($notification->via($designer))
        ->toContain('database')
        ->toContain(WebPushChannel::class)
        ->and($notification->viaConnections())
        ->toMatchArray([
            'database' => 'database',
            WebPushChannel::class => 'database',
        ]);
});

test('accounting export failures are queued after commit and delivered in browser', function () {
    $financeUser = User::factory()->create([
        'role' => UserRole::Finance,
    ]);
    $export = AccountingExport::factory()->create([
        'status' => AccountingExport::STATUS_FAILED,
        'error_message' => 'Peachtree is unavailable.',
    ]);
    $notification = new AccountingExportFailed($export);

    expect($notification)
        ->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($notification->via($financeUser))
        ->toContain('database')
        ->toContain(WebPushChannel::class)
        ->and($notification->viaConnections())
        ->toMatchArray([
            'database' => 'database',
            WebPushChannel::class => 'database',
        ])
        ->and($notification->toWebPush($financeUser, new stdClass)->toArray())
        ->toMatchArray([
            'title' => 'Peachtree Export Failed',
            'body' => 'Peachtree is unavailable.',
            'data' => [
                'url' => route('filament.finance.resources.accounting-exports.view', ['record' => $export]),
            ],
        ]);
});

test('web push notification payload contains a title body and target url', function () {
    $designer = User::factory()->create([
        'role' => UserRole::Design,
    ]);
    $task = JobOrderTask::factory()->create([
        'designer_id' => $designer->id,
        'name' => 'Plate layout',
    ]);

    $message = (new DesignerAssignedToTask($task))->toWebPush($designer, new stdClass);

    expect($message->toArray())
        ->toMatchArray([
            'title' => 'Design Task Assigned',
            'body' => "You have been assigned to task 'Plate layout' for job {$task->jobOrder->job_order_number}.\n",
            'data' => [
                'url' => route('filament.design.resources.job-order-tasks.view', ['record' => $task]),
            ],
        ]);
});

test('production users are notified when a task is sent to production', function () {
    Notification::fake();

    $productionUser = User::factory()->create([
        'role' => UserRole::Production,
    ]);
    $operationsUser = User::factory()->create([
        'role' => UserRole::Operations,
    ]);
    $task = JobOrderTask::factory()->create([
        'status' => 'design',
        'name' => 'Cover print',
    ]);

    $task->update(['status' => 'production']);

    Notification::assertSentTo(
        $productionUser,
        TaskSentToProductionNotification::class,
        fn (TaskSentToProductionNotification $notification): bool => $notification
            ->toWebPush($productionUser, new stdClass)
            ->toArray()['data']['url'] === route('filament.production.resources.job-order-tasks.view', ['record' => $task])
    );
    Notification::assertNotSentTo($operationsUser, TaskSentToProductionNotification::class);
});
