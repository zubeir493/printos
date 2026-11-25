<?php

use App\Filament\Finance\Widgets\HighRiskReceivables;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Mail\CustomerReminderMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\GoodsReceiptPostedNotification;
use App\Notifications\LeaveRequestDecisionNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PurchaseOrderApprovedNotification;
use App\Notifications\SalesOrderCreatedNotification;
use App\Services\InvoiceGeneratorService;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('standalone invoice issuance is unavailable and historical PDFs can still be viewed', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $invoice = Invoice::create([
        'invoice_type' => 'sales',
        'partner_id' => Partner::factory()->create()->id,
        'invoice_date' => today(),
        'due_date' => today()->addDays(30),
        'order_type' => 'sales_order',
        'order_id' => null,
        'subtotal' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
        'status' => 'draft',
        'filename' => 'historical-invoice.pdf',
        'file_path' => 'invoices/historical-invoice.pdf',
    ]);

    $this->get(InvoiceResource::getUrl('create'))->assertRedirect('/finance');
    expect(InvoiceResource::canCreate())->toBeFalse();
    $this->get(InvoiceResource::getUrl('view', ['record' => $invoice]))->assertSuccessful();
    Livewire::test(ListInvoices::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionHidden('email', $invoice)
        ->assertTableActionHidden('mark_sent', $invoice)
        ->assertTableActionVisible('download', $invoice);
});

test('creating an order-backed invoice stores its generated PDF', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    config(['filesystems.private_disk' => 'local']);
    Storage::fake('local');

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $partner = Partner::create([
        'name' => 'PDF Customer',
        'email' => 'pdf@example.com',
        'phone' => '0944444444',
        'address' => 'Main Street',
        'is_customer' => true,
    ]);

    $order = SalesOrder::create([
        'partner_id' => $partner->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'order_date' => today(),
        'subtotal' => 500,
        'tax_amount' => 75,
        'total' => 575,
        'status' => SalesOrder::STATUS_SUBMITTED,
    ]);
    $invoice = app(InvoiceGeneratorService::class)->generateFromSalesOrder($order)['invoice'];

    Storage::disk('local')->assertExists($invoice->file_path);
    expect($invoice->order_id)->toBe($order->id);
});

test('an order-backed invoice with a failed email stays unsent and shows a failure notification', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $this->mock(InvoiceGeneratorService::class, function ($mock): void {
        $mock->shouldReceive('getInvoiceDownloadUrl')->andReturn('/invoices/draft-invoice.pdf');
        $mock->shouldReceive('sendInvoiceEmail')
            ->once()
            ->andThrow(new RuntimeException('Mailtrap rejected the message.'));
    });

    $invoice = releaseDraftInvoice([
        'email' => 'failure@example.com',
    ]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('email', $invoice, ['email' => 'failure@example.com'])
        ->assertNotified('Email Failed');

    $invoice->refresh();

    expect($invoice->emailed_at)->toBeNull()
        ->and($invoice->email_recipient)->toBeNull()
        ->and($invoice->status)->toBe('draft')
        ->and(EmailLog::count())->toBe(0);
});

test('invoice edit actions transition status without exposing the status field', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $partner = Partner::create([
        'name' => 'Action Customer',
        'email' => 'action@example.com',
        'is_customer' => true,
    ]);

    $invoice = Invoice::create([
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'order_id' => releaseInvoiceOrder($partner)->id,
        'partner_id' => $partner->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
        'balance_due' => 100,
        'status' => 'draft',
        'filename' => 'manual-invoice.pdf',
        'file_path' => 'invoices/manual-invoice.pdf',
    ]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->id])
        ->assertFormFieldDoesNotExist('invoice_number')
        ->assertFormFieldDoesNotExist('status')
        ->assertActionDoesNotExist('mark_paid')
        ->callAction('mark_sent')
        ->assertNotified('Invoice marked as sent');

    expect($invoice->refresh()->status)->toBe('sent')
        ->and($invoice->balance_due)->toBe('100.00');
});

test('an overdue invoice with a recorded payment cannot be cancelled', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $invoice = releaseDraftInvoice(['email' => 'finance@example.com']);
    $invoice->update(['status' => 'overdue', 'balance_due' => (float) $invoice->total_amount - 10]);

    Livewire::test(ListInvoices::class)->assertTableActionHidden('cancel_invoice', $invoice);
    expect($invoice->fresh()->status)->toBe('overdue');

    $invoice->update(['balance_due' => $invoice->total_amount]);
    Livewire::test(ListInvoices::class)
        ->callTableAction('cancel_invoice', $invoice)
        ->assertNotified('Invoice cancelled');
    expect($invoice->fresh()->status)->toBe('cancelled');
});

