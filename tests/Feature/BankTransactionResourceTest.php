<?php

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\BankTransfer;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('bank transaction resource lists balance affecting payments and completed transfers', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $operatingBank = createBankTransactionResourceBank('Operating Bank', 1000);
    $savingsBank = createBankTransactionResourceBank('Savings Bank', 500);
    $customer = Partner::factory()->create([
        'name' => 'Acme Customer',
        'is_customer' => true,
    ]);
    $supplier = Partner::factory()->create([
        'name' => 'Paper Supplier',
        'is_supplier' => true,
    ]);

    Payment::withoutEvents(fn () => Payment::create([
        'payment_number' => 'PAY-IN-001',
        'partner_id' => $customer->id,
        'bank_id' => $operatingBank->id,
        'payment_date' => '2026-05-01',
        'amount' => 300,
        'direction' => 'inbound',
        'method' => 'bank',
        'reference' => 'Customer deposit',
        'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
        'payment_type' => 'standard',
    ]));

    Payment::withoutEvents(fn () => Payment::create([
        'payment_number' => 'PAY-OUT-001',
        'partner_id' => $supplier->id,
        'bank_id' => $operatingBank->id,
        'payment_date' => '2026-05-02',
        'amount' => 125,
        'direction' => 'outbound',
        'method' => 'bank',
        'reference' => 'Supplier payment',
        'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
        'payment_type' => 'standard',
    ]));

    BankTransfer::create([
        'transfer_number' => 'BT-COMPLETE',
        'from_bank_id' => $operatingBank->id,
        'to_bank_id' => $savingsBank->id,
        'amount' => 200,
        'transfer_date' => '2026-05-03',
        'status' => 'completed',
    ]);

    BankTransfer::create([
        'transfer_number' => 'BT-PENDING',
        'from_bank_id' => $operatingBank->id,
        'to_bank_id' => $savingsBank->id,
        'amount' => 75,
        'transfer_date' => '2026-05-04',
        'status' => 'pending',
    ]);

    expect(BankTransaction::query()->pluck('id')->all())->toContain(
        'payment-1',
        'payment-2',
        'bank-transfer-out-1',
        'bank-transfer-in-1',
    )->not->toContain(
        'bank-transfer-out-2',
        'bank-transfer-in-2',
    );

    Livewire::test(ListBankTransactions::class)
        ->assertSee('PAY-IN-001')
        ->assertSee('PAY-OUT-001')
        ->assertSee('BT-COMPLETE')
        ->assertDontSee('BT-PENDING')
        ->assertSee('Operating Bank')
        ->assertSee('Savings Bank')
        ->assertSee('Acme Customer')
        ->assertSee('Paper Supplier');
});

function createBankTransactionResourceBank(string $name, float $currentBalance): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->headline()->replace(' ', '-')->upper()->limit(20, '')->toString(),
        'account_number' => fake()->unique()->numerify('##########'),
        'account_holder_name' => 'PrintOS',
        'bank_name' => $name,
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
