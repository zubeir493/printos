<?php

use App\Filament\Pages\Settings;
use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

it('renders the live summary as a styled estimator panel', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->assertSee('Final Price')
        ->assertSee('Unit Price')
        ->assertSee('Calculator Type')
        ->assertDontSee('Service Type')
        ->assertDontSee('Deadline')
        ->assertDontSee('Remarks')
        ->assertDontSee('Material Consumption')
        ->assertSee('Awaiting inputs')
        ->assertDontSee('Warnings')
        ->assertSeeHtml('cost-summary-metric cost-summary-total')
        ->assertDontSeeHtml('cost-summary-alert');
});

it('switches the wizard to the selected calculator type', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'job_type' => 'books',
        ])
        ->assertFormFieldIsVisible('services.book.page_count')
        ->assertFormFieldDoesNotExist('services.production.printing_speed')
        ->assertFormFieldDoesNotExist('services.production.artwork_hours')
        ->assertFormFieldDoesNotExist('services.label.width')
        ->assertFormFieldDoesNotExist('services.box.length');
});

it('renders the Sheet2 workbook total in the live book preview', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Sheet2 sample book',
            'job_type' => 'books',
            'quantity' => 1000,
            'services' => [
                'book' => [
                    'page_count' => 64,
                    'size' => 'A5',
                    'binding' => 'Perfect',
                    'cover_laminated' => 'Yes',
                    'inside_printing' => 'Yes',
                    'text_colors' => 1,
                    'cover_colors' => 4,
                    'text_allowance_per_signature' => 50,
                    'cover_allowance' => 20,
                    'text_ink_coverage' => 1,
                    'cover_ink_coverage' => 1,
                    'cover_paper_format' => 'A1',
                ],
                'material' => [
                    'text_paper_unit_cost' => 7000 / 1.15,
                    'text_plate_unit_cost' => 0,
                    'cover_plate_unit_cost' => 900,
                ],
                'commercial' => [
                    'overhead_percent' => 15,
                    'profit_margin_percent' => 25,
                    'discount_percent' => 0,
                ],
            ],
        ])
        ->assertSee('79,895.31');
});

it('renders inventory and machine snapshots with structured costing details', function (): void {
    Setting::first()->update(['vat_rate' => 14]);

    $paper = InventoryItem::factory()->create([
        'name' => 'Premium Label Stock',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 12.5,
        'gsm' => 120,
        'width' => 70,
        'height' => 100,
    ]);

    $machine = Machine::create([
        'name' => 'Flexo Press',
        'code' => 'FLX-2',
        'operation_type' => 'printing',
        'baseline_rounds_per_week' => 0,
        'production_speed' => 500,
        'hourly_cost' => 125,
    ]);

    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Bottle label',
            'job_type' => 'labels',
            'quantity' => 1000,
            'services' => [
                'label' => [
                    'width' => 10,
                    'height' => 8,
                    'yield' => 2,
                ],
                'material' => [
                    'material_item_id' => $paper->id,
                ],
                'production' => [
                    'machine_id' => $machine->id,
                    'printing_up' => 2,
                    'diecutting_up' => 1,
                ],
            ],
        ])
        ->goToWizardStep(3)
        ->assertSee('Material Consumption')
        ->assertSee('Machine Usage')
        ->assertSee('Premium Label Stock')
        ->assertSee('Flexo Press')
        ->assertSee('Profit Margin (20%)')
        ->assertSee('VAT (14%)')
        ->assertSee('Discount')
        ->assertSeeHtml('x-tooltip')
        ->assertSee('120 gsm')
        ->goToWizardStep(4)
        ->assertSee('Flexo Press')
        ->assertSee('Machine Rates Snapshot');
});

it('shows material consumption when a material-backed estimate is calculable', function (): void {
    $paper = InventoryItem::factory()->create([
        'name' => 'Premium Label Stock',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 12.5,
    ]);

    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Bottle label',
            'job_type' => 'labels',
            'quantity' => 1000,
            'services' => [
                'label' => [
                    'width' => 10,
                    'height' => 8,
                ],
                'material' => [
                    'material_item_id' => $paper->id,
                ],
            ],
        ])
        ->assertSee('Material Consumption')
        ->assertSee('Premium Label Stock')
        ->assertDontSee('Label stock')
        ->assertSeeHtml('cost-summary-list');
});

it('keeps book material and machine details in the sidebar only', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Book job',
            'job_type' => 'books',
            'quantity' => 1000,
            'services' => [
                'book' => [
                    'page_count' => 64,
                    'size' => 'A5',
                    'binding' => 'Perfect',
                ],
                'material' => [
                    'text_paper_unit_cost' => 7000 / 1.15,
                    'cover_paper_unit_cost' => 0,
                ],
                'production' => [
                    'printing_speed' => 2500,
                    'printing_rate' => 200,
                ],
                'commercial' => [
                    'discount_percent' => 5,
                ],
            ],
        ])
        ->assertSee('Material Consumption')
        ->assertSee('Text paper')
        ->assertSee('4.4 ream')
        ->assertSee('Machine Usage')
        ->assertSee('Printing')
        ->assertSee('4.8 hour')
        ->assertDontSee('5,000 hour')
        ->assertSee('Discount')
        ->assertSee('Material Cost Breakdown')
        ->assertSee('Labour & Production Cost Breakdown')
        ->assertDontSee('Selected Material Details')
        ->assertDontSee('Machine Snapshots');
});

it('hides hard cover cover paper inputs and trims noisy settings defaults', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Hard cover book',
            'job_type' => 'books',
            'quantity' => 1000,
            'services' => [
                'book' => [
                    'page_count' => 64,
                    'size' => 'A5',
                    'binding' => 'Hard cover',
                ],
            ],
        ])
        ->assertFormFieldIsHidden('services.book.cover_paper_format')
        ->assertFormFieldIsHidden('services.material.cover_paper_unit_cost')
        ->assertFormFieldIsVisible('services.material.case_paper_unit_cost');

    Livewire::test(Settings::class)
        ->assertDontSee('Costing Settings')
        ->assertDontSee('Book Plate Cost')
        ->assertDontSee('Book Text Paper Fallback')
        ->assertDontSee('Book Cover Paper Fallback')
        ->assertDontSee('Book Printing Fallback Speed')
        ->assertDontSee('Book Artwork Fallback Rate');
});
