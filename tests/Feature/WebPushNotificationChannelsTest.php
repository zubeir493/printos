<?php

use App\Models\JobOrderTask;
use App\Models\User;
use App\Notifications\DesignerAssignedToTask;
use App\UserRole;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->toContain(WebPushChannel::class);
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
                'url' => url('/design'),
            ],
        ]);
});
