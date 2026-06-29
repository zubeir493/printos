<?php

use App\Filament\Resources\JobOrderTasks\Pages\EditJobOrderTask;
use App\Filament\Resources\JobOrderTasks\Pages\ListJobOrderTasks;
use App\Filament\Resources\JobOrderTasks\Pages\ViewJobOrderTask;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\Partner;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Operations,
    ]));

    User::factory()->create(['role' => UserRole::Design]);
    User::factory()->create(['role' => UserRole::Typist]);
});

it('shows job order task workflow actions on table view and edit pages with distinct colors', function (): void {
    $task = makeAssignableJobOrderTask();

    Livewire::test(ListJobOrderTasks::class)
        ->assertActionVisible(TestAction::make('assign_designer')->table($task))
        ->assertActionHasColor(TestAction::make('assign_designer')->table($task), 'info')
        ->assertActionVisible(TestAction::make('assign_typist')->table($task))
        ->assertActionHasColor(TestAction::make('assign_typist')->table($task), 'primary');

    Livewire::test(ViewJobOrderTask::class, ['record' => $task->id])
        ->assertActionVisible('assign_designer')
        ->assertActionHasColor('assign_designer', 'info')
        ->assertActionVisible('assign_typist')
        ->assertActionHasColor('assign_typist', 'primary');

    Livewire::test(EditJobOrderTask::class, ['record' => $task->id])
        ->assertActionVisible('assign_designer')
        ->assertActionHasColor('assign_designer', 'info')
        ->assertActionVisible('assign_typist')
        ->assertActionHasColor('assign_typist', 'primary');

    $task->update(['status' => 'design']);

    Livewire::test(ListJobOrderTasks::class)
        ->assertActionVisible(TestAction::make('send_to_production')->table($task))
        ->assertActionHasColor(TestAction::make('send_to_production')->table($task), 'success');

    Livewire::test(ViewJobOrderTask::class, ['record' => $task->id])
        ->assertActionVisible('send_to_production')
        ->assertActionHasColor('send_to_production', 'success');

    Livewire::test(EditJobOrderTask::class, ['record' => $task->id])
        ->assertActionVisible('send_to_production')
        ->assertActionHasColor('send_to_production', 'success');
});

function makeAssignableJobOrderTask(): JobOrderTask
{
    $jobOrder = JobOrder::factory()->create([
        'partner_id' => Partner::factory(),
        'status' => 'active',
        'production_mode' => 'make_to_order',
    ]);

    return JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'status' => 'pending',
        'designer_id' => null,
        'typist_id' => null,
    ]);
}
