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

class PackageCostingWizardSchema
{
    public static function steps(): array
    {
        return [
            Step::make('Box Structure')
                ->visible(fn (Get $get): bool => $get('job_type') === 'packages')
                ->columns(3)
                ->schema([
                    Select::make('services.box.box_type')->options(['folding_carton' => 'Folding Carton', 'sleeve' => 'Sleeve', 'mailer' => 'Mailer'])->default('folding_carton'),
                    TextInput::make('services.box.length')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    TextInput::make('services.box.width')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    TextInput::make('services.box.height')->numeric()->suffix('cm')->live(onBlur: true)->required(),
                    TextInput::make('services.box.flap_size')->numeric()->suffix('cm')->default(1)->live(onBlur: true),
                    TextInput::make('services.box.glue_area')->numeric()->suffix('cm')->default(1)->live(onBlur: true),
                    TextInput::make('services.box.colors')->label('Number of Colors')->numeric()->default(4)->live(onBlur: true),
                    TextInput::make('services.box.print_coverage_percent')->numeric()->suffix('%')->default(1.5)->live(onBlur: true),
                    CheckboxList::make('services.box.finishing')->options(['uv' => 'UV', 'foil' => 'Foil', 'emboss' => 'Emboss', 'varnish' => 'Varnish'])->columns(4)->columnSpanFull(),
                ]),
            Step::make('Sheet Layout')
                ->visible(fn (Get $get): bool => $get('job_type') === 'packages')
                ->columns(3)
                ->schema([
                    TextInput::make('services.layout.sheet_width')->numeric()->suffix('cm')->default(100)->live(onBlur: true),
                    TextInput::make('services.layout.sheet_height')->numeric()->suffix('cm')->default(70)->live(onBlur: true),
                    TextInput::make('services.layout.ups')->numeric()->default(24)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.layout.print_length')->numeric()->suffix('cm')->default(42)->live(onBlur: true),
                    TextInput::make('services.layout.print_width')->numeric()->suffix('cm')->default(70)->live(onBlur: true),
                    TextInput::make('services.layout.waste_percent')->numeric()->suffix('%')->default(3)->live(onBlur: true),
                    Select::make('services.layout.grain_direction')->options(['long' => 'Long Grain', 'short' => 'Short Grain'])->default('long'),
                    Placeholder::make('services.layout.utilization')->label('Sheet Utilization')->content(fn (Get $get): string => ((string) ($get('services.layout.ups') ?: 0)).' ups'),
                ]),
            Step::make('Material Selection')
                ->visible(fn (Get $get): bool => $get('job_type') === 'packages')
                ->columns(2)
                ->schema([
                    Select::make('services.material.board_item_id')->label('Board Item')->options(fn (): array => InventoryItem::query()->paperOrBoardMaterials()->pluck('name', 'id')->all())->searchable()->preload()->live()->required(),
                    Select::make('services.material.ink_item_id')->label('Ink System')->options(fn (): array => InventoryItem::query()->inkMaterials()->pluck('name', 'id')->all())->searchable()->preload()->live(),
                    Select::make('services.material.glue_item_id')->label('Glue Item')->options(fn (): array => InventoryItem::query()->glueMaterials()->pluck('name', 'id')->all())->searchable()->preload()->live(),
                    Select::make('services.material.lamination_item_id')->label('Lamination')->options(fn (): array => InventoryItem::query()->laminationMaterials()->pluck('name', 'id')->all())->searchable()->preload()->live(),
                    Select::make('services.material.coating_item_id')->label('Coating')->options(fn (): array => InventoryItem::query()->coatingMaterials()->pluck('name', 'id')->all())->searchable()->preload()->live(),
                ]),
            Step::make('Production Operations')
                ->visible(fn (Get $get): bool => $get('job_type') === 'packages')
                ->columns(3)
                ->schema([
                    Select::make('services.operations.printing_machine_id')->label('Printing Machine')->options(fn (): array => Machine::query()->operation('printing')->pluck('name', 'id')->all())->searchable()->preload()->live(),
                    Select::make('services.operations.diecutting_machine_id')->label('Die Cutting Machine')->options(fn (): array => Machine::query()->operation('die_cutting')->pluck('name', 'id')->all())->searchable()->preload()->live(),
                    TextInput::make('services.operations.die_unit_cost')->numeric()->suffix(fn (): string => Money::suffix())->default(10000)->live(onBlur: true),
                    Select::make('services.operations.folder_gluer_machine_id')->label('Folder Gluer')->options(fn (): array => Machine::query()->operation('folder_gluer')->pluck('name', 'id')->all())->searchable()->preload()->live(),
                    TextInput::make('services.operations.manual_finishing_unit_cost')->numeric()->suffix(fn (): string => Money::suffix())->default(0.05)->live(onBlur: true),
                    Section::make('Machine Snapshots')
                        ->schema([
                            Placeholder::make('printing_machine_snapshot')
                                ->label('Printing')
                                ->content(fn (Get $get): HtmlString => self::machineSnapshot($get('services.operations.printing_machine_id')))
                                ->extraAttributes(['class' => 'cost-snapshot-field']),
                            Placeholder::make('die_machine_snapshot')
                                ->label('Die Cutting')
                                ->content(fn (Get $get): HtmlString => self::machineSnapshot($get('services.operations.diecutting_machine_id')))
                                ->extraAttributes(['class' => 'cost-snapshot-field']),
                            Placeholder::make('gluer_machine_snapshot')
                                ->label('Folder Gluer')
                                ->content(fn (Get $get): HtmlString => self::machineSnapshot($get('services.operations.folder_gluer_machine_id')))
                                ->extraAttributes(['class' => 'cost-snapshot-field']),
                        ])
                        ->secondary()
                        ->compact()
                        ->columns(3)
                        ->extraAttributes(['class' => 'cost-snapshot-section'])
                        ->columnSpanFull(),
                ]),
            Step::make('Packing & Logistics')
                ->visible(fn (Get $get): bool => $get('job_type') === 'packages')
                ->columns(3)
                ->schema([
                    TextInput::make('services.packing.units_per_carton')->numeric()->default(1200)->minValue(1)->live(onBlur: true),
                    TextInput::make('services.packing.cartons_per_pallet')->numeric()->default(40)->minValue(1),
                    TextInput::make('services.packing.bundle_size')->numeric()->default(100),
                    Select::make('services.packing.carton_item_id')->label('Carton / Bag Item')->options(fn (): array => InventoryItem::query()->packingMaterials()->pluck('name', 'id')->all())->searchable()->preload()->live(),
                ]),
            LabelCostingWizardSchema::commercialStep('packages'),
        ];
    }

    private static function itemSnapshot(mixed $itemId): HtmlString
    {
        return CostingSnapshotPresenter::item(InventoryItem::query()->find($itemId));
    }

    private static function machineSnapshot(mixed $machineId): HtmlString
    {
        return CostingSnapshotPresenter::machine(Machine::query()->find($machineId), 'Select a machine.');
    }
}
