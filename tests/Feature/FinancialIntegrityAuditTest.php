<?php

use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Policies\PaymentPolicy;
use App\Services\Accounting\CreatePaymentJournalEntry;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Support\SequentialNumber;
use App\UserRole;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function auditBank(float $balance = 1000): Bank
{
    return Bank::create([
        'name' => 'Audit Bank', 'code' => fake()->unique()->bothify('BANK-####'),
        'account_number' => fake()->unique()->numerify('##########'),
        'account_holder_name' => 'Pilot', 'bank_name' => 'Audit Bank', 'branch' => 'Main',
        'current_balance' => $balance, 'status' => 'active',
    ]);
}

test('a failed journal rolls back the payment and all journal records', function (): void {
    $bank = auditBank();
    $attributes = Payment::factory()->make(['method' => 'cpo', 'bank_id' => $bank->id, 'amount' => 100])->getAttributes();
    Notification::fake();
    $this->mock(CreatePaymentJournalEntry::class)->shouldReceive('handle')->once()->andReturnUsing(function (Payment $payment): void {
        JournalEntry::create(['date' => today(), 'reference' => 'FAIL', 'source_type' => Payment::class,
            'source_id' => $payment->id, 'total_debit' => 100, 'total_credit' => 100, 'status' => 'posted']);
        throw new RuntimeException('Simulated ledger failure');
    });
    expect(fn () => Payment::create($attributes))->toThrow(RuntimeException::class, 'Simulated ledger failure');
    expect(Payment::count())->toBe(0)->and(JournalEntry::count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(1000.0);
    Notification::assertNothingSent();
});

test('CPO payments post to the bank and can be reversed exactly once', function (): void {
    $bank = auditBank();
    $payment = Payment::factory()->create(['amount' => 100, 'method' => 'cpo', 'bank_id' => $bank->id,
        'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value]);
    expect((float) $bank->fresh()->current_balance)->toBe(900.0);
    expect(DB::table('bank_transactions')->where('source_id', $payment->id)->where('source_type', 'payment')->count())->toBe(1);
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Pilot correction');
    expect((float) $bank->fresh()->current_balance)->toBe(1000.0);
    expect(fn () => app(VoidPaymentJournalEntry::class)->handle($payment, 'Repeated correction'))->toThrow(RuntimeException::class);
    expect((float) $bank->fresh()->current_balance)->toBe(1000.0);
});

test('bank funded methods require a bank account', function (string $method): void {
    expect(fn () => Payment::factory()->create(['method' => $method, 'bank_id' => null, 'amount' => 100]))
        ->toThrow(RuntimeException::class, 'bank account');
    expect(Payment::count())->toBe(0);
})->with(['bank', 'bank_transfer', 'cheque', 'check', 'cpo']);

test('zero and negative payments are rejected', function (float $amount): void {
    expect(fn () => Payment::factory()->create(['method' => 'cash', 'amount' => $amount]))->toThrow(ValidationException::class);
    expect(Payment::count())->toBe(0);
})->with([0.0, -1.0]);

test('payment policies limit general financial access to finance and admin', function (): void {
    $payment = Payment::factory()->create(['amount' => 100]);
    $policy = new PaymentPolicy;
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->make(['role' => $role]);
        $allowed = in_array($role, [UserRole::Admin, UserRole::Finance], true);
        expect($policy->view($user, $payment))->toBe($allowed)
            ->and($policy->create($user))->toBe($allowed)
            ->and($policy->void($user, $payment))->toBe($allowed);
    }
});

test('number allocation reserves values before insertion and includes deleted and older high numbers', function (): void {
    $old = Payment::factory()->create(['payment_number' => 'PAY-009999', 'amount' => 100]);
    $old->delete();
    Payment::factory()->create(['payment_number' => 'PAY-000001', 'amount' => 100]);
    $next = fn (): string => SequentialNumber::next('payments', Payment::class, 'payment_number', 'PAY-', 6, 'PAY-%');
    expect($next())->toBe('PAY-010000')->and($next())->toBe('PAY-010001');
});

test('a failed bank update rolls back a previously posted journal and payment', function (): void {
    $bank = auditBank();
    DB::listen(function (QueryExecuted $event): void {
        if (str_starts_with(strtolower($event->sql), 'update "banks"')) {
            throw new RuntimeException('Simulated bank update failure');
        }
    });
    expect(fn () => Payment::factory()->create(['method' => 'cpo', 'bank_id' => $bank->id,
        'amount' => 100, 'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value]))
        ->toThrow(RuntimeException::class, 'Simulated bank update failure');
    expect(Payment::count())->toBe(0)->and(JournalEntry::count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(1000.0);
});

test('account factory codes are unique and cannot collide with system account codes', function (): void {
    $accounts = Account::factory()->count(100)->create();
    expect($accounts->pluck('code')->unique())->toHaveCount(100);
    expect($accounts->every(fn ($account): bool => str_starts_with($account->code, 'ACCT-')))->toBeTrue();
});