test('emailing an invoice from the table marks open invoices as sent', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $this->mock(InvoiceGeneratorService::class, function ($mock): void {
        $mock->shouldReceive('getInvoicePath')
            ->andReturn('https://printos.test/invoices/table-invoice.pdf');
        $mock->shouldReceive('getInvoiceDownloadUrl')
            ->andReturn('https://printos.test/invoices/table-invoice.pdf');
        $mock->shouldReceive('sendInvoiceEmail')
            ->once()
            ->andReturnTrue();
    });

    $partner = Partner::create([
        'name' => 'Table Email Customer',
        'email' => 'table@example.com',
        'is_customer' => true,
    ]);

    $invoice = Invoice::create([
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'order_id' => releaseInvoiceOrder($partner)->id,
        'partner_id' => $partner->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
        'balance_due' => 100,
        'status' => 'draft',
        'filename' => 'table-invoice.pdf',
        'file_path' => 'invoices/table-invoice.pdf',
    ]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('email', $invoice, [
            'email' => 'table@example.com',
            'message' => 'Please review.',
        ])
        ->assertNotified('Invoice Sent');

    expect($invoice->refresh()->status)->toBe('sent')
        ->and($invoice->email_recipient)->toBe('table@example.com')
        ->and($invoice->emailed_at)->not->toBeNull();
});

test('invoice download urls use the stored file path when present', function (): void {
    $url = app(InvoiceGeneratorService::class)->getInvoiceDownloadUrl(
        'custom/manuals/manual-invoice.pdf',
        'manual-invoice.pdf',
    );

    expect($url)->toContain('custom/manuals/manual-invoice.pdf')
        ->not->toContain('invoices/manual-invoice.pdf');
});

function releaseInvoiceOrder(Partner $partner): SalesOrder
{
    return SalesOrder::create([
        'partner_id' => $partner->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'order_date' => today(),
        'subtotal' => 100,
        'total' => 100,
        'status' => SalesOrder::STATUS_SUBMITTED,
    ]);
}

/** @param array{email: string} $partnerAttributes */
function releaseDraftInvoice(array $partnerAttributes): Invoice
{
    $partner = Partner::factory()->create($partnerAttributes);

    return Invoice::create([
        'invoice_type' => 'sales',
        'partner_id' => $partner->id,
        'order_type' => 'sales_order',
        'order_id' => releaseInvoiceOrder($partner)->id,
        'invoice_date' => today(),
        'due_date' => today()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
        'filename' => 'draft-invoice.pdf',
        'file_path' => 'invoices/draft-invoice.pdf',
        'status' => 'draft',
        'email_recipient' => null,
        'emailed_at' => null,
    ]);
}

test('high risk receivables aggregates multiple order balances without full group by errors', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $partner = Partner::create([
        'name' => 'Multi Order Customer',
        'email' => 'multi@example.com',
        'is_customer' => true,
    ]);

    $warehouse = Warehouse::factory()->create();

    $firstOrder = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now()->subDays(70),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total' => 1000,
        'status' => 'completed',
    ]);

    SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now()->subDays(55),
        'subtotal' => 500,
        'tax_amount' => 0,
        'total' => 500,
        'status' => 'completed',
    ]);

    Payment::withoutEvents(fn () => Payment::create([
        'payment_number' => 'PAY-000001',
        'partner_id' => $partner->id,
        'payment_date' => now()->subDays(10),
        'amount' => 300,
        'direction' => 'inbound',
        'method' => 'cash',
        'payable_type' => SalesOrder::class,
        'payable_id' => $firstOrder->id,
    ]));

    Livewire::test(HighRiskReceivables::class)
        ->assertCanSeeTableRecords([$partner])
        ->assertTableColumnStateSet('total_balance', '1200.0000', $partner);
});

test('the high risk receivables reminder action sends a reminder email to the customer', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Mail::fake();

    $partner = Partner::create([
        'name' => 'Aging Customer',
        'email' => 'aging@example.com',
        'phone' => '0922222222',
        'address' => 'Old Road',
        'is_customer' => true,
    ]);

    SalesOrder::create([
        'warehouse_id' => Warehouse::factory()->create()->id,
        'partner_id' => $partner->id,
        'order_date' => now()->subDays(60),
        'subtotal' => 200,
        'total' => 200,
        'status' => 'completed',
    ]);

    Livewire::test(HighRiskReceivables::class)
        ->callTableAction('remind', $partner);

    Mail::assertQueued(CustomerReminderMail::class, function (CustomerReminderMail $mail) use ($partner): bool {
        return $mail->hasTo($partner->email);
    });

    expect(EmailLog::count())->toBe(1);
});

test('activity logs are visible only to admins', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    expect(ActivityLogResource::canViewAny())->toBeFalse();

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    expect(ActivityLogResource::canViewAny())->toBeTrue();
});

test('release workflow notifications are queued after database commits', function (string $notificationClass): void {
    expect((new ReflectionClass($notificationClass))->newInstanceWithoutConstructor())
        ->toBeInstanceOf(ShouldQueueAfterCommit::class);
})->with([
    SalesOrderCreatedNotification::class,
    PurchaseOrderApprovedNotification::class,
    PaymentReceivedNotification::class,
    GoodsReceiptPostedNotification::class,
    LeaveRequestDecisionNotification::class,
]);
