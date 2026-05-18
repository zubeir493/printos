<?php

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\Bank;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('payment form previews the next payment number', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $customer = Partner::factory()->create([
        'is_customer' => true,
    ]);

    Payment::create([
        'partner_id' => $customer->id,
        'payment_date' => now(),
        'amount' => 100,
        'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
        'method' => 'cash',
    ]);

    Livewire::test(CreatePayment::class)
        ->assertFormSet([
            'payment_number' => 'PAY-000002',
        ]);
});

test('payment form shows insufficient bank balance as a form error', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $bank = createPaymentFormBank('Low Balance Bank', 100);
    $supplier = Partner::factory()->create([
        'is_supplier' => true,
    ]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
            'partner_id' => $supplier->id,
            'amount' => 250,
            'payment_date' => now()->toDateString(),
            'method' => 'bank',
            'bank_id' => $bank->id,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'bank_id',
        ]);

    expect(Payment::count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(100.0);
});

function createPaymentFormBank(string $name, float $currentBalance): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->headline()->replace(' ', '-')->upper()->limit(20, '')->toString(),
        'account_number' => fake()->numerify('##########'),
        'account_holder_name' => 'PrintOS',
        'bank_name' => $name,
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
