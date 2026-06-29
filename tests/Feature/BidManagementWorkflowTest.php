<?php

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\Bids\BidResource;
use App\Filament\Resources\Bids\Pages\CreateBid;
use App\Filament\Resources\Bids\Pages\EditBid;
use App\Filament\Resources\Bids\Pages\ListBids;
use App\Filament\Resources\Bids\Pages\ViewBid;
use App\Filament\Resources\Bonds\Pages\ListBonds;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Bid;
use App\Models\Bond;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('finance can read and filter bids without mutation actions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $entity = Partner::factory()->customer()->create([
        'name' => 'Public Procurement Entity',
    ]);

    $bid = Bid::factory()->create([
        'title' => 'Annual Packaging Tender',
        'tender_reference' => 'TDR-2026-01',
        'partner_id' => $entity->id,
        'deadline_date' => '2026-06-15',
        'estimated_value' => 250000,
        'bid_bond_amount' => 5000,
        'status' => Bid::STATUS_DRAFT,
    ]);

    expect(BidResource::canCreate())->toBeFalse()
        ->and(BidResource::canEdit($bid))->toBeFalse()
        ->and(BidResource::canDelete($bid))->toBeFalse()
        ->and(BidResource::canDeleteAny())->toBeFalse();

    Livewire::test(ListBids::class)
        ->assertActionHidden('create')
        ->filterTable('status', Bid::STATUS_DRAFT)
        ->assertCanSeeTableRecords([$bid])
        ->assertTableActionHidden('edit', $bid)
        ->assertTableActionHidden('send_bond', $bid)
        ->filterTable('status', Bid::STATUS_AWARDED)
        ->assertCanNotSeeTableRecords([$bid]);

    $bondSentBid = Bid::factory()->create([
        'status' => Bid::STATUS_BOND_SENT,
    ]);
    $bondRecoveredBid = Bid::factory()->create([
        'status' => Bid::STATUS_BOND_RECOVERED,
    ]);

    Livewire::test(ListBids::class)
        ->filterTable('status', Bid::STATUS_BOND_SENT)
        ->assertCanSeeTableRecords([$bondSentBid])
        ->assertCanNotSeeTableRecords([$bid, $bondRecoveredBid])
        ->filterTable('status', Bid::STATUS_BOND_RECOVERED)
        ->assertCanSeeTableRecords([$bondRecoveredBid])
        ->assertCanNotSeeTableRecords([$bid, $bondSentBid]);

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->assertSet('data.title', 'Annual Packaging Tender')
        ->assertActionHidden('edit')
        ->assertActionHidden('send_bond');
});

test('bid bond amount cannot exceed estimated bid value', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Operations,
    ]));

    $entity = Partner::factory()->customer()->create();

    Livewire::test(CreateBid::class)
        ->set('data.title', 'Bond Limit Tender')
        ->set('data.partner_id', $entity->id)
        ->set('data.estimated_value', 1000)
        ->set('data.bid_bond_amount', 1001)
        ->call('create')
        ->assertHasFormErrors([
            'bid_bond_amount' => 'max',
        ]);

    expect(Bid::query()->where('title', 'Bond Limit Tender')->exists())->toBeFalse();
});

test('bids are editable only while draft', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Operations,
    ]));

    $draft = Bid::factory()->create([
        'status' => Bid::STATUS_DRAFT,
    ]);
    $submitted = Bid::factory()->create([
        'status' => Bid::STATUS_SUBMITTED,
    ]);

    expect(BidResource::canEdit($draft))->toBeTrue()
        ->and(BidResource::canEdit($submitted))->toBeFalse();

    Livewire::test(ViewBid::class, ['record' => $draft->id])
        ->assertActionVisible('edit');

    Livewire::test(ViewBid::class, ['record' => $submitted->id])
        ->assertActionHidden('edit');

    Livewire::test(ListBids::class)
        ->filterTable('status', Bid::STATUS_DRAFT)
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$submitted])
        ->assertTableActionVisible('edit', $draft);

    Livewire::test(ListBids::class)
        ->filterTable('status', Bid::STATUS_SUBMITTED)
        ->assertCanSeeTableRecords([$submitted])
        ->assertCanNotSeeTableRecords([$draft])
        ->assertTableActionHidden('edit', $submitted);
});

