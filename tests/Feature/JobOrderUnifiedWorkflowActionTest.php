<?php

use App\Filament\Resources\JobOrders\Pages\EditJobOrder;
use App\Filament\Resources\JobOrders\Pages\ListJobOrders;
use App\Filament\Resources\JobOrders\Pages\ViewJobOrder;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('s3'));

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Operations,
    ]));

    User::factory()->create(['role' => UserRole::Design]);
    User::factory()->create(['role' => UserRole::Typist]);
});

it('shows job order workflow actions for the current stage with consistent colors', function (): void {
    $task = makeAssignableJobOrder();

    Livewire::test(ListJobOrders::class)
        ->assertActionVisible(TestAction::make('activate')->table($task))
        ->assertActionVisible(TestAction::make('delete')->table($task))
        ->assertActionHidden(TestAction::make('assign_designer')->table($task))
        ->assertActionHidden(TestAction::make('assign_typist')->table($task))
        ->assertActionHidden(TestAction::make('send_to_production')->table($task))
        ->assertActionHidden(TestAction::make('print_job_order')->table($task))
        ->assertActionHidden(TestAction::make('request_materials')->table($task));

    Livewire::test(ViewJobOrder::class, ['record' => $task->id])
        ->assertActionVisible('activate')
        ->assertActionVisible('delete')
        ->assertActionHidden('assign_designer')
        ->assertActionHidden('assign_typist')
        ->assertActionHidden('send_to_production');

    Livewire::test(EditJobOrder::class, ['record' => $task->id])
        ->assertActionVisible('activate')
        ->assertActionVisible('delete')
        ->assertActionHidden('assign_designer')
        ->assertActionHidden('assign_typist')
        ->assertActionHidden('send_to_production');

    $task->updateQuietly(['status' => 'design']);

    Livewire::test(ListJobOrders::class)
        ->assertActionHidden(TestAction::make('activate')->table($task))
        ->assertActionHidden(TestAction::make('delete')->table($task))
        ->assertActionVisible(TestAction::make('assign_designer')->table($task))
        ->assertActionHasColor(TestAction::make('assign_designer')->table($task), 'gray')
        ->assertActionVisible(TestAction::make('assign_typist')->table($task))
        ->assertActionHasColor(TestAction::make('assign_typist')->table($task), 'gray')
        ->assertActionVisible(TestAction::make('send_to_production')->table($task))
        ->assertActionHasColor(TestAction::make('send_to_production')->table($task), 'gray');

    Livewire::test(ViewJobOrder::class, ['record' => $task->id])
        ->assertActionVisible('assign_designer')
        ->assertActionHasColor('assign_designer', 'gray')
        ->assertActionVisible('assign_typist')
        ->assertActionHasColor('assign_typist', 'gray')
        ->assertActionVisible('send_to_production')
        ->assertActionHasColor('send_to_production', 'gray');

    $task->updateQuietly(['status' => 'production']);
    Livewire::test(ViewJobOrder::class, ['record' => $task->id])
        ->assertActionHidden('assign_designer')
        ->assertActionHidden('assign_typist')
        ->assertActionHidden('send_to_production')
        ->assertActionHidden('complete')
        ->assertActionVisible('log_production');
});

function makeAssignableJobOrder(): JobOrder
{
    $jobOrder = JobOrder::factory()->create([
        'partner_id' => Partner::factory(),
        'status' => 'design',
        'production_mode' => 'make_to_order',
    ]);

    return tap($jobOrder, fn (JobOrder $record) => $record->updateQuietly([

        'status' => 'draft',
        'designer_id' => null,
        'typist_id' => null,
    ]));
}
