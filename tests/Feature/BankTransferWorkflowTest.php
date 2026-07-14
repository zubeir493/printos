<?php

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\BankTransfers\Pages\CreateBankTransfer;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('bank transfer form previews the next transfer number', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    BankTransfer::create([
        'from_bank_id' => createBank('Source Bank')->id,
        'to_bank_id' => createBank('Destination Bank')->id,
        'amount' => 100,
        'transfer_date' => now(),
        'status' => 'pending',
    ]);

    Livewire::test(CreateBankTransfer::class)
        ->assertFormSet([
            'transfer_number' => 'BT-0002',
            'status' => 'pending',
        ]);
});

test('bank transfer completion moves balances when the source bank has enough funds', function (): void {
    $sourceBank = createBank('Funded Bank', currentBalance: 500);
    $destinationBank = createBank('Receiving Bank', currentBalance: 100);

    $transfer = BankTransfer::create([
        'from_bank_id' => $sourceBank->id,
        'to_bank_id' => $destinationBank->id,
        'amount' => 250,
        'transfer_date' => now(),
        'status' => 'pending',
    ]);

    $transfer->complete();

    expect($transfer->fresh()->status)->toBe('completed')
        ->and((float) $sourceBank->fresh()->current_balance)->toBe(250.0)
        ->and((float) $destinationBank->fresh()->current_balance)->toBe(350.0);
});

test('bank transfer completion is blocked when the source bank has insufficient funds', function (): void {
    $sourceBank = createBank('Low Balance Bank', currentBalance: 100);
    $destinationBank = createBank('Receiving Bank', currentBalance: 0);

    $transfer = BankTransfer::create([
        'from_bank_id' => $sourceBank->id,
        'to_bank_id' => $destinationBank->id,
        'amount' => 250,
        'transfer_date' => now(),
        'status' => 'pending',
    ]);

    expect(fn () => $transfer->complete())->toThrow(RuntimeException::class, 'Insufficient balance');

    expect($transfer->fresh()->status)->toBe('pending')
        ->and((float) $sourceBank->fresh()->current_balance)->toBe(100.0)
        ->and((float) $destinationBank->fresh()->current_balance)->toBe(0.0);
});

test('outbound bank payments are blocked when the bank has insufficient funds', function (): void {
    $bank = createBank('Supplier Payment Bank', currentBalance: 100);
    $supplier = Partner::factory()->create([
        'is_supplier' => true,
    ]);

    expect(fn () => Payment::create([
        'partner_id' => $supplier->id,
        'bank_id' => $bank->id,
        'payment_date' => now(),
        'amount' => 250,
        'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
        'method' => 'bank',
    ]))->toThrow(RuntimeException::class, 'Insufficient balance');

    expect((float) $bank->fresh()->current_balance)->toBe(100.0);
});

test('outbound bank payments reduce bank balance', function (): void {
    $bank = createBank('Funded Supplier Payment Bank', currentBalance: 500);
    $supplier = Partner::factory()->create([
        'is_supplier' => true,
    ]);

    Payment::create([
        'partner_id' => $supplier->id,
        'bank_id' => $bank->id,
        'payment_date' => now(),
        'amount' => 250,
        'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
        'method' => 'bank',
    ]);

    expect((float) $bank->fresh()->current_balance)->toBe(250.0);
});

function createBank(string $name, float $currentBalance = 1000): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->headline()->replace(' ', '-')->upper()->limit(20, '')->toString(),
        'account_number' => fake()->numerify('##########'),
        'account_holder_name' => 'Packledge',
        'bank_name' => $name,
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
