<?php

use App\Filament\Imports\AccountImporter;
use App\Filament\Imports\AttendanceSegmentImporter;
use App\Filament\Resources\Proformas\Pages\CreateProforma;
use App\Filament\Resources\Proformas\Pages\EditProforma;
use App\Filament\Resources\Proformas\Pages\ViewProforma;
use App\Models\Account;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Proforma;
use App\Models\ProformaTask;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Imports\Models\Import;
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

it('keeps proforma grouped actions visually neutral', function (): void {
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
        ->assertActionHasColor('approve', 'gray');

    Livewire::test(ViewProforma::class, ['record' => $draft->getKey()])
        ->assertActionHasColor('download', 'gray')
        ->assertActionHasColor('email', 'gray')
        ->assertActionHasColor('approve', 'gray');

    foreach ([
        base_path('app/Filament/Resources/Proformas/Pages/EditProforma.php'),
        base_path('app/Filament/Resources/Proformas/Pages/ViewProforma.php'),
        base_path('app/Filament/Resources/Proformas/Tables/ProformasTable.php'),
    ] as $path) {
        expect(file_get_contents($path))
            ->toContain("Action::make('create_job_order')")
            ->toContain("->color('gray')");
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

it('keeps grouped resource actions visually gray', function (): void {
    $providerSource = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    $themeSource = file_get_contents(base_path('resources/css/filament/admin/theme.css'));

    expect($providerSource)
        ->toContain('ActionGroup::configureUsing')
        ->toContain("->color('gray')");

    expect($themeSource)
        ->toContain('.fi-dropdown-list-item[class*="fi-color-"]')
        ->toContain('color: var(--gray-700) !important');
});

it('keeps export actions in page header action groups instead of table headers', function (): void {
    $pageExports = [
        'accounts' => ['app/Filament/Resources/Accounts/Pages/ListAccounts.php', 'AccountExporter::class', 'AccountImporter::class'],
        'bank transactions' => ['app/Filament/Resources/BankTransactions/Pages/ListBankTransactions.php', 'BankTransactionExporter::class', 'BankTransactionImporter::class'],
        'inventory items' => ['app/Filament/Resources/InventoryItems/Pages/ListInventoryItems.php', 'InventoryItemExporter::class', 'InventoryItemImporter::class'],
        'job orders' => ['app/Filament/Resources/JobOrders/Pages/ListJobOrders.php', 'JobOrderExporter::class', 'JobOrderImporter::class'],
        'job order tasks' => ['app/Filament/Resources/JobOrderTasks/Pages/ListJobOrderTasks.php', 'JobOrderTaskExporter::class', 'JobOrderTaskImporter::class'],
        'journal entries' => ['app/Filament/Resources/JournalEntries/Pages/ListJournalEntries.php', 'JournalEntryExporter::class', 'JournalEntryImporter::class'],
        'partners' => ['app/Filament/Resources/Partners/Pages/ListPartners.php', 'PartnerExporter::class', 'PartnerImporter::class'],
        'payments' => ['app/Filament/Resources/Payments/Pages/ListPayments.php', 'PaymentExporter::class', 'PaymentImporter::class'],
        'purchase order items' => ['app/Filament/Resources/PurchaseOrderItems/Pages/ListPurchaseOrderItems.php', 'PurchaseOrderItemExporter::class', 'PurchaseOrderItemImporter::class'],
        'purchase orders' => ['app/Filament/Resources/PurchaseOrders/Pages/ListPurchaseOrders.php', 'PurchaseOrderExporter::class', 'PurchaseOrderImporter::class'],
        'sales orders' => ['app/Filament/Resources/SalesOrders/Pages/ListSalesOrders.php', 'SalesOrderExporter::class', 'SalesOrderImporter::class'],
        'stock movements' => ['app/Filament/Resources/StockMovements/Pages/ListStockMovements.php', 'StockMovementExporter::class', 'StockMovementImporter::class'],
        'stock overview' => ['app/Filament/Pages/StockOverview.php', 'InventoryBalanceExporter::class', null],
        'account statement' => ['app/Filament/Finance/Pages/AccountStatementReport.php', 'AccountStatementExporter::class', null],
        'balance sheet' => ['app/Filament/Finance/Pages/BalanceSheetReport.php', 'FinancialAccountExporter::class', null],
        'general ledger' => ['app/Filament/Finance/Pages/GeneralLedgerReport.php', 'GeneralLedgerExporter::class', null],
        'income statement' => ['app/Filament/Finance/Pages/IncomeStatementReport.php', 'FinancialAccountExporter::class', null],
        'payables aging' => ['app/Filament/Finance/Pages/PayablesAgingReport.php', 'PayablesAgingExporter::class', null],
        'profit loss' => ['app/Filament/Finance/Pages/ProfitLossStatementReport.php', 'ProfitLossStatementExporter::class', null],
        'receivables aging' => ['app/Filament/Finance/Pages/ReceivablesAgingReport.php', 'ReceivablesAgingExporter::class', null],
        'trial balance' => ['app/Filament/Finance/Pages/TrialBalanceReport.php', 'FinancialAccountExporter::class', null],
    ];

    foreach ($pageExports as [$path, $exporter, $importer]) {
        $source = file_get_contents(base_path($path));

        expect($source)
            ->toContain('ActionGroup::make([')
            ->toContain('ExportAction::make()')
            ->toContain($exporter)
            ->not->toContain('->headerActions([');

        if (str_contains($source, 'CreateAction::make()')) {
            expect(strpos($source, 'CreateAction::make()'))
                ->toBeLessThan(strpos($source, 'ActionGroup::make(['));
        }

        if ($importer) {
            expect($source)
                ->toContain('ImportAction::make()')
                ->toContain($importer);
        }
    }

    foreach ([
        'app/Filament/Resources/BankTransactions/Tables/BankTransactionsTable.php',
        'app/Filament/Resources/InventoryItems/Tables/InventoryItemsTable.php',
        'app/Filament/Resources/JobOrders/Tables/JobOrdersTable.php',
        'app/Filament/Resources/JobOrderTasks/Tables/JobOrderTasksTable.php',
        'app/Filament/Resources/JournalEntries/Tables/JournalEntriesTable.php',
        'app/Filament/Resources/Partners/Tables/PartnersTable.php',
        'app/Filament/Resources/Payments/Tables/PaymentsTable.php',
        'app/Filament/Resources/PurchaseOrderItems/Tables/PurchaseOrderItemsTable.php',
        'app/Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php',
        'app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php',
        'app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))
            ->not->toContain('ExportAction::make()')
            ->not->toContain('->headerActions([');
    }
});

it('keeps attendance import after create with explicit csv validation', function (): void {
    $source = file_get_contents(base_path('app/Filament/Resources/AttendanceSegments/Pages/ManageAttendanceSegments.php'));

    expect($source)
        ->toContain('CreateAction::make()')
        ->toContain('ActionGroup::make([')
        ->toContain("ImportAction::make('importAttendanceCsv')")
        ->toContain('AttendanceSegmentImporter::class')
        ->toContain("File::types(['csv', 'txt'])->max(10240)");

    expect(strpos($source, 'CreateAction::make()'))
        ->toBeLessThan(strpos($source, "ImportAction::make('importAttendanceCsv')"));
});

it('does not reorder primary header actions in css', function (): void {
    $source = file_get_contents(base_path('resources/css/filament/admin/theme.css'));

    preg_match('/\.fi-ac-btn-action\.fi-color-primary\s*\{(?<body>.*?)\n\}/s', $source, $matches);

    expect($matches)->not->toBeEmpty();
    expect($matches['body'])->not->toContain('order:');
});

it('imports chart of accounts rows by account code', function (): void {
    $existingAccount = Account::create([
        'code' => '1010',
        'name' => 'Old cash account',
        'type' => 'Asset',
        'default_tracking_type' => null,
    ]);

    $importer = new AccountImporter(new Import, [
        'code' => 'code',
        'name' => 'name',
        'type' => 'type',
        'default_tracking_type' => 'default_tracking_type',
    ], []);

    $importer([
        'code' => '1010',
        'name' => 'Cash on Hand',
        'type' => 'asset',
        'default_tracking_type' => 'Vehicle',
    ]);

    $importer([
        'code' => '6200',
        'name' => 'Fuel Expense',
        'type' => 'expense',
        'default_tracking_type' => 'Vehicle',
    ]);

    expect($existingAccount->refresh())
        ->name->toBe('Cash on Hand')
        ->type->toBe('Asset')
        ->default_tracking_type->toBeNull();

    expect(Account::query()->where('code', '6200')->first())
        ->name->toBe('Fuel Expense')
        ->type->toBe('Expense')
        ->default_tracking_type->toBe('vehicle');
});

it('imports attendance segments by fp number using the standard importer', function (): void {
    $employee = Employee::factory()->create([
        'attendance_device_id' => '12',
    ]);

    $importer = new AttendanceSegmentImporter(new Import, [
        'date' => 'date',
        'fp_no' => 'fp_no',
        'schedule_name' => 'schedule_name',
        'scheduled_start' => 'scheduled_start',
        'scheduled_end' => 'scheduled_end',
        'clock_in' => 'clock_in',
        'clock_out' => 'clock_out',
        'late_minutes' => 'late_minutes',
        'early_minutes' => 'early_minutes',
        'worked_minutes' => 'worked_minutes',
        'overtime_minutes' => 'overtime_minutes',
        'day_fraction' => 'day_fraction',
        'status' => 'status',
        'exception' => 'exception',
        'correction_reason' => 'correction_reason',
    ], []);

    $importer([
        'date' => '01-31-26',
        'fp_no' => '12',
        'schedule_name' => 'Day',
        'scheduled_start' => '8:00 AM',
        'scheduled_end' => '5:00 PM',
        'clock_in' => '8:10 AM',
        'clock_out' => '5:00 PM',
        'late_minutes' => '10',
        'early_minutes' => '0',
        'worked_minutes' => '8:50',
        'overtime_minutes' => '0:30',
        'day_fraction' => '1',
        'status' => 'Late',
        'exception' => '',
        'correction_reason' => '',
    ]);

    expect(AttendanceSegment::query()->first())
        ->employee_id->toBe($employee->id)
        ->fp_no->toBe('12')
        ->worked_minutes->toBe(530)
        ->overtime_minutes->toBe(30);

    expect(Shift::query()->where('name', 'Day')->exists())->toBeTrue();
});

it('uses gray edit actions in multi action page headers', function (): void {
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
            ->toContain("->color('gray')");
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
        ->toContain("->color('gray')")
        ->toContain("Action::make('return_materials')")
        ->toContain("Action::make('receive')")
        ->toContain("->color('gray')")
        ->toContain("Warehouse::query()->orderBy('name')->pluck('name', 'id')->all()");
});

it('keeps the job order cost calculation upload compact', function (): void {
    $formSource = file_get_contents(base_path('app/Filament/Resources/JobOrders/Schemas/JobOrderForm.php'));
    $themeSource = file_get_contents(base_path('resources/css/filament/admin/theme.css'));

    expect($formSource)
        ->toContain("FileUpload::make('cost_calc_file')")
        ->toContain("->panelLayout('compact')")
        ->toContain("->placeholder('Upload cost file')")
        ->toContain("->extraAttributes(['class' => 'job-order-cost-file-upload'])")
        ->and($themeSource)
        ->toContain('.job-order-cost-file-upload .filepond--drop-label')
        ->toContain('height: 2.25rem')
        ->toContain('.job-order-cost-file-upload .filepond--file-info-sub')
        ->toContain('display: none');
});
