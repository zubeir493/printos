<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Support\WarehouseAccess;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PostDirectGoodsReceipt
{
    public static function allows(?User $user): bool
    {
        return in_array($user?->role, [UserRole::Admin, UserRole::Operations, UserRole::Warehouse], true);
    }

    /** @param array<string, mixed> $data */
    public function handle(array $data): GoodsReceipt
    {
        abort_unless(self::allows(auth()->user()), 403);

        $data = Validator::make($data, [
            'source' => ['required', Rule::in(['supplier', 'opening_stock'])],
            'supplier_id' => ['exclude_unless:source,supplier', 'required', Rule::exists('partners', 'id')->where('is_supplier', true)],
            'source_reference' => ['required', 'string', 'max:120'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array:inventory_item_id,quantity_received'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
        ])->validate();

        WarehouseAccess::authorize((int) $data['warehouse_id']);

        return DB::transaction(function () use ($data): GoodsReceipt {
            $receipt = GoodsReceipt::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'source_reference' => trim($data['source_reference']),
                'warehouse_id' => $data['warehouse_id'],
                'receipt_date' => $data['receipt_date'],
                'status' => 'draft',
            ]);

            $receipt->items()->createMany($data['items']);
            $receipt->update(['status' => 'posted']);

            return $receipt->refresh();
        }, 3);
    }
}
