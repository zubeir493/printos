<?php

use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\JobOrders\Actions\JobOrderActions;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Resources\SalesOrders\Actions\SalesOrderActions;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('file uploads are globally protected against submitted path tampering', function (): void {
    expect(file_get_contents(app_path('Providers/AppServiceProvider.php')))
        ->toContain('FileUpload::configureUsing')
        ->toContain('preventFilePathTampering()');
});

test('posted financial records cannot be bulk deleted from list tables', function (string $page): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test($page)
        ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk());
})->with([
    'payments' => [ListPayments::class],
    'invoices' => [ListInvoices::class],
]);

test('core order list tables do not expose destructive bulk deletes', function (string $path): void {
    expect(file_get_contents(base_path($path)))
        ->not->toContain('DeleteBulkAction::make');
})->with([
    'job orders' => ['app/Filament/Resources/JobOrders/Tables/JobOrdersTable.php'],
    'sales orders' => ['app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php'],
    'purchase orders' => ['app/Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php'],
]);

test('payment void audit metadata is persisted', function (): void {
    $payment = Payment::factory()->create();
    $user = User::factory()->create();

    $payment->update([
        'voided_at' => now(),
        'voided_by' => $user->id,
        'void_reason' => 'Compliance reversal',
    ]);

    expect($payment->fresh())
        ->voided_by->toBe($user->id)
        ->void_reason->toBe('Compliance reversal');
});

test('invoice paid status must come from payment records', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $partner = Partner::factory()->create(['is_customer' => true]);
    $invoice = Invoice::create([
        'invoice_type' => 'sales',
        'order_id' => 1,
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
        'balance_due' => 100,
        'status' => 'sent',
        'filename' => 'invoice.pdf',
        'file_path' => 'invoices/invoice.pdf',
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->id])
        ->assertActionDoesNotExist('mark_paid')
        ->assertActionDoesNotExist('delete');

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertActionDoesNotExist('mark_paid');
});

test('high-risk uploads declare type and size limits', function (string $path): void {
    $contents = file_get_contents(base_path($path));

    expect($contents)
        ->toContain('FileUpload::make')
        ->toContain('acceptedFileTypes')
        ->toContain('maxSize');
})->with([
    'artworks' => ['app/Filament/Resources/Artworks/Schemas/ArtworkForm.php'],
    'bids' => ['app/Filament/Resources/Bids/Schemas/BidForm.php'],
    'journal attachments' => ['app/Filament/Resources/JournalEntries/Schemas/JournalEntryForm.php'],
]);

test('attendance imports declare csv file validation limits', function (): void {
    expect(file_get_contents(base_path('app/Filament/Resources/AttendanceSegments/Pages/ManageAttendanceSegments.php')))
        ->toContain("ImportAction::make('importAttendanceCsv')")
        ->toContain('fileRules')
        ->toContain("File::types(['csv', 'txt'])->max(10240)");
});

test('order payment actions reject payments above the current balance even with a stale record', function (string $kind): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $partner = Partner::factory()->create(['is_customer' => true, 'is_supplier' => true]);
    if ($kind === 'job') {
        $record = JobOrder::factory()->create(['partner_id' => $partner->id, 'status' => 'design', 'subtotal' => 100, 'total' => 100]);
        $action = JobOrderActions::pay();
    } elseif ($kind === 'purchase') {
        $record = PurchaseOrder::withoutEvents(fn () => PurchaseOrder::create([
            'po_number' => 'PO-STALE', 'partner_id' => $partner->id, 'order_date' => today(), 'subtotal' => 100, 'total' => 100, 'status' => 'approved',
        ]));
        $action = PurchaseOrderActions::pay();
    } else {
        $record = SalesOrder::withoutEvents(fn () => SalesOrder::create([
            'order_number' => 'SO-STALE', 'partner_id' => $partner->id, 'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(), 'subtotal' => 100, 'total' => 100, 'status' => 'submitted', 'payment_mode' => 'credit',
        ]));
        $action = SalesOrderActions::pay();
    }
    Payment::factory()->create(['payable_type' => $record::class, 'payable_id' => $record->id, 'amount' => 80,
        'direction' => $kind === 'purchase' ? 'outbound' : 'inbound', 'method' => 'cash']);
    $record->setAttribute('payments_sum_amount', 0);
    ($action->getActionFunction())($record, ['amount' => 30, 'method' => 'cash', 'payment_date' => today()->toDateString()]);
    expect(Payment::query()->where('payable_type', $record::class)->where('payable_id', $record->id)->count())->toBe(1);
    expect((float) $record->fresh()->balance)->toBe(20.0);
})->with(['job order table payments' => 'job', 'sales order table payments' => 'sale', 'sales order view payments' => 'sale-view', 'purchase order table payments' => 'purchase']);