test('bid view groups header actions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $bid = Bid::factory()->create([
        'status' => Bid::STATUS_DRAFT,
        'bid_bond_amount' => 1000,
    ]);

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->assertActionExists('submit')
        ->assertActionExists('send_bond')
        ->assertActionExists('edit');
});

test('bid workflow actions manage status and submission date', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $bid = Bid::factory()->create([
        'status' => Bid::STATUS_DRAFT,
        'submission_date' => null,
    ]);

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->callAction('submit')
        ->assertNotified();

    expect($bid->refresh()->status)->toBe(Bid::STATUS_SUBMITTED)
        ->and($bid->submission_date?->toDateString())->toBe(now()->toDateString());

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->callAction('award')
        ->assertNotified();

    expect($bid->refresh()->status)->toBe(Bid::STATUS_AWARDED);
});

test('bid table workflow actions manage status', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $bid = Bid::factory()->create([
        'status' => Bid::STATUS_DRAFT,
        'submission_date' => null,
    ]);

    Livewire::test(ListBids::class)
        ->callTableAction('submit', $bid)
        ->assertNotified();

    expect($bid->refresh()->status)->toBe(Bid::STATUS_SUBMITTED)
        ->and($bid->submission_date?->toDateString())->toBe(now()->toDateString());

    Livewire::test(ListBids::class)
        ->callTableAction('mark_lost', $bid)
        ->assertNotified();

    expect($bid->refresh()->status)->toBe(Bid::STATUS_LOST);
});

test('bid bond issue posts receivable accounting and decreases bank balance', function (): void {
    $bank = createBidManagementBank(10000);
    $bond = Bond::factory()->bid()->create([
        'amount' => 2500,
    ]);

    $payment = $bond->issue('2026-06-02', 'bank', $bank->id, 'Bond issue receipt');

    $journalEntry = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();

    $receivable = Account::query()->where('code', Account::CODE_BID_BONDS_RECEIVABLE)->firstOrFail();

    expect($payment->transaction_type)->toBe(PaymentTransactionType::BID_BOND_ISSUE->value)
        ->and($payment->direction)->toBe('outbound')
        ->and((float) $bank->fresh()->current_balance)->toBe(7500.0)
        ->and($bond->fresh()->status)->toBe(Bond::STATUS_ACTIVE);

    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $receivable->id,
        'debit' => 2500,
        'credit' => 0,
    ]);
});

test('bid bond recovery posts receivable accounting and increases bank balance', function (): void {
    $bank = createBidManagementBank(10000);
    $bond = Bond::factory()->bid()->create([
        'amount' => 2500,
    ]);

    $bond->issue('2026-06-02', 'bank', $bank->id);
    $payment = $bond->fresh()->recover('2026-06-10', 'bank', $bank->id, 'Bond recovered');

    $journalEntry = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();

    $receivable = Account::query()->where('code', Account::CODE_BID_BONDS_RECEIVABLE)->firstOrFail();

    expect($payment->transaction_type)->toBe(PaymentTransactionType::BID_BOND_RECOVERY->value)
        ->and($payment->direction)->toBe('inbound')
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0)
        ->and($bond->fresh()->status)->toBe(Bond::STATUS_RECOVERED);

    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $receivable->id,
        'debit' => 0,
        'credit' => 2500,
    ]);
});

test('cpo bond issue and recovery store cpo bank without moving internal bank balance', function (): void {
    $bank = createBidManagementBank(10000);
    $bond = Bond::factory()->bid()->create([
        'amount' => 3000,
    ]);

    $issuePayment = $bond->issue('2026-06-02', 'cpo', cpoBankName: 'Awash Bank');

    expect($issuePayment->method)->toBe('cpo')
        ->and($issuePayment->payable_type)->toBe(Bond::class)
        ->and($issuePayment->bank_id)->toBeNull()
        ->and($bond->fresh()->cpo_bank_name)->toBe('Awash Bank')
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);

    $recoveryPayment = $bond->fresh()->recover('2026-06-10', 'cpo', cpoBankName: 'Awash Bank');

    expect($recoveryPayment->method)->toBe('cpo')
        ->and($recoveryPayment->payable_type)->toBe(Bond::class)
        ->and($recoveryPayment->bank_id)->toBeNull()
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);
});

