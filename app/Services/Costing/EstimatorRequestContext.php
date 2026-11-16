<?php

namespace App\Services\Costing;

use App\Models\DielineImposition;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Support\Collection;

class EstimatorRequestContext
{
    /** @var array<int, InventoryItem|null> */
    private array $items = [];

    /** @var array<int, DielineImposition|null> */
    private array $impositions = [];

    /** @var Collection<int, Machine>|null */
    private ?Collection $machines = null;

    /** @var Collection<int, InventoryItem>|null */
    private ?Collection $materialChoices = null;

    /** @var array<string, array<int, string>> */
    private array $options = [];

    private ?Setting $settings = null;

    public function refresh(): void
    {
        $this->items = [];
        $this->impositions = [];
        $this->machines = null;
        $this->materialChoices = null;
        $this->options = [];
        $this->settings = null;
    }

    /** @param array<int, mixed> $ids
     * @return Collection<int, InventoryItem>
     */
    public function items(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        $missing = array_values(array_filter($ids, fn (int $id): bool => ! array_key_exists($id, $this->items)));
        if ($missing !== []) {
            $loaded = InventoryItem::query()->whereIn('id', $missing)->withSum('inventoryBalances', 'quantity_on_hand')->get()->keyBy('id');
            foreach ($missing as $id) {
                $this->items[$id] = $loaded->get($id);
            }
        }

        return collect($ids)->mapWithKeys(fn (int $id): array => [$id => $this->items[$id]])->filter();
    }

    public function item(mixed $id): ?InventoryItem
    {
        return $this->items([$id])->get((int) $id);
    }

    /** @return Collection<int, Machine> */
    public function machines(): Collection
    {
        return $this->machines ??= Machine::query()->orderBy('name')->get()->keyBy('id')->toBase();
    }

    public function machine(mixed $id): ?Machine
    {
        return $this->machines()->get((int) $id);
    }

    public function imposition(mixed $id): ?DielineImposition
    {
        $id = (int) $id;
        if ($id < 1) {
            return null;
        }
        if (! array_key_exists($id, $this->impositions)) {
            $this->impositions[$id] = DielineImposition::find($id);
        }

        return $this->impositions[$id];
    }

    /** @param list<string> $categories
     * @return array<int, string>
     */
    public function materialOptions(array $categories = []): array
    {
        $this->materialChoices ??= InventoryItem::query()->rawMaterials()->select(['id', 'name', 'category'])->orderBy('name')->get();

        return $this->materialChoices->filter(fn (InventoryItem $item): bool => $categories === [] || in_array($item->category, $categories, true))->pluck('name', 'id')->all();
    }

    /** @param list<string> $operations
     * @return array<int, string>
     */
    public function machineOptions(array $operations = [], bool $finishingOnly = false): array
    {
        return $this->machines()->filter(fn (Machine $machine): bool => $machine->is_active
            && ($operations === [] || in_array($machine->operation_type, $operations, true))
            && (! $finishingOnly || $machine->operation_type !== 'printing'))
            ->mapWithKeys(fn (Machine $machine): array => [$machine->id => $machine->name.($machine->costing_configured ? '' : ' · needs setup')])->all();
    }

    /** @return array<int, string> */
    public function customerOptions(): array
    {
        return $this->options['customers'] ??= Partner::query()->where('is_customer', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function impositionOptions(): array
    {
        return $this->options['impositions'] ??= DielineImposition::query()->where('status', 'completed')
            ->select(['id', 'name', 'paper_size', 'paper_width', 'paper_height', 'copies'])->orderByDesc('id')->limit(100)->get()
            ->mapWithKeys(fn (DielineImposition $row): array => [$row->id => $row->name.' · '.$row->sheetLabel().' · '.$row->copies.' up'])->all();
    }

    public function settings(): Setting
    {
        return $this->settings ??= Setting::getSettings();
    }

    public function currencySuffix(): string
    {
        return Money::suffix($this->settings()->currency_code ?: 'ETB');
    }
}
