<?php

use App\Filament\Finance\Pages\ReceivablesAgingReport;
use App\Filament\Finance\Widgets\FinancePanelStats;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Widgets\StockOverviewStats;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\PaymentReceivedNotification;
use App\Services\Accounting\ReceivablesQuery;
use App\Support\Money;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('receivables agree across customer balances dashboard and aging and ignore invalid documents and payments', function (): void {
    $partner = Partner::factory()->create(['is_customer' => true]);
    $warehouse = Warehouse::factory()->create();
    $sale = SalesOrder::withoutEvents(fn (): SalesOrder => SalesOrder::create([
        'order_number' => 'SO-AUDIT', 'warehouse_id' => $warehouse->id, 'partner_id' => $partner->id,
        'order_date' => '2026-01-01', 'due_date' => '2026-02-01', 'total' => 100, 'status' => 'submitted',
    ]));
    $job = JobOrder::factory()->create(['partner_id' => $partner->id, 'submission_date' => '2026-01-01', 'status' => 'design', 'subtotal' => 200, 'tax_amount' => 0, 'total' => 200]);
    JobOrder::factory()->create(['partner_id' => $partner->id, 'status' => 'draft', 'total' => 1000]);
    JobOrder::factory()->create(['partner_id' => $partner->id, 'production_mode' => 'make_to_stock', 'status' => 'production', 'total' => 1000]);
    $voidSale = $sale->replicate()->fill(['order_number' => 'SO-VOID', 'status' => 'void']);
    SalesOrder::withoutEvents(fn () => $voidSale->save());
    foreach ([[$sale, 25, null, null], [$job, 50, null, null], [$job, 40, now(), null], [$job, 30, null, now()]] as [$document, $amount, $voidedAt, $deletedAt]) {
        Payment::withoutEvents(fn () => Payment::factory()->create(['partner_id' => $partner->id, 'payable_type' => $document::class,
            'payable_id' => $document->id, 'amount' => $amount, 'direction' => 'inbound', 'method' => 'cash',
            'payment_date' => '2026-01-10', 'voided_at' => $voidedAt, 'deleted_at' => $deletedAt]));
    }
    $query = app(ReceivablesQuery::class);
    expect($query->total('2026-01-31'))->toBe(225.0)
        ->and((float) $query->customerBalances('2026-01-31')->first()->total_balance)->toBe(225.0);
    $page = new ReceivablesAgingReport;
    $page->asOfDate = '2026-01-31';
    $rows = $query->documents('2026-01-31')->get();
    expect($rows->modelKeys())->toHaveCount(2)->and(array_unique($rows->modelKeys()))->toHaveCount(2);
    foreach ($rows as $row) {
        expect((int) $row->age_days)->toBe($page->ageDays($row))->and($row->bucket)->toBe($page->bucket($row));
    }
    expect($page->report()['total'])->toBe(225.0)->and($query->total('2026-01-05'))->toBe(300.0);
});

test('finance cash flow excludes void payments and withholding', function (): void {
    foreach ([[100, 20, null], [40, 0, now()]] as [$amount, $withholding, $voidedAt]) {
        Payment::withoutEvents(fn () => Payment::factory()->create(['amount' => $amount, 'withholding_amount' => $withholding,
            'direction' => 'inbound', 'method' => 'cash', 'voided_at' => $voidedAt, 'payment_date' => today()]));
    }
    $widget = new class extends FinancePanelStats
    {
        public function stats(): array
        {
            return $this->getStats();
        }
    };
    expect($widget->stats()[0]->getValue())->toBe(Money::abbreviate(80, precision: 2));
});

test('job payment totals are loaded once and do not query while rendering row values', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    JobOrder::factory()->count(20)->create();
    $jobs = JobOrderResource::getEloquentQuery()->get();
    DB::enableQueryLog();
    DB::flushQueryLog();
    foreach ($jobs as $job) {
        expect($job->paid_amount)->toBe(0.0)->and($job->balance)->toBe((float) $job->total);
        $job->paid_amount;
        $job->balance;
    }
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

test('stock warnings use configured thresholds and do not invent chart histories', function (): void {
    $warehouse = Warehouse::factory()->create();
    foreach ([[50, 100], [5, 1], [0, 10]] as [$quantity, $threshold]) {
        $item = InventoryItem::factory()->create(['low_stock_threshold' => $threshold]);
        InventoryBalance::factory()->create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => $quantity]);
    }
    $widget = new class extends StockOverviewStats
    {
        public function stats(): array
        {
            return $this->getStats();
        }
    };
    $widget->warehouse_id = $warehouse->id;
    $stats = $widget->stats();
    expect($stats[2]->getValue())->toBe(1)->and($stats[3]->getValue())->toBe(1)
        ->and($stats[0]->getChart())->toBeNull()->and($stats[1]->getChart())->toBeNull();
});

test('payment notifications use the configured currency', function (): void {
    Setting::getSettings()->update(['currency_code' => 'USD']);
    $payment = Payment::factory()->create(['amount' => 125]);
    $user = User::factory()->create();
    $message = (new PaymentReceivedNotification($payment))->toDatabase($user);
    expect($message['body'])->toContain('125.00 USD')->not->toContain('Birr');
});
