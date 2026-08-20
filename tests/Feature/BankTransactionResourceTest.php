<?php

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\BankTransfer;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    $inboundPayment = Payment::withoutEvents(fn () => Payment::create([
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

    $outboundPayment = Payment::withoutEvents(fn () => Payment::create([
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

    expect(BankTransaction::query()
        ->get(['source_type', 'source_id', 'transaction_number'])
        ->map(fn (BankTransaction $transaction): string => $transaction->source_type.'-'.$transaction->source_id.'-'.$transaction->transaction_number)
        ->all())->toContain(
            'payment-'.$inboundPayment->id.'-PAY-IN-001',
            'payment-'.$outboundPayment->id.'-PAY-OUT-001',
            'bank_transfer-1-BT-COMPLETE',
        )->not->toContain(
            'bank_transfer-2-BT-PENDING',
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

test('bank opening balance separates stored balance from transaction movement', function (): void {
    $bank = createBankTransactionResourceBank('Opening Bank', 1000);
    $customer = Partner::factory()->create([
        'is_customer' => true,
    ]);

    Payment::create([
        'payment_number' => 'PAY-OPEN-001',
        'partner_id' => $customer->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-05-01',
        'amount' => 250,
        'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
        'method' => 'bank',
    ]);

    $bank = $bank->fresh();

    expect((float) $bank->transaction_balance)->toBe(250.0)
        ->and((float) $bank->opening_balance)->toBe(1000.0)
        ->and((float) $bank->expected_balance)->toBe(1250.0)
        ->and((float) $bank->current_balance)->toBe(1250.0);

    DB::table('banks')->where('id', $bank->id)->update(['current_balance' => 999]);
    $bank = $bank->fresh();

    expect((float) $bank->calculated_balance)->toBe(1250.0);

    $bank->updateBalance();

    expect((float) $bank->fresh()->current_balance)->toBe(1250.0);
});

test('cheque payments and withholding reconcile stored and transaction balances', function (): void {
    $bank = createBankTransactionResourceBank('Cheque Bank', 1000);
    $supplier = Partner::factory()->create(['is_supplier' => true]);

    $payment = Payment::create([
        'partner_id' => $supplier->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-19',
        'amount' => 200,
        'withholding_amount' => 20,
        'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
        'method' => 'cheque',
    ]);

    expect((float) $bank->fresh()->current_balance)->toBe(820.0)
        ->and((float) $bank->fresh()->transaction_balance)->toBe(-180.0)
        ->and((float) $bank->fresh()->calculated_balance)->toBe(820.0);

    app(VoidPaymentJournalEntry::class)->handle($payment, 'Cheque cancelled');

    expect((float) $bank->fresh()->current_balance)->toBe(1000.0)
        ->and((float) $bank->fresh()->transaction_balance)->toBe(0.0)
        ->and((float) $bank->fresh()->calculated_balance)->toBe(1000.0);
});

function createBankTransactionResourceBank(string $name, float $currentBalance): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->headline()->replace(' ', '-')->upper()->limit(20, '')->toString(),
        'account_number' => fake()->unique()->numerify('##########'),
        'account_holder_name' => 'Packledge',
        'bank_name' => $name,
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
