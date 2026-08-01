<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Support\StockTransferQuantity;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class StockAdjustmentItemImportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function importRows(string $filePath, int $warehouseId): array
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

                $mapped = $this->mapRow($headers, $values, $warehouseId);

                if ($mapped === null) {
                    continue;
                }

                $rows[] = $mapped;
            }

            break;
        }

        $reader->close();

        if (empty($rows)) {
            throw new \RuntimeException('No valid stock adjustment items were found in the uploaded file.');
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $values
     * @return array<string, mixed>|null
     */
    protected function mapRow(array $headers, array $values, int $warehouseId): ?array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = $values[$index] ?? null;
        }

        $item = $this->resolveInventoryItem($row);

        $sku = $this->normalizeCell($row['sku'] ?? '');

        if ($sku === '') {
            throw new \RuntimeException('Every imported row must include a `sku` value.');
        }

        if (! $item) {
            throw new \RuntimeException("Unable to match inventory item SKU `{$sku}`. Replace the template example SKUs with SKUs that exist in Inventory Items.");
        }

        $systemQuantity = StockTransferQuantity::availableDisplayQuantity($item->id, $warehouseId) ?? 0.0;
        if (! isset($row['new_quantity']) || $row['new_quantity'] === '') {
            throw new \RuntimeException('Every imported row must include a `new_quantity` value.');
        }

        $newQuantity = (float) $row['new_quantity'];
        $adjustmentQuantity = $newQuantity - $systemQuantity;

        return [
            'inventory_item_id' => $item->id,
            'system_quantity' => $systemQuantity,
            'adjustment_quantity' => $adjustmentQuantity,
            'new_quantity' => $systemQuantity + $adjustmentQuantity,
            'difference' => $adjustmentQuantity,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveInventoryItem(array $row): ?InventoryItem
    {
        $sku = $this->normalizeCell($row['sku'] ?? '');

        if ($sku !== '') {
            return InventoryItem::where('sku', $sku)->first();
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        return str($this->normalizeCell($header))
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();
    }

    protected function normalizeCell(mixed $value): string
    {
        return str((string) $value)
            ->remove("\u{FEFF}")
            ->trim()
            ->value();
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn ($value) => $value === '');
    }
}
