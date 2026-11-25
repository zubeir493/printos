<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\PrivateStorage;
use App\Support\WarehouseAccess;
use App\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $reference = trim($data['source_reference']);
        $supplierId = $data['supplier_id'] ?? null;
        $key = hash('sha256', implode('|', [$data['warehouse_id'], $supplierId ?? 'opening', Str::lower($reference)]));
        try {
            return DB::transaction(function () use ($data, $reference, $supplierId, $key): GoodsReceipt {
                $existing = GoodsReceipt::query()->whereNull('purchase_order_id')->whereNull('reversed_at')
                    ->where('warehouse_id', $data['warehouse_id'])->where('supplier_id', $supplierId)
                    ->whereRaw('LOWER(TRIM(source_reference)) = ?', [Str::lower($reference)])->exists();
                if ($existing) {
                    throw ValidationException::withMessages(['source_reference' => 'This reference has already been received for this supplier and warehouse.']);
                }
                $receipt = GoodsReceipt::create([
                    'supplier_id' => $supplierId,
                    'source_reference' => $reference,
                    'direct_reference_key' => $key,
                    'warehouse_id' => $data['warehouse_id'],
                    'receipt_date' => $data['receipt_date'],
                    'status' => 'draft',
                ]);

                $receipt->items()->createMany($data['items']);
                $receipt->update(['status' => 'posted']);

                return $receipt->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (GoodsReceipt::query()->where('direct_reference_key', $key)->exists()) {
                throw ValidationException::withMessages(['source_reference' => 'This reference has already been received for this supplier and warehouse.']);
            }
            throw $exception;
        }
    }

    public function reverse(GoodsReceipt $receipt, string $reason): void
    {
        $actor = auth()->user();
        abort_unless(in_array($actor?->role, [UserRole::Admin, UserRole::Operations], true), 403);
        Validator::validate(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($receipt, $reason, $actor): void {
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            WarehouseAccess::authorize((int) $locked->warehouse_id);
            if ($locked->purchase_order_id !== null || ! $locked->posted_at || $locked->reversed_at || $locked->supplierBill()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Only posted direct receipts without a supplier bill can be reversed here.']);
            }
            $originals = StockMovement::query()->where('reference_type', GoodsReceipt::class)
                ->where('reference_id', $locked->id)->where('type', GoodsReceipt::DIRECT_MOVEMENT)
                ->orderBy('inventory_item_id')->lockForUpdate()->get();
            if ($originals->isEmpty() || $originals->contains(fn (StockMovement $movement): bool => $movement->unit_cost !== null)) {
                throw ValidationException::withMessages(['reason' => 'This receipt has no quantity-only movements to reverse.']);
            }
            foreach ($originals as $movement) {
                $available = InventoryBalance::query()->where('warehouse_id', $movement->warehouse_id)
                    ->where('inventory_item_id', $movement->inventory_item_id)->lockForUpdate()->value('quantity_on_hand');
                if ((float) $available < (float) $movement->quantity) {
                    throw ValidationException::withMessages(['reason' => 'Reversing this receipt would make stock negative. Resolve any subsequent movements first.']);
                }
                StockMovement::create([
                    'inventory_item_id' => $movement->inventory_item_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'type' => GoodsReceipt::REVERSAL_MOVEMENT,
                    'quantity' => -(float) $movement->quantity,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $locked->id,
                    'movement_date' => now(),
                ]);
            }
            $locked->forceFill(['reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $reason, 'direct_reference_key' => null])->save();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordSupplierBill(GoodsReceipt $receipt, array $data): Invoice
    {
        abort_unless(in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Finance], true), 403);
        $data = Validator::validate($data, [
            'bill_number' => ['required', 'string', 'max:120'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'tax_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'document' => ['required', 'string', 'regex:/^invoices\/supplier-bills\/[a-zA-Z0-9-]+\.pdf$/i'],
        ]);
        if (! Storage::disk(PrivateStorage::diskName())->exists($data['document'])) {
            throw ValidationException::withMessages(['document' => 'Attach the supplier bill PDF.']);
        }
        $total = round((float) $data['subtotal'] + (float) $data['tax_amount'], 2);
        if ($total > 9999999999.99) {
            throw ValidationException::withMessages(['subtotal' => 'The bill total exceeds the supported amount.']);
        }

        return DB::transaction(function () use ($receipt, $data, $total): Invoice {
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->purchase_order_id !== null || ! $locked->posted_at || $locked->reversed_at || ! $locked->supplier_id
                || $locked->supplierBill()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['bill_number' => 'Only an active direct supplier receipt without a matched bill can be matched.']);
            }
            Partner::query()->lockForUpdate()->findOrFail($locked->supplier_id);
            $billNumber = trim($data['bill_number']);
            $billKey = Str::lower($billNumber);
            if (Invoice::withTrashed()->where('partner_id', $locked->supplier_id)
                ->whereNotNull('goods_receipt_id')->where('options->supplier_bill_key', $billKey)->exists()) {
                throw ValidationException::withMessages(['bill_number' => 'This supplier bill number has already been matched.']);
            }

            return Invoice::create([
                'invoice_type' => 'purchase',
                'order_type' => 'purchase_order',
                'goods_receipt_id' => $locked->id,
                'partner_id' => $locked->supplier_id,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $data['subtotal'],
                'tax_amount' => $data['tax_amount'],
                'total_amount' => $total,
                'status' => 'draft',
                'file_path' => $data['document'],
                'filename' => basename($data['document']),
                'options' => ['supplier_bill_number' => $billNumber, 'supplier_bill_key' => $billKey],
            ]);
        }, 3);
    }

    public function unmatchSupplierBill(GoodsReceipt $receipt, string $reason): void
    {
        $actor = auth()->user();
        abort_unless(in_array($actor?->role, [UserRole::Admin, UserRole::Finance], true), 403);
        Validator::validate(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($receipt, $reason, $actor): void {
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $bill = $locked->supplierBill()->withTrashed()->lockForUpdate()->first();
            if (! $bill || ! in_array($bill->status, ['draft', 'cancelled'], true) || $bill->payments()->exists()
                || JournalEntry::query()->where('source_type', Invoice::class)->where('source_id', $bill->id)->exists()) {
                throw ValidationException::withMessages(['reason' => 'Only unposted bills without payments can be unmatched.']);
            }

            $bill->update([
                'goods_receipt_id' => null,
                'status' => 'cancelled',
                'options' => [...($bill->options ?? []), 'unmatched_receipt_id' => $locked->id, 'unmatch_reason' => $reason],
            ]);
            activity('goods_receipt')->performedOn($locked)->causedBy($actor)
                ->withProperties(['invoice_id' => $bill->id, 'reason' => $reason])->log('Supplier bill unmatched');
        }, 3);
    }
}
