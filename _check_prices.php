<?php
foreach (\App\Models\InventoryItem::take(5)->get() as $i) {
    echo $i->id . ' ' . $i->name
        . ' price=' . $i->price
        . ' avg=' . $i->average_cost
        . ' factor=' . $i->conversion_factor
        . ' ppu=' . $i->pricePerPurchaseUnit()
        . PHP_EOL;
}
