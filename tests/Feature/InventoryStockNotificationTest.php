<?php

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('low stock notification is sent when stock crosses threshold', function () {
    Notification::fake();

    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();
    $user->warehouses()->attach($warehouse);

    $item = InventoryItem::factory()->create([
        'low_stock_threshold' => 10,
    ]);

    // Initial stock above threshold
    $balance = InventoryBalance::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 20,
    ]);

    // Reduce stock to below threshold
    StockMovement::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => -15, // New qty = 5
    ]);

    Notification::assertSentTo(
        [$user],
        LowStockNotification::class,
        fn ($notification) => $notification->toDatabase($user)['title'] === 'Low Stock Warning'
    );
});

test('stock finished notification is sent when stock reaches zero', function () {
    Notification::fake();

    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();
    $user->warehouses()->attach($warehouse);

    $item = InventoryItem::factory()->create([
        'low_stock_threshold' => 10,
    ]);

    // Initial stock
    $balance = InventoryBalance::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 5,
    ]);

    // Reduce stock to zero
    StockMovement::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => -5, // New qty = 0
    ]);

    Notification::assertSentTo(
        [$user],
        LowStockNotification::class,
        fn ($notification) => $notification->toDatabase($user)['title'] === 'Stock Finished'
    );
});

test('notifications are only sent to users associated with the warehouse', function () {
    Notification::fake();

    $warehouse1 = Warehouse::factory()->create();
    $warehouse2 = Warehouse::factory()->create();

    $user1 = User::factory()->create();
    $user1->warehouses()->attach($warehouse1);

    $user2 = User::factory()->create();
    $user2->warehouses()->attach($warehouse2);

    $item = InventoryItem::factory()->create(['low_stock_threshold' => 10]);

    // Trigger low stock in warehouse 1
    InventoryBalance::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse1->id,
        'quantity_on_hand' => 20,
    ]);

    StockMovement::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse1->id,
        'quantity' => -15,
    ]);

    Notification::assertSentTo($user1, LowStockNotification::class);
    Notification::assertNotSentTo($user2, LowStockNotification::class);
});
