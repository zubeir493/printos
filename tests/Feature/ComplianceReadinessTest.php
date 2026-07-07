<?php

use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
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
    'attendance imports' => ['app/Filament/Resources/AttendanceSegments/Pages/ManageAttendanceSegments.php'],
    'artworks' => ['app/Filament/Resources/Artworks/Schemas/ArtworkForm.php'],
    'bids' => ['app/Filament/Resources/Bids/Schemas/BidForm.php'],
    'journal attachments' => ['app/Filament/Resources/JournalEntries/Schemas/JournalEntryForm.php'],
]);

test('order payment actions lock payable rows before checking balances', function (string $path): void {
    expect(file_get_contents(base_path($path)))
        ->toContain('lockForUpdate()')
        ->toContain('Cannot pay more than the remaining balance');
})->with([
    'job order table payments' => ['app/Filament/Resources/JobOrders/Tables/JobOrdersTable.php'],
    'sales order table payments' => ['app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php'],
    'sales order view payments' => ['app/Filament/Resources/SalesOrders/Pages/ViewSalesOrder.php'],
    'purchase order table payments' => ['app/Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php'],
]);