test('bid bond issue and recovery cannot be duplicated', function (): void {
    $bank = createBidManagementBank(10000);
    $bond = Bond::factory()->bid()->create([
        'amount' => 2500,
    ]);

    $bond->issue('2026-06-02', 'bank', $bank->id);

    expect(fn () => $bond->fresh()->issue('2026-06-03', 'bank', $bank->id))
        ->toThrow(RuntimeException::class, 'already been issued');

    $bond->fresh()->recover('2026-06-10', 'bank', $bank->id);

    expect(fn () => $bond->fresh()->recover('2026-06-11', 'bank', $bank->id))
        ->toThrow(RuntimeException::class, 'already been recovered');
});

test('awarded bid can send and return performance bond from bid actions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $bank = createBidManagementBank(10000);
    $entity = Partner::factory()->customer()->create();
    $bid = Bid::factory()->create([
        'partner_id' => $entity->id,
        'status' => Bid::STATUS_AWARDED,
    ]);

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->callAction('send_performance_bond', [
            'amount' => 2500,
            'method' => 'cpo',
            'cpo_bank_name' => 'CBE',
            'payment_date' => '2026-06-12',
            'notes' => 'Performance bond note',
        ])
        ->assertNotified();

    $bond = $bid->refresh()->currentPerformanceBond();
    $payment = $bond->issuePayment;
    $receivable = Account::query()->where('code', Account::CODE_PERFORMANCE_BONDS_RECEIVABLE)->firstOrFail();
    $journalEntry = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();

    expect($bond)->not->toBeNull()
        ->and($bid->refresh()->status)->toBe(Bid::STATUS_BOND_SENT)
        ->and($bond->type)->toBe(Bond::TYPE_PERFORMANCE)
        ->and($bond->issuing_partner_id)->toBe($entity->id)
        ->and($bond->cpo_bank_name)->toBe('CBE')
        ->and($payment->transaction_type)->toBe(PaymentTransactionType::PERFORMANCE_BOND_ISSUE->value)
        ->and($payment->method)->toBe('cpo')
        ->and($payment->bank_id)->toBeNull()
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);

    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $receivable->id,
        'debit' => 2500,
        'credit' => 0,
    ]);

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->callAction('return_performance_bond', [
            'payment_date' => '2026-06-20',
        ])
        ->assertNotified();

    expect($bond->fresh()->status)->toBe(Bond::STATUS_RECOVERED)
        ->and($bid->refresh()->status)->toBe(Bid::STATUS_BOND_RECOVERED)
        ->and($bond->fresh()->recoveryPayment->transaction_type)->toBe(PaymentTransactionType::PERFORMANCE_BOND_RECOVERY->value)
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);
});

test('bid resource can send and return bid bond from bid actions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $bank = createBidManagementBank(10000);
    $entity = Partner::factory()->customer()->create();
    $bid = Bid::factory()->create([
        'partner_id' => $entity->id,
        'status' => Bid::STATUS_DRAFT,
        'submission_date' => null,
        'estimated_value' => 100000,
        'bid_bond_amount' => 2000,
    ]);

    Livewire::test(EditBid::class, ['record' => $bid->id])
        ->callAction('send_bond', [
            'method' => 'cpo',
            'cpo_bank_name' => 'CBE',
            'payment_date' => '2026-06-02',
            'reference' => 'Sent from bid page',
        ])
        ->assertNotified();

    $bond = $bid->refresh()->currentBidBond();

    expect($bid->status)->toBe(Bid::STATUS_SUBMITTED)
        ->and($bid->submission_date?->toDateString())->toBe(now()->toDateString())
        ->and($bond)->not->toBeNull()
        ->and($bond->issuing_partner_id)->toBe($entity->id)
        ->and($bond->status)->toBe(Bond::STATUS_ACTIVE)
        ->and($bond->cpo_bank_name)->toBe('CBE')
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);

    Livewire::test(ViewBid::class, ['record' => $bid->id])
        ->callAction('return_bond', [
            'payment_date' => '2026-06-10',
            'reference' => 'Returned from bid page',
        ])
        ->assertNotified();

    expect($bid->refresh()->status)->toBe(Bid::STATUS_LOST)
        ->and($bond->fresh()->status)->toBe(Bond::STATUS_RECOVERED)
        ->and($bond->fresh()->recoveryPayment->method)->toBe('cpo')
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);
});

