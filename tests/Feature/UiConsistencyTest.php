<?php

use App\Filament\Resources\Proformas\Pages\CreateProforma;
use App\Filament\Resources\Proformas\Pages\EditProforma;
use App\Filament\Resources\Proformas\Pages\ViewProforma;
use App\Models\Partner;
use App\Models\Proforma;
use App\Models\ProformaTask;
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

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));
});

it('renders proforma totals as summary placeholders instead of read only inputs', function (): void {
    $source = file_get_contents(base_path('app/Filament/Resources/Proformas/Schemas/ProformaForm.php'));

    expect($source)
        ->toContain("Hidden::make('subtotal')")
        ->toContain("Hidden::make('tax_amount')")
        ->toContain("Hidden::make('total')")
        ->toContain("Placeholder::make('summary_subtotal')")
        ->toContain("Placeholder::make('summary_tax_amount')")
        ->toContain("Placeholder::make('summary_total')")
        ->toContain("->label('Tax (VAT)')")
        ->toContain('orderSummary')
        ->toContain('cost-summary-metric cost-summary-total')
        ->not->toContain("TextInput::make('subtotal')")
        ->not->toContain("TextInput::make('tax_amount')")
        ->not->toContain("TextInput::make('total')");
});

it('keeps proforma actions visually distinct by intent', function (): void {
    $customer = Partner::factory()->create(['is_customer' => true]);
    $draft = Proforma::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'draft',
    ]);
    $approved = Proforma::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'approved',
    ]);
    ProformaTask::factory()->for($approved)->create();

    Livewire::test(EditProforma::class, ['record' => $draft->getKey()])
        ->assertActionHasColor('download', 'gray')
        ->assertActionHasColor('email', 'gray')
        ->assertActionHasColor('approve', 'success');

    Livewire::test(ViewProforma::class, ['record' => $draft->getKey()])
        ->assertActionHasColor('download', 'gray')
        ->assertActionHasColor('email', 'gray')
        ->assertActionHasColor('approve', 'success');

    foreach ([
        base_path('app/Filament/Resources/Proformas/Pages/EditProforma.php'),
        base_path('app/Filament/Resources/Proformas/Pages/ViewProforma.php'),
        base_path('app/Filament/Resources/Proformas/Tables/ProformasTable.php'),
    ] as $path) {
        expect(file_get_contents($path))
            ->toContain("Action::make('create_job_order')")
            ->toContain('->color(Color::Indigo)');
    }

    expect($approved->canCreateJobOrder())->toBeTrue();
});

it('groups multi action headers instead of rendering separate action buttons', function (): void {
    $sources = [
        'proforma edit' => file_get_contents(base_path('app/Filament/Resources/Proformas/Pages/EditProforma.php')),
        'proforma view' => file_get_contents(base_path('app/Filament/Resources/Proformas/Pages/ViewProforma.php')),
        'invoice view' => file_get_contents(base_path('app/Filament/Resources/Invoices/Pages/ViewInvoice.php')),
        'invoice edit' => file_get_contents(base_path('app/Filament/Resources/Invoices/Pages/EditInvoice.php')),
        'payroll edit' => file_get_contents(base_path('app/Filament/Resources/PayrollRuns/Pages/EditPayrollRun.php')),
        'partner view' => file_get_contents(base_path('app/Filament/Resources/Partners/Pages/ViewPartner.php')),
        'job order task view' => file_get_contents(base_path('app/Filament/Resources/JobOrderTasks/Pages/ViewJobOrderTask.php')),
        'job order task edit' => file_get_contents(base_path('app/Filament/Resources/JobOrderTasks/Pages/EditJobOrderTask.php')),
        'job order edit' => file_get_contents(base_path('app/Filament/Resources/JobOrders/Pages/EditJobOrder.php')),
        'partner statement' => file_get_contents(base_path('app/Filament/Resources/Partners/Pages/PartnerStatement.php')),
    ];

    foreach ([
        'proforma edit',
        'proforma view',
        'invoice view',
        'invoice edit',
        'payroll edit',
        'partner view',
        'job order task view',
        'job order task edit',
        'job order edit',
    ] as $key) {
        expect($sources[$key])
            ->toContain('ActionGroup::make([');
    }

    expect($sources['partner view'])
        ->toContain("Action::make('statement')")
        ->toContain("->color('gray')");
    expect($sources['partner statement'])
        ->toContain("Action::make('viewPartner')")
        ->toContain("->color('gray')");
});

