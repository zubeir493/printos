<?php

use App\Filament\Resources\JobOrders\Pages\ViewJobOrder;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\User;
use App\Support\Money;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('s3');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

test('job detail explains blockers and promotes the next action without duplicating it', function (): void {
    $job = JobOrder::factory()->create(['status' => 'draft']);
    $component = Livewire::test(ViewJobOrder::class, ['record' => $job->id])
        ->assertSee('Workflow readiness')->assertSee('Confirm the specifications, then start the job.')
        ->assertActionVisible('activate');
    $actions = $component->instance()->getCachedHeaderActions();
    expect($actions[0])->toBeInstanceOf(Action::class)->and($actions[0]->getName())->toBe('activate')
        ->and($actions[1])->toBeInstanceOf(ActionGroup::class)
        ->and(array_keys($actions[1]->getFlatActions()))->not->toContain('activate');
});

test('material readiness distinguishes the base requirement reserve and units', function (): void {
    $item = InventoryItem::factory()->create(['name' => 'Book Paper', 'unit' => 'sheets']);
    $job = JobOrder::factory()->create(['status' => 'design', 'deliverables' => [['type' => 'artwork', 'label' => 'Cover']], 'paper' => [[
        'inventory_item_id' => $item->id, 'required_quantity' => 100, 'reserve_quantity' => 20,
    ]]]);
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])
        ->assertSee('Required file approval pending')->assertSee('Book Paper')->assertSee('sheets')
        ->assertSee('Reserve allowance')->assertSee('Remaining incl. reserve');
    expect($job->materials_summary[0])->toMatchArray(['planned' => 100.0, 'reserved' => 20.0, 'required' => 120.0, 'remaining' => 120.0]);
});

test('production users do not receive financial readiness details', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('production'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Production]));
    $job = JobOrder::factory()->create(['status' => 'production']);
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])
        ->assertSee('Workflow readiness')->assertDontSee('Remaining payment: '.Money::format($job->balance, 2));
});

test('design review action points to the file tabs', function (): void {
    $job = JobOrder::factory()->create(['status' => 'design', 'deliverables' => [['type' => 'artwork', 'label' => 'Cover']]]);
    Livewire::test(ViewJobOrder::class, ['record' => $job->id])
        ->assertActionVisible('review_files')->assertSeeHtml('id="job-order-files"');
});
