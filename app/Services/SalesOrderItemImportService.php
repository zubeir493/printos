<?php

namespace App\Services;

use App\Models\InventoryItem;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class SalesOrderItemImportService
{
    public function importRows(string $filePath): array
    {
        $reader = ReaderFactory::createFromFile($filePath);
        $reader->open($filePath);

        $rows = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                if ($headers === null) {
                    $headers = array_map([$this, 'normalizeHeader'], $values);

                    continue;
                }

                $mapped = $this->mapRow($headers, $values);

                if ($mapped === null) {
                    continue;
                }

                $rows[] = $mapped;
            }

            break;
        }

        $reader->close();

        if (empty($rows)) {
            throw new \RuntimeException('No valid sales order items were found in the uploaded file.');
        }

        return $rows;
    }

    protected function mapRow(array $headers, array $values): ?array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = $values[$index] ?? null;
        }

        $item = $this->resolveInventoryItem($row);

        if (! $item) {
            throw new \RuntimeException('Unable to match an inventory item from one of the imported rows. Use `inventory_item_id`, `sku`, or `name`.');
        }

        $quantity = (float) ($row['quantity'] ?? 0);
        $unitPrice = (float) ($row['unit_price'] ?? $row['price'] ?? 0);

        if ($quantity <= 0) {
            return null;
        }

        $unit = $item->hasPurchaseUnit() ? $item->purchase_unit : ($item->unit ?? 'unit');

        return [
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_label' => $unit,
            'unit_price' => $unitPrice ?: (float) ($item->price ?? 0),
            'total' => round($quantity * ($unitPrice ?: (float) ($item->price ?? 0)), 2),
        ];
    }

    protected function resolveInventoryItem(array $row): ?InventoryItem
    {
        if (! empty($row['inventory_item_id']) || ! empty($row['item_id'])) {
            $id = (int) ($row['inventory_item_id'] ?? $row['item_id']);

            return InventoryItem::find($id);
        }

        if (! empty($row['sku']) || ! empty($row['item_sku'])) {
            $sku = $row['sku'] ?? $row['item_sku'];

            return InventoryItem::where('sku', $sku)->first();
        }

        if (! empty($row['name']) || ! empty($row['item_name'])) {
            $name = $row['name'] ?? $row['item_name'];

            return InventoryItem::where('name', $name)->first();
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        return str($header)
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();
    }

    protected function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn ($value) => $value === '');
    }
}
