<?php

use App\Filament\Finance\Widgets\HighRiskReceivables;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Mail\CustomerReminderMail;
use App\Mail\InvoiceGenerated;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Partner;
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
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('creating an invoice with send email enabled records the email and marks it as sent', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

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
            'status' => 'draft',
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
        ->and(EmailLog::count())->toBe(1);
});

test('creating an invoice with a failed email leaves the invoice unsent and shows a failure notification', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $this->mock(InvoiceGeneratorService::class, function ($mock): void {
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
            'status' => 'draft',
            'send_email' => true,
            'email_recipient' => 'failure@example.com',
        ])
        ->call('create')
        ->assertNotified('Email Failed');

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    expect($invoice->emailed_at)->toBeNull()
        ->and($invoice->email_recipient)->toBeNull()
        ->and(EmailLog::count())->toBe(0);
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
