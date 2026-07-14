<?php

use App\Filament\Finance\Widgets\HighRiskReceivables;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Mail\CustomerReminderMail;
use App\Mail\InvoiceGenerated;
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

test('creating an invoice with send email enabled records the email and marks it as sent', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    config(['filesystems.private_disk' => 'local']);
    Storage::fake('local');
    Mail::fake();

    $partner = Partner::create([
        'name' => 'Walk-In Customer',
        'email' => 'walkin@example.com',
        'phone' => '0911111111',
        'address' => 'Main Street',
        'is_customer' => true,
    ]);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'invoice_type' => 'sales',
            'partner_id' => $partner->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'send_email' => true,
            'email_recipient' => 'walkin@example.com',
        ])
        ->call('create');

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    Mail::assertSent(InvoiceGenerated::class, function (InvoiceGenerated $mail) use ($partner): bool {
        return $mail->hasTo($partner->email);
    });

    expect($invoice->emailed_at)->not->toBeNull()
        ->and($invoice->email_recipient)->toBe('walkin@example.com')
        ->and($invoice->status)->toBe('sent')
        ->and(EmailLog::count())->toBe(1);
});

test('creating a manual invoice stores its generated pdf', function (): void {
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

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'invoice_type' => 'sales',
            'partner_id' => $partner->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 75,
            'total_amount' => 575,
            'send_email' => false,
        ])
        ->call('create');

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    Storage::disk('local')->assertExists($invoice->file_path);
});

test('creating an invoice with a failed email leaves the invoice unsent and shows a failure notification', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $this->mock(InvoiceGeneratorService::class, function ($mock): void {
        $mock->shouldReceive('generateManualInvoicePdf')
            ->once()
            ->andReturnTrue();
        $mock->shouldReceive('sendInvoiceEmail')
            ->once()
            ->andThrow(new RuntimeException('Mailtrap rejected the message.'));
    });

    $partner = Partner::create([
        'name' => 'Failure Customer',
        'email' => 'failure@example.com',
        'phone' => '0933333333',
        'address' => 'Main Street',
        'is_customer' => true,
    ]);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'invoice_type' => 'sales',
            'partner_id' => $partner->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 100,
            'tax_amount' => 15,
            'total_amount' => 115,
            'send_email' => true,
            'email_recipient' => 'failure@example.com',
        ])
        ->call('create')
        ->assertNotified('Email Failed');

    $invoice = Invoice::query()->latest('id')->firstOrFail();

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

test('emailing an invoice from the table marks open invoices as sent', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $this->mock(InvoiceGeneratorService::class, function ($mock): void {
        $mock->shouldReceive('getInvoicePath')
            ->andReturn('https://packledge.test/invoices/table-invoice.pdf');
        $mock->shouldReceive('getInvoiceDownloadUrl')
            ->andReturn('https://packledge.test/invoices/table-invoice.pdf');
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