test('bid table can send and return bid bond', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $bank = createBidManagementBank(10000);
    $entity = Partner::factory()->customer()->create();
    $bid = Bid::factory()->create([
        'partner_id' => $entity->id,
        'status' => Bid::STATUS_DRAFT,
        'submission_date' => null,
        'bid_bond_amount' => 1500,
    ]);

    Livewire::test(ListBids::class)
        ->callTableAction('send_bond', $bid, [
            'method' => 'bank',
            'bank_id' => $bank->id,
            'payment_date' => '2026-06-02',
            'reference' => 'Table sent',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $bond = $bid->refresh()->currentBidBond();

    expect($bid->status)->toBe(Bid::STATUS_SUBMITTED)
        ->and($bond)->not->toBeNull()
        ->and($bond->issuing_partner_id)->toBe($entity->id)
        ->and((float) $bank->fresh()->current_balance)->toBe(8500.0);

    Livewire::test(ListBids::class)
        ->callTableAction('return_bond', $bid, [
            'method' => 'bank',
            'bank_id' => $bank->id,
            'payment_date' => '2026-06-10',
            'reference' => 'Table returned',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    expect($bid->refresh()->status)->toBe(Bid::STATUS_LOST)
        ->and($bond->fresh()->status)->toBe(Bond::STATUS_RECOVERED)
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);
});

test('bid actions are hidden outside valid states', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $draftWithoutAmount = Bid::factory()->create([
        'status' => Bid::STATUS_DRAFT,
        'bid_bond_amount' => 0,
    ]);
    $submitted = Bid::factory()->create([
        'status' => Bid::STATUS_SUBMITTED,
        'bid_bond_amount' => 1000,
    ]);
    $awarded = Bid::factory()->create([
        'status' => Bid::STATUS_AWARDED,
        'bid_bond_amount' => 1000,
    ]);
    $bondSent = Bid::factory()->create([
        'status' => Bid::STATUS_BOND_SENT,
    ]);
    $activePerformanceBond = $bondSent->performanceBonds()->create([
        'type' => Bond::TYPE_PERFORMANCE,
        'issuing_partner_id' => $bondSent->partner_id,
        'amount' => 1000,
        'status' => Bond::STATUS_ACTIVE,
        'issue_payment_id' => Payment::factory()->create()->id,
    ]);
    $bondRecovered = Bid::factory()->create([
        'status' => Bid::STATUS_BOND_RECOVERED,
    ]);

    Livewire::test(EditBid::class, ['record' => $draftWithoutAmount->id])
        ->assertActionHidden('send_bond')
        ->assertActionHidden('award')
        ->assertActionHidden('mark_lost');

    Livewire::test(ViewBid::class, ['record' => $submitted->id])
        ->assertActionHidden('submit')
        ->assertActionHidden('send_bond')
        ->assertActionVisible('award')
        ->assertActionVisible('mark_lost');

    Livewire::test(ViewBid::class, ['record' => $awarded->id])
        ->assertActionHidden('submit')
        ->assertActionHidden('award')
        ->assertActionHidden('mark_lost')
        ->assertActionVisible('send_performance_bond')
        ->assertActionHidden('return_performance_bond');

    Livewire::test(ViewBid::class, ['record' => $bondSent->id])
        ->assertActionHidden('send_performance_bond')
        ->assertActionVisible('return_performance_bond');

    Livewire::test(ViewBid::class, ['record' => $bondRecovered->id])
        ->assertActionHidden('send_performance_bond')
        ->assertActionHidden('return_performance_bond');

    expect($activePerformanceBond->exists)->toBeTrue();
});

test('bid table actions are hidden outside valid states', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $draftWithoutAmount = Bid::factory()->create([
        'status' => Bid::STATUS_DRAFT,
        'bid_bond_amount' => 0,
    ]);
    $submitted = Bid::factory()->create([
        'status' => Bid::STATUS_SUBMITTED,
        'bid_bond_amount' => 1000,
    ]);
    $awarded = Bid::factory()->create([
        'status' => Bid::STATUS_AWARDED,
        'bid_bond_amount' => 1000,
    ]);
    $bondSent = Bid::factory()->create([
        'status' => Bid::STATUS_BOND_SENT,
    ]);
    $bondSent->performanceBonds()->create([
        'type' => Bond::TYPE_PERFORMANCE,
        'issuing_partner_id' => $bondSent->partner_id,
        'amount' => 1000,
        'status' => Bond::STATUS_ACTIVE,
        'issue_payment_id' => Payment::factory()->create()->id,
    ]);
    $bondRecovered = Bid::factory()->create([
        'status' => Bid::STATUS_BOND_RECOVERED,
    ]);

    Livewire::test(ListBids::class)
        ->assertTableActionHidden('edit', $submitted)
        ->assertTableActionHidden('send_bond', $draftWithoutAmount)
        ->assertTableActionHidden('award', $draftWithoutAmount)
        ->assertTableActionHidden('mark_lost', $draftWithoutAmount)
        ->assertTableActionHidden('submit', $submitted)
        ->assertTableActionHidden('send_bond', $submitted)
        ->assertTableActionVisible('award', $submitted)
        ->assertTableActionVisible('mark_lost', $submitted)
        ->assertTableActionHidden('submit', $awarded)
        ->assertTableActionHidden('award', $awarded)
        ->assertTableActionHidden('mark_lost', $awarded)
        ->assertTableActionVisible('send_performance_bond', $awarded)
        ->assertTableActionHidden('return_performance_bond', $awarded)
        ->assertTableActionHidden('send_performance_bond', $bondSent)
        ->assertTableActionVisible('return_performance_bond', $bondSent)
        ->assertTableActionHidden('send_performance_bond', $bondRecovered)
        ->assertTableActionHidden('return_performance_bond', $bondRecovered);
});

