<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Models\InventoryItem;
use App\Models\Machine;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class LabelCostingWizardSchema
{
    public static function steps(): array
    {
        return [
            Step::make('Label Specifications')
                ->visible(fn(Get $get): bool => $get('job_type') === 'labels')
                ->columns(3)
                ->schema([
                    TextInput::make('services.label.width')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    TextInput::make('services.label.height')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    Select::make('services.label.shape')->options(['rectangle' => 'Rectangle', 'round' => 'Round', 'oval' => 'Oval', 'custom' => 'Custom'])->default('rectangle'),
                    TextInput::make('services.label.colors')->label('Number of Colors')->numeric()->default(4)->minValue(1)->live(onBlur: true),
                    Select::make('services.label.printing_sides')->options(['front' => 'Front', 'front_back' => 'Front & Back'])->default('front'),
                    TextInput::make('services.label.gap')->numeric()->suffix('cm')->default(0),
                    TextInput::make('services.label.yield')->numeric()->default(3)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.label.waste_allowance_percent')->numeric()->suffix('%')->default(3)->live(onBlur: true),
                    Placeholder::make('services.label.area_preview')
                        ->label('Area')
                        ->content(fn(Get $get): HtmlString => new HtmlString(number_format((float) $get('services.label.width') * (float) $get('services.label.height'), 2) . ' cm2')),
                    CheckboxList::make('services.label.finishing_options')
                        ->options(['lamination' => 'Lamination', 'uv' => 'UV', 'foil' => 'Foil', 'varnish' => 'Varnish'])
                        ->columns(4)
                        ->columnSpanFull(),
                ]),
            Step::make('Material Selection')
                ->visible(fn(Get $get): bool => $get('job_type') === 'labels')
                ->columns(2)
                ->schema([
                    Select::make('services.material.material_item_id')
                        ->label('Label Stock')
                        ->options(fn(): array => InventoryItem::query()->paperMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Select::make('services.material.ink_item_id')
                        ->label('Ink Item')
                        ->options(fn(): array => InventoryItem::query()->inkMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('services.material.adhesive_item_id')
                        ->label('Adhesive')
                        ->options(fn(): array => InventoryItem::query()->adhesiveMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('services.material.liner_item_id')
                        ->label('Liner')
                        ->options(fn(): array => InventoryItem::query()->linerMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('services.material.lamination_item_id')
                        ->label('Lamination')
                        ->options(fn(): array => InventoryItem::query()->laminationMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->visible(fn(Get $get): bool => in_array('lamination', $get('services.label.finishing_options') ?? [], true)),
                    Section::make('Inventory Snapshot')
                        ->schema([
                            Placeholder::make('material_snapshot')
                                ->label('Selected Material')
                                ->content(fn(Get $get): HtmlString => self::itemSnapshot($get('services.material.material_item_id'))),
                            Placeholder::make('ink_snapshot')
                                ->label('Selected Ink')
                                ->content(fn(Get $get): HtmlString => self::itemSnapshot($get('services.material.ink_item_id'))),
                        ])
                        ->columnSpanFull(),
                ]),
            Step::make('Production Setup')
                ->visible(fn(Get $get): bool => $get('job_type') === 'labels')
                ->columns(3)
                ->schema([
                    Select::make('services.production.machine_id')
                        ->label('Printing Machine')
                        ->options(fn(): array => Machine::query()->operation('printing')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    TextInput::make('services.production.printing_up')->numeric()->default(3)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.production.diecutting_up')->numeric()->default(1)->minValue(1)->live(onBlur: true),
                    Placeholder::make('machine_snapshot')
                        ->label('Machine Rates')
                        ->content(fn(Get $get): HtmlString => self::machineSnapshot($get('services.production.machine_id')))
                        ->columnSpanFull(),
                ]),
            Step::make('Finishing & Packing')
                ->visible(fn(Get $get): bool => $get('job_type') === 'labels')
                ->columns(3)
                ->schema([
                    TextInput::make('services.finishing.cutting_unit_cost')->numeric()->suffix('Birr')->default(25)->live(onBlur: true),
                    TextInput::make('services.finishing.bundle_size')->numeric()->default(2000)->minValue(1)->live(onBlur: true),
                    Select::make('services.finishing.packing_item_id')
                        ->label('Packing Item')
                        ->options(fn(): array => InventoryItem::query()->packingMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                ]),
            self::commercialStep('labels'),
        ];
    }

    public static function commercialStep(string $serviceType): Step
    {
        return Step::make('Commercial Review')
            ->visible(fn(Get $get): bool => $get('job_type') === $serviceType)
            ->columns(3)
            ->schema([
                TextInput::make('services.commercial.overhead_percent')->numeric()->suffix('%')->default(15)->live(onBlur: true),
                TextInput::make('services.commercial.profit_margin_percent')->numeric()->suffix('%')->default(20)->live(onBlur: true),
                TextInput::make('services.commercial.discount_percent')->numeric()->suffix('%')->default(0)->live(onBlur: true),
            ]);
    }

    private static function itemSnapshot(mixed $itemId): HtmlString
    {
        $item = InventoryItem::query()->find($itemId);

        if (! $item) {
            return new HtmlString('<span class="text-gray-500">Select an item to see cost, specs, and stock.</span>');
        }

        $purchaseCost = $item->hasPurchaseUnit()
            ? sprintf(
                '<br><span class="text-xs text-gray-500 dark:text-gray-400">%s Birr/%s, %s %s per %s</span>',
                number_format($item->pricePerPurchaseUnit(), 2),
                e($item->purchase_unit),
                number_format((float) $item->conversion_factor, 2),
                e($item->unit),
                e($item->purchase_unit),
            )
            : '';

        return new HtmlString(sprintf(
            '<strong>%s</strong><br><span class="text-primary-600 font-semibold">%s Birr/%s</span>%s<br><span class="text-gray-500 dark:text-gray-400">GSM %s | %s x %s | Stock %s %s</span>',
            e($item->name),
            number_format($item->baseUnitCost(), 2),
            e($item->unit),
            $purchaseCost,
            e($item->gsm ?: 'N/A'),
            e($item->width ?: 'N/A'),
            e($item->height ?: 'N/A'),
            number_format($item->stockOnHand(), 2),
            e($item->unit),
        ));
    }

    private static function machineSnapshot(mixed $machineId): HtmlString
    {
        $machine = Machine::query()->find($machineId);

        if (! $machine) {
            return new HtmlString('<span class="text-gray-500">Select a machine to use speed, hourly cost, setup, and waste defaults.</span>');
        }

        return new HtmlString(sprintf(
            '<strong>%s</strong><br><span class="text-success-600 font-semibold">%s units/hr</span><br><span class="text-gray-500">Hourly %s Birr</span>',
            e($machine->name),
            number_format((float) $machine->production_speed, 2),
            number_format((float) $machine->hourly_cost, 2),
        ));
    }
}
