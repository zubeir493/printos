<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Models\InventoryItem;
use App\Models\Machine;
use App\Support\Money;
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
                ->visible(fn (Get $get): bool => $get('job_type') === 'labels')
                ->columns(3)
                ->schema([
                    TextInput::make('services.label.width')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    TextInput::make('services.label.height')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    Select::make('services.label.shape')->options(['rectangle' => 'Rectangle', 'round' => 'Round', 'oval' => 'Oval', 'custom' => 'Custom'])->default('rectangle'),
                    TextInput::make('services.label.colors')->label('Number of Colors')->numeric()->default(4)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.label.print_coverage_percent')->label('Ink Coverage')->numeric()->suffix('%')->default(15)->live(onBlur: true),
                    Select::make('services.label.printing_sides')->options(['front' => 'Front', 'front_back' => 'Front & Back'])->default('front'),
                    TextInput::make('services.label.gap')->numeric()->suffix('cm')->default(0),
                    TextInput::make('services.label.yield')->numeric()->default(3)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.label.waste_allowance_percent')->numeric()->suffix('%')->default(3)->live(onBlur: true),
                    Placeholder::make('services.label.area_preview')
                        ->label('Area')
                        ->content(fn (Get $get): HtmlString => new HtmlString(number_format((float) $get('services.label.width') * (float) $get('services.label.height'), 2).' cm2')),
                    CheckboxList::make('services.label.finishing_options')
                        ->options(['lamination' => 'Lamination', 'uv' => 'UV', 'foil' => 'Foil', 'varnish' => 'Varnish'])
                        ->columns(4)
                        ->columnSpanFull(),
                ]),
            Step::make('Material Selection')
                ->visible(fn (Get $get): bool => $get('job_type') === 'labels')
                ->columns(2)
                ->schema([
                    Select::make('services.material.material_item_id')
                        ->label('Label Stock')
                        ->options(fn (): array => InventoryItem::query()->paperMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Select::make('services.material.ink_item_id')
                        ->label('Ink Item')
                        ->options(fn (): array => InventoryItem::query()->inkMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('services.material.adhesive_item_id')
                        ->label('Adhesive')
                        ->options(fn (): array => InventoryItem::query()->adhesiveMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('services.material.liner_item_id')
                        ->label('Liner')
                        ->options(fn (): array => InventoryItem::query()->linerMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('services.material.lamination_item_id')
                        ->label('Lamination')
                        ->options(fn (): array => InventoryItem::query()->laminationMaterials()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->visible(fn (Get $get): bool => in_array('lamination', $get('services.label.finishing_options') ?? [], true)),
                ]),
            Step::make('Production Setup')
                ->visible(fn (Get $get): bool => $get('job_type') === 'labels')
                ->columns(3)
                ->schema([
                    Select::make('services.production.machine_id')
                        ->label('Printing Machine')
                        ->options(fn (): array => Machine::query()->operation('printing')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    TextInput::make('services.production.printing_up')->numeric()->default(3)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.production.diecutting_up')->numeric()->default(1)->minValue(1)->live(onBlur: true),
                    Section::make('Machine Rates Snapshot')
                        ->schema([
                            Placeholder::make('machine_snapshot')
                                ->label('Printing')
                                ->content(fn (Get $get): HtmlString => self::machineSnapshot($get('services.production.machine_id')))
                                ->extraAttributes(['class' => 'cost-snapshot-field']),
                        ])
                        ->secondary()
                        ->compact()
                        ->columns(1)
                        ->extraAttributes(['class' => 'cost-snapshot-section'])
                        ->columnSpanFull(),
                ]),
            Step::make('Finishing & Packing')
                ->visible(fn (Get $get): bool => $get('job_type') === 'labels')
                ->columns(3)
                ->schema([
                    TextInput::make('services.finishing.cutting_unit_cost')->numeric()->suffix(fn (): string => Money::suffix())->default(25)->live(onBlur: true),
                    TextInput::make('services.finishing.bundle_size')->numeric()->default(2000)->minValue(1)->live(onBlur: true),
                    Select::make('services.finishing.packing_item_id')
                        ->label('Packing Item')
                        ->options(fn (): array => InventoryItem::query()->packingMaterials()->pluck('name', 'id')->all())
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
            ->visible(fn (Get $get): bool => $get('job_type') === $serviceType)
            ->columns(3)
            ->schema([
                TextInput::make('services.commercial.overhead_percent')->numeric()->suffix('%')->default(15)->live(onBlur: true),
                TextInput::make('services.commercial.profit_margin_percent')->numeric()->suffix('%')->default(20)->live(onBlur: true),
                TextInput::make('services.commercial.discount_percent')->numeric()->suffix('%')->default(0)->live(onBlur: true),
            ]);
    }

    private static function itemSnapshot(mixed $itemId): HtmlString
    {
        return CostingSnapshotPresenter::item(InventoryItem::query()->find($itemId));
    }

    private static function machineSnapshot(mixed $machineId): HtmlString
    {
        return CostingSnapshotPresenter::machine(Machine::query()->find($machineId));
    }
}