test('bond tracker lists bonds and returns active bonds', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $bank = createBidManagementBank(10000);
    $bidBond = Bond::factory()->bid()->create([
        'amount' => 1500,
    ]);
    $performanceBond = Bond::factory()->performance()->create([
        'amount' => 2000,
    ]);

    $performanceBond->issue('2026-06-12', 'cpo', cpoBankName: 'CBE');

    Livewire::test(ListBonds::class)
        ->assertCanSeeTableRecords([$bidBond, $performanceBond])
        ->assertTableActionVisible('return_bond', $performanceBond)
        ->assertTableActionHidden('return_bond', $bidBond)
        ->filterTable('type', Bond::TYPE_PERFORMANCE)
        ->assertCanSeeTableRecords([$performanceBond])
        ->assertCanNotSeeTableRecords([$bidBond])
        ->callTableAction('return_bond', $performanceBond, [
            'payment_date' => '2026-06-20',
            'reference' => 'Tracker returned',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    expect($performanceBond->fresh()->status)->toBe(Bond::STATUS_RECOVERED)
        ->and($performanceBond->fresh()->recoveryPayment->method)->toBe('cpo')
        ->and((float) $bank->fresh()->current_balance)->toBe(10000.0);
});

function createBidManagementBank(float $currentBalance): Bank
{
    return Bank::create([
        'name' => 'Bid Bond Bank',
        'code' => fake()->unique()->bothify('BBB-####'),
        'account_number' => fake()->unique()->numerify('##########'),
        'account_holder_name' => 'PrintOS',
        'bank_name' => 'Bid Bond Bank',
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
