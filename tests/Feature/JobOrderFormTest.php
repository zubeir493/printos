<?php

use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\EditJobOrder;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('s3');
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
});

it('uses compact table repeaters and four columns for the specification fields', function (): void {
    Livewire::test(CreateJobOrder::class)
        ->assertFormFieldExists('paper', fn (Repeater $component): bool => $component->isTable() && $component->isCompact() && count($component->getTableColumns()) === 3)
        ->assertFormFieldExists('deliverables', fn (Repeater $component): bool => $component->isTable() && $component->isCompact() && count($component->getTableColumns()) === 2)
        ->assertFormFieldExists('unit_price', fn (TextInput $component): bool => $component->getContainer()->getParentComponent()->getColumns('xl') === 4)
        ->assertFormComponentActionExists('paper', 'add_paper')
        ->assertFormComponentActionExists('deliverables', 'add_deliverable');
});

it('adds rows with the custom repeater actions and can restart an empty repeater', function (): void {
    $component = Livewire::test(CreateJobOrder::class);
    $paperKey = array_key_first($component->get('data.paper'));
    $deliverableKey = array_key_first($component->get('data.deliverables'));
    $component->callFormComponentAction('paper', 'add_paper', arguments: ['item' => $paperKey])
        ->callFormComponentAction('deliverables', 'add_deliverable', arguments: ['item' => $deliverableKey]);
    expect($component->get('data.paper'))->toHaveCount(2)
        ->and($component->get('data.deliverables'))->toHaveCount(2);
    $component->set('data.paper', [])->callFormComponentAction('paper', 'add')
        ->set('data.deliverables', [])->callFormComponentAction('deliverables', 'add');
    expect($component->get('data.paper'))->toHaveCount(1)
        ->and($component->get('data.deliverables'))->toHaveCount(1);
});

it('updates the summary and saves quantity times unit price with the configured VAT', function (bool $vatEnabled, float $tax, float $total): void {
    Setting::getSettings()->update(['vat_enabled' => $vatEnabled, 'vat_rate' => 15]);
    $component = Livewire::test(CreateJobOrder::class)->fillForm(jobOrderFormData())
        ->set('data.unit_price', 2.5)->set('data.quantity', 10)
        ->assertFormSet(['subtotal' => 25.0, 'tax_amount' => $tax, 'total' => $total]);
    $component->set('data.subtotal', 999)->set('data.tax_amount', 999)->set('data.total', 999)
        ->call('create')->assertHasNoFormErrors();
    $job = JobOrder::query()->sole();
    expect((float) $job->subtotal)->toBe(25.0)->and((float) $job->tax_amount)->toBe($tax)->and((float) $job->total)->toBe($total);
})->with([[true, 3.75, 28.75], [false, 0.0, 25.0]]);

it('loads the effective unit price for an existing job and recalculates when quantity changes', function (): void {
    Setting::getSettings()->update(['vat_enabled' => true, 'vat_rate' => 15]);
    $job = JobOrder::factory()->create(['quantity' => 4, 'subtotal' => 100, 'tax_amount' => 15, 'partner_id' => Partner::factory()->create(['is_customer' => true])->id, 'due_date' => today()->addDays(30), 'job_type' => 'packages']);
    Storage::disk('s3')->put($job->cost_calc_file, 'cost');
    Livewire::test(EditJobOrder::class, ['record' => $job->id])
        ->assertFormSet(['unit_price' => 25.0, 'subtotal' => 100])
        ->set('data.quantity', 8)
        ->assertFormSet(['subtotal' => 200.0, 'tax_amount' => 30.0, 'total' => 230.0])
        ->call('save')->assertHasNoFormErrors();
    expect((float) $job->fresh()->subtotal)->toBe(200.0)->and((float) $job->fresh()->total)->toBe(230.0);
});

it('preserves the stored subtotal when the effective unit price is recurring', function (): void {
    Setting::getSettings()->update(['vat_enabled' => false]);
    $job = JobOrder::factory()->create(['quantity' => 3, 'subtotal' => 10, 'tax_amount' => 0, 'partner_id' => Partner::factory()->create(['is_customer' => true])->id, 'due_date' => today()->addDays(30), 'job_type' => 'packages']);
    Storage::disk('s3')->put($job->cost_calc_file, 'cost');
    Livewire::test(EditJobOrder::class, ['record' => $job->id])->call('save')->assertHasNoFormErrors();
    expect((float) $job->fresh()->subtotal)->toBe(10.0);
});

it('saves the materials and deliverables entered in the table repeaters', function (): void {
    $paper = InventoryItem::factory()->create(['type' => 'raw_material']);
    Livewire::test(CreateJobOrder::class)
        ->fillForm([
            ...jobOrderFormData(), 'unit_price' => 5, 'quantity' => 2,
            'paper' => [['inventory_item_id' => $paper->id, 'required_quantity' => 20, 'reserve_quantity' => 2]],
            'deliverables' => [['label' => 'Cover', 'type' => 'artwork'], ['label' => 'Body', 'type' => 'text_file']],
        ])->call('create')->assertHasNoFormErrors();
    $job = JobOrder::query()->sole();
    expect((float) $job->subtotal)->toBe(10.0)->and($job->paper)->toHaveCount(1)
        ->and($job->paper[0]['inventory_item_id'])->toEqual($paper->id)->and($job->deliverables)->toHaveCount(2);
});

it('rejects negative unit prices', function (): void {
    Livewire::test(CreateJobOrder::class)->fillForm([...jobOrderFormData(), 'unit_price' => -1])
        ->call('create')->assertHasFormErrors(['unit_price' => 'min']);
    expect(JobOrder::count())->toBe(0);
});

/** @return array<string, mixed> */
function jobOrderFormData(): array
{
    return [
        'production_mode' => 'make_to_order', 'partner_id' => Partner::factory()->create(['is_customer' => true])->id,
        'job_type' => 'packages', 'submission_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(),
        'name' => 'Carton', 'quantity' => 1, 'paper' => [], 'deliverables' => [],
        'cost_calc_file' => UploadedFile::fake()->create('cost.csv', 1, 'text/csv'),
    ];
}
