<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Filament\Forms\Components\MinimalRepeater;
use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Services\Costing\EstimatorRecipe;
use App\Services\Costing\EstimatorRequestContext;
use App\Services\Costing\SheetfedCostCalculator;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class EstimatorWorkspace
{
    public const array ROLES = ['stock' => 'Label / carton stock', 'text' => 'Book text', 'cover' => 'Soft cover', 'case' => 'Case wrap', 'board' => 'Cover boards', 'spine' => 'Spine board', 'endsheet' => 'Endsheets'];

    private const array MACHINE_OVERRIDE_FIELDS = ['hourly_rate_override', 'speed_override', 'setup_minutes_override', 'setup_waste_override', 'waste_percent_override', 'machine_override_reason'];

    private static function sources(): EstimatorRequestContext
    {
        return app(EstimatorRequestContext::class);
    }

    private static function finishingRepeater(): Repeater
    {
        return MinimalRepeater::make('services.finishing')->label('Finishing')->helperText('Sheet operations first, then assembly. Use row options for setup, speed and rate overrides.')
            ->table([TableColumn::make('Machine')->width('220px'), TableColumn::make('Basis')->width('130px'), TableColumn::make('Component')->width('130px')])->compact()
            ->schema([
                ...self::hiddenFields(['name', 'product_review_confirmed', ...self::MACHINE_OVERRIDE_FIELDS]),
                Select::make('machine_id')->label('Machine')->options(fn (): array => self::sources()->machineOptions(finishingOnly: true))->searchable()->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $machine = self::sources()->machine($state);
                        $set('name', $machine?->name);
                        $set('basis', $machine?->speed_unit === 'products_per_hour' ? 'products' : 'sheets');
                    }),
                Select::make('basis')->label('Quantity basis')->options(['sheets' => 'Press sheets', 'products' => 'Finished pieces'])->default('products')->live(),
                Select::make('component_role')->label('Component')->options(self::ROLES)->default('stock')->disabled(fn (Get $get): bool => $get('basis') !== 'sheets')->dehydrated()->live(),
            ])->default(self::template('labels')['finishing'])->reorderableWithButtons()
            ->inlineAdd('Add operation', actions: [self::itemOptions('operationOptions', 'Operation options', [TextInput::make('name')->label('Operation label')->maxLength(120), Toggle::make('product_review_confirmed')->label('Keep this operation for the selected product')->default(true), ...self::machineOverrides()])])->columnSpanFull();
    }

    private static function consumablesRepeater(): Repeater
    {
        return MinimalRepeater::make('services.consumables')->label('Consumables & tooling')->helperText('Quantities use the inventory base unit. Target a component or operation for film and glue.')
            ->table([TableColumn::make('Material')->width('220px'), TableColumn::make('Consumption')->width('110px'), TableColumn::make('Per')->width('140px'), TableColumn::make('Component')->width('130px')])->compact()
            ->schema([
                ...self::hiddenFields(['name', 'operation_machine_id', 'unit_cost_override', 'override_reason']),
                Select::make('material_id')->label('Inventory item')->options(fn (): array => self::sources()->materialOptions())->searchable()->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state) => $set('name', self::sources()->item($state)?->name)),
                self::number('quantity_per_basis', 'Quantity per basis', 0.000001)->suffix(fn (Get $get): ?string => self::sources()->item($get('material_id'))?->unit),
                Select::make('basis')->options(['fixed' => 'Job', 'products' => 'Finished piece', 'sheets' => 'Press sheet', 'm2' => 'Sheet m²'])->default('fixed')->live(),
                Select::make('component_role')->label('Component')->options(['all' => 'All', ...self::ROLES])->default('all')->live(),
            ])->default([])->reorderable(false)
            ->inlineAdd('Add material', actions: [self::itemOptions('consumableOptions', 'Consumption options', [
                TextInput::make('name')->label('Usage label')->maxLength(120),
                Select::make('operation_machine_id')->label('At operation (optional)')->options(fn (): array => self::sources()->machineOptions(finishingOnly: true))->searchable(),
                self::number('unit_cost_override', 'Base-unit cost override')->suffix(fn (): string => self::sources()->currencySuffix()),
                TextInput::make('override_reason')->label('Override reason')->maxLength(255),
            ])])->columnSpanFull();
    }

    private static function componentOptions(): array
    {
        return [
            TextInput::make('name')->label('Component name')->maxLength(100),
            Toggle::make('product_review_confirmed')->label('Keep this component for the selected product')->default(true),
            self::number('units_per_product', 'Pieces / finished product', 0.000001)->default(1),
            self::number('bleed_mm', 'Bleed / edge')->suffix('mm')->default(3),
            self::number('gap_mm', 'Gap between blanks')->suffix('mm')->default(2),
            self::number('page_gap_mm', 'Gap between text pages')->suffix('mm')->default(0),
            Select::make('precut_machine_id')->label('Parent-sheet cutter')->options(fn (): array => self::sources()->machineOptions(['cutting']))->searchable(),
            self::number('material_waste_percent', 'Stock handling rejects')->maxValue(99)->suffix('%'),
            self::number('unit_cost_override', 'Stock cost / base unit')->suffix(fn (): string => self::sources()->currencySuffix()),
            TextInput::make('override_reason')->label('Stock override reason')->maxLength(255),
            self::number('plate_cost_override', 'Plate cost / base unit')->suffix(fn (): string => self::sources()->currencySuffix()),
            TextInput::make('plate_override_reason')->label('Plate override reason')->maxLength(255),
            self::number('ink_grams_per_m2', 'Ink at full coverage')->suffix('g/m²'),
            Select::make('dieline_imposition_id')->label('Completed dieline layout')->options(fn (): array => self::sources()->impositionOptions())->searchable(),
            Toggle::make('blank_dimensions_confirmed')->label('Carton blank includes flaps and glue tabs'),
            ...self::machineOverrides(),
        ];
    }

    private static function itemOptions(string $name, string $heading, array $fields): Action
    {
        foreach ($fields as $field) {
            $field->live(false);
        }

        return Action::make($name)->label('Options')->tooltip($heading)->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->disabled(fn (Repeater $component): bool => $component->isDisabled())
            ->slideOver()->modalHeading($heading)->modalSubmitActionLabel('Apply options')->modalWidth('4xl')->schema([Group::make($fields)->columns(2)])
            ->fillForm(fn (array $arguments, Repeater $component): array => $component->getRawItemState($arguments['item']))
            ->action(function (array $data, array $arguments, Repeater $component) use ($name): void {
                self::updateItem($component, $arguments['item'], $name === 'componentOptions' ? [...$data, 'layout_confirmed' => false, 'layout_key' => null] : $data);
            });
    }

    private static function suggestPressAction(): Action
    {
        return Action::make('suggestPress')->icon(Heroicon::OutlinedSparkles)->label('Suggest press')->tooltip('Compare compatible presses')
            ->disabled(fn (Repeater $component): bool => $component->isDisabled())
            ->modalHeading('Compatible printing presses')->modalSubmitActionLabel('Use press')->schema([
                Select::make('machine_id')->label('Printing press')->required()->options(function (array $arguments, Repeater $component): array {
                    $livewire = $component->getLivewire();
                    $suggestions = app(SheetfedCostCalculator::class)->suggestPresses($component->getRawItemState($arguments['item']), [
                        ...($livewire->data['services']['product'] ?? []), 'family' => $livewire->data['job_type'] ?? 'labels',
                    ]);

                    return collect($suggestions)->mapWithKeys(fn (array $suggestion): array => [$suggestion['machine_id'] => $suggestion['name'].' · '.$suggestion['layout']['ups'].' up · '.$suggestion['layout']['utilization_percent'].'% utilization'])->all();
                })->helperText('Suggestions respect stock size, material limits and grain. Configure a compatible press if no options appear.'),
            ])->action(function (array $data, array $arguments, Repeater $component): void {
                self::updateItem($component, $arguments['item'], [...$data, 'layout_key' => null, 'layout_confirmed' => false]);
            });
    }

    private static function updateItem(Repeater $component, string $key, array $data): void
    {
        $state = $component->getRawState();
        $state[$key] = [...($state[$key] ?? []), ...$data];
        $component->state($state);
        $livewire = $component->getLivewire();
        unset($livewire->estimatorResult, $livewire->estimatorComparisons);
    }

    /** @param list<string> $names */
    private static function hiddenFields(array $names): array
    {
        return array_map(function (string $name): Hidden {
            $rules = match (true) {
                $name === 'name', str_ends_with($name, '_reason') => ['nullable', 'string', 'max:255'],
                str_ends_with($name, '_confirmed') => ['nullable', 'boolean'],
                str_ends_with($name, '_id') => ['nullable', 'integer', 'min:1'],
                $name === 'units_per_product' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
                str_contains($name, 'percent') => ['nullable', 'numeric', 'min:0', 'lt:100'],
                default => ['nullable', 'numeric', 'min:0'],
            };

            return Hidden::make($name)->rules($rules)->default($name === 'product_review_confirmed' ? true : null);
        }, $names);
    }

    public static function components(): array
    {
        return [
            Group::make()->schema([
                Hidden::make('services.costing_version')->default('sheetfed-v2'),
                View::make('filament.resources.cost-estimates.workspace-configurator')->viewData(fn (Component $livewire): array => [
                    'configurator' => $livewire->estimatorConfigurator,
                    'result' => $livewire->estimatorResult,
                    'actions' => $livewire instanceof EditCostEstimate ? $livewire->getCachedHeaderActions() : [],
                ])->columnSpanFull(),
                View::make('filament.resources.cost-estimates.workspace-details-modal')->schema([
                    Tabs::make('estimator-workspace')->livewireProperty('estimatorTab')->tabs([
                        'specifications' => Tab::make('Product details')->key('specifications')->schema([
                            Group::make()->schema([
                                TextInput::make('description')->label('Product name')->required()->maxLength(255)->columnSpan(2),
                                Select::make('job_type')->label('Product family')->options(['books' => 'Books', 'labels' => 'Labels', 'packages' => 'Folding cartons', 'stickers' => 'Stickers'])->default('labels')->required()->live()
                                    ->afterStateUpdated(fn (Component $livewire, ?string $state) => $livewire->applyEstimatorTemplate($state ?? 'labels')),
                                TextInput::make('quantity')->integer()->minValue(1)->maxValue(100000000)->default(1000)->required()->live(onBlur: true),
                                Select::make('partner_id')->label('Customer (optional)')->options(fn (): array => self::sources()->customerOptions())->searchable()->columnSpan(2),
                            ])->columns(3)->columnSpanFull(),
                            ...self::specifications(),
                        ]),
                        'materials' => Tab::make('Material & layout')->key('materials')->schema([
                            MinimalRepeater::make('services.components')->hiddenLabel()->schema(self::componentFields())->default(self::template('labels')['components'])
                                ->itemLabel(fn (array $state): string => $state['name'] ?? self::ROLES[$state['role'] ?? 'stock'])->columns(3)->collapsible()->inlineAdd('Add component', actions: [
                                    self::itemOptions('componentOptions', 'Component options', self::componentOptions()),
                                    self::suggestPressAction(),
                                ])->columnSpanFull(),
                        ]),
                        'production' => Tab::make('Production route')->key('production')->schema([
                            self::finishingRepeater(),
                            self::view('route'),
                            self::consumablesRepeater(),
                            Toggle::make('services.product.consumables_confirmed')->label('All required glue, wire, dies, film and packing are included')->default(false)->live()->columnSpanFull(),
                        ]),
                        'pricing' => Tab::make('Pricing')->key('pricing')->schema([
                            Group::make()->schema([
                                Select::make('services.commercial.pricing_mode')->label('Profit method')->options(['margin' => 'Gross margin on selling price', 'markup' => 'Markup on production cost'])->default('margin')->live(),
                                self::number('services.commercial.profit_percent', 'Target profit')->default(20)->suffix('%')->maxValue(fn (Get $get): int => $get('services.commercial.pricing_mode') === 'markup' ? 1000 : 99),
                                self::number('services.commercial.discount_percent', 'Discount')->default(0)->maxValue(99)->suffix('%'),
                                self::number('services.commercial.overhead_fixed', 'Additional overhead · fixed')->default(0)->suffix(fn (): string => self::sources()->currencySuffix()),
                                self::number('services.commercial.overhead_percent', 'Additional overhead · percent')->default(0)->suffix('%'),
                            ])->columns(3)->columnSpanFull(),
                            self::view('breakdown'),
                        ]),
                        'quantities' => Tab::make('Quantities')->key('quantities')->schema([
                            TextInput::make('services.comparison_quantities')->label('Compare quantities')->placeholder('500, 1000, 2500')->helperText('Up to three additional quantities, plus the current quantity. The same stock, route and pricing policy are recalculated for each.')->regex('/^\s*\d+(?:\s*,\s*\d+){0,2}\s*$/')->live(onBlur: true)->maxLength(100),
                            self::view('quantities'),
                        ]),
                    ])->contained(false)->extraAttributes(['class' => 'estimator-tabs'])->columnSpanFull(),
                    self::view('summary')->visible(fn (Component $livewire): bool => $livewire->estimatorTab === 'pricing')->columnSpanFull(),
                ])->columnSpanFull(),
            ])->columnSpanFull(),
        ];
    }

    private static function specifications(): array
    {
        return [
            Group::make()->schema([
                self::number('services.product.width_mm', 'Trim width')->suffix('mm')->default(148),
                self::number('services.product.height_mm', 'Trim height')->suffix('mm')->default(210),
                self::number('services.product.page_count', 'Text pages (excludes cover)')->integer()->default(64)->visible(fn (Get $get): bool => $get('job_type') === 'books'),
                Select::make('services.product.binding')->options(['saddle' => 'Saddle stitch', 'perfect' => 'Perfect binding', 'hardcover' => 'Hardcover'])->default('perfect')->live()
                    ->helperText('Changing binding adds the required components and preserves existing selections for review.')
                    ->visible(fn (Get $get): bool => $get('job_type') === 'books')
                    ->afterStateUpdated(fn (Component $livewire, ?string $state) => $livewire->applyEstimatorTemplate('books', $state ?? 'perfect')),
                Select::make('services.product.shape')->options(['rectangle' => 'Rectangle', 'round' => 'Round', 'oval' => 'Oval', 'custom' => 'Custom'])->default('rectangle')->live()
                    ->visible(fn (Get $get): bool => in_array($get('job_type'), ['labels', 'stickers'], true)),
                Toggle::make('services.product.requires_gluing')->label('Carton requires gluing')->default(true)->live()->visible(fn (Get $get): bool => $get('job_type') === 'packages'),
            ])->columns(3)->columnSpanFull(),
            self::view('readiness'),
        ];
    }

    private static function componentFields(): array
    {
        $resetLayout = fn (Set $set) => $set('layout_confirmed', false);

        return [
            ...self::hiddenFields(['name', 'product_review_confirmed', 'units_per_product', 'bleed_mm', 'gap_mm', 'page_gap_mm', 'precut_machine_id', 'material_waste_percent', 'unit_cost_override', 'override_reason', 'plate_cost_override', 'plate_override_reason', 'ink_grams_per_m2', 'dieline_imposition_id', 'blank_dimensions_confirmed', ...self::MACHINE_OVERRIDE_FIELDS]),
            Select::make('role')->options(self::ROLES)->default('stock')->live()->afterStateUpdated($resetLayout),
            Select::make('material_id')->label('Paper / board')->options(fn (): array => self::sources()->materialOptions(['paper', 'board']))->searchable()->live()->afterStateUpdated($resetLayout),
            Select::make('machine_id')->label('Press / layout machine')->options(fn (): array => self::sources()->machineOptions(['printing', 'cutting']))->searchable()->live()->afterStateUpdated($resetLayout),
            Select::make('signature_pages')->label('Pages per signature')->options([4 => '4', 8 => '8', 16 => '16', 32 => '32'])->default(16)->live()->visible(fn (Get $get): bool => $get('role') === 'text')->afterStateUpdated($resetLayout),
            Placeholder::make('source_stock')->label('Inventory source')->content(function (Get $get): string {
                $stock = self::sources()->item($get('material_id'));
                if (! $stock) {
                    return 'Select a material to see its cost, base unit and parent-sheet dimensions.';
                }

                return ((float) $stock->average_cost > 0 ? Money::format($stock->average_cost, 4, self::sources()->settings()->currency_code ?: 'ETB').' / '.$stock->unit : 'Average cost missing')
                    .' · '.((float) $stock->width * 10).' × '.((float) $stock->height * 10).' mm parent'
                    .' · '.($stock->gsm ?? 'Unknown').' g/m² · '.($stock->grain_direction ?? 'Unknown').' grain';
            })->columnSpanFull(),
            self::number('width_mm', 'Measured flat width')->suffix('mm')->visible(fn (Get $get): bool => $get('role') !== 'text')->afterStateUpdated($resetLayout),
            self::number('height_mm', 'Measured flat height')->suffix('mm')->visible(fn (Get $get): bool => $get('role') !== 'text')->afterStateUpdated($resetLayout),
            Select::make('grain_axis')->label('Grain along flat blank')->options(['any' => 'Unrestricted', 'height' => 'Height / spine', 'width' => 'Width'])->default('any')->live()->afterStateUpdated($resetLayout),
            self::number('front_colors', 'Front colors')->integer()->maxValue(12)->default(4)->afterStateUpdated($resetLayout),
            self::number('back_colors', 'Back colors')->integer()->maxValue(12)->default(0)->afterStateUpdated($resetLayout),
            Select::make('layout_key')->label('Sheet layout')->options(fn (Get $get): array => self::layoutOptions($get))->placeholder('Suggested valid grid')->live()->afterStateUpdated($resetLayout),
            Select::make('plate_item_id')->label('Printing plate')->options(fn (): array => self::sources()->materialOptions(['plate']))->searchable()->live(),
            Select::make('ink_item_id')->label('Ink')->options(fn (): array => self::sources()->materialOptions(['ink']))->searchable()->live(),
            self::number('ink_coverage_percent', 'Average coverage per ink / side')->maxValue(100)->suffix('%'),
            View::make('filament.resources.cost-estimates.workspace-layouts')->viewData(function (Get $get, Component $livewire): array {
                $result = clone $livewire->estimatorResult;
                $result->layouts = array_values(array_filter($result->layouts, fn (array $layout): bool => $layout['role'] === $get('role')));

                return ['result' => $result];
            })->columnSpanFull(),
            Toggle::make('layout_confirmed')->label('I have verified this layout, grain, folding and finishing allowances')->default(false)->live()->columnSpanFull(),
        ];
    }

    private static function machineOverrides(): array
    {
        return [
            self::number('hourly_rate_override', 'Hourly cost override')->suffix(fn (): string => self::sources()->currencySuffix()),
            self::number('speed_override', 'Effective speed override'),
            self::number('setup_minutes_override', 'Setup time override')->suffix('min'),
            self::number('setup_waste_override', 'Setup waste override'),
            self::number('waste_percent_override', 'Run reject rate override')->maxValue(99)->suffix('%'),
            TextInput::make('machine_override_reason')->label('Machine override reason')->maxLength(255),
        ];
    }

    private static function number(string $name, string $label, float $min = 0): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->minValue($min)->live(onBlur: true);
    }

    private static function view(string $section): View
    {
        return View::make('filament.resources.cost-estimates.workspace-'.$section)
            ->viewData(fn (Component $livewire): array => ['result' => $livewire->estimatorResult, 'comparisons' => $section === 'quantities' ? $livewire->estimatorComparisons : []])
            ->columnSpanFull();
    }

    private static function layoutOptions(Get $get): array
    {
        return collect(app(SheetfedCostCalculator::class)->layoutCandidates($get('') ?? [], [...($get('../../product') ?? []), 'family' => $get('data.job_type', isAbsolute: true)]))->take(12)
            ->mapWithKeys(fn (array $row): array => [$row['key'] => $row['sheet_width_mm'].' × '.$row['sheet_height_mm'].' mm · '.$row['ups'].' up · '.$row['cuts_per_parent'].' cuts/parent'])->all();
    }

    public static function template(string $type, string $binding = 'perfect'): array
    {
        return EstimatorRecipe::template($type, $binding);
    }
}