it('uses indigo edit actions in multi action page headers', function (): void {
    foreach ([
        'app/Filament/Resources/Bids/Pages/Concerns/InteractsWithBidActions.php',
        'app/Filament/Resources/CostEstimates/Pages/ViewCostEstimate.php',
        'app/Filament/Resources/Dispatches/Pages/ViewDispatch.php',
        'app/Filament/Resources/JobOrders/Pages/ViewJobOrder.php',
        'app/Filament/Resources/JobOrderTasks/Pages/ViewJobOrderTask.php',
        'app/Filament/Resources/Partners/Pages/ViewPartner.php',
        'app/Filament/Resources/ProductionPlans/Pages/ViewProductionPlan.php',
        'app/Filament/Resources/ProductionReports/Pages/ViewProductionReport.php',
        'app/Filament/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php',
        'app/Filament/Resources/SalesOrders/Pages/ViewSalesOrder.php',
        'app/Filament/Resources/StockAdjustments/Pages/ViewStockAdjustment.php',
    ] as $path) {
        $source = file_get_contents(base_path($path));

        expect($source)
            ->toContain('EditAction::make()')
            ->toContain('->color(Color::Indigo)');
    }
});

it('renders create and edit proforma forms with the styled summary panel', function (): void {
    $customer = Partner::factory()->create(['is_customer' => true]);
    $proforma = Proforma::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'draft',
    ]);
    ProformaTask::factory()->for($proforma)->create([
        'quantity' => 10,
        'unit_price' => 20,
        'task_cost' => 200,
    ]);

    Livewire::test(CreateProforma::class)
        ->assertSuccessful()
        ->assertSee('Tax (VAT)')
        ->assertSeeHtml('cost-summary-value-primary');

    Livewire::test(EditProforma::class, ['record' => $proforma->getKey()])
        ->assertSuccessful()
        ->assertSee('Tax (VAT)')
        ->assertSeeHtml('cost-summary-value-primary');
});

it('keeps order workflow copy and colors consistent', function (): void {
    $resourceSource = collect([
        'app/Filament/Resources/Proformas/Schemas/ProformaForm.php',
        'app/Filament/Resources/Proformas/Pages/EditProforma.php',
        'app/Filament/Resources/Proformas/Pages/ViewProforma.php',
        'app/Filament/Resources/Proformas/Tables/ProformasTable.php',
        'app/Filament/Resources/JobOrders/Schemas/JobOrderForm.php',
        'app/Filament/Resources/JobOrders/Pages/EditJobOrder.php',
        'app/Filament/Resources/JobOrders/Pages/ViewJobOrder.php',
        'app/Filament/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php',
        'app/Filament/Resources/Payments/Schemas/PaymentForm.php',
    ])->map(fn (string $path): string => file_get_contents(base_path($path)))->implode("\n");

    expect($resourceSource)
        ->not->toContain('Paid Via')
        ->not->toContain('Peices')
        ->not->toContain('Panton No')
        ->toContain('Payment method')
        ->toContain('Pieces per sheet')
        ->toContain('Pantone No')
        ->toContain("Action::make('issue_materials')")
        ->toContain("->color('warning')")
        ->toContain("Action::make('return_materials')")
        ->toContain("Action::make('receive')")
        ->toContain("->color('success')")
        ->toContain("Warehouse::query()->orderBy('name')->pluck('name', 'id')->all()");
});
