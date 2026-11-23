<?php

namespace App\Filament\Resources\JobOrders\Schemas;

use App\Filament\Forms\Components\MinimalRepeater;
use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Setting;
use App\Services\JobOrders\JobProductionCosting;
use App\Support\Money;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
use Filament\Schemas\Components\Utilities\Set as UtilitiesSet;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class JobOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()->schema([
                TextInput::make('name')->label('Job name')->required()->maxLength(255)
                    ->disabled(fn (?JobOrder $record): bool => self::pricingLocked($record)),
                Select::make('production_mode')
                    ->label('Job source')
                    ->options([
                        'make_to_order' => 'Client Job',
                        'make_to_stock' => 'Internal Job',
                    ])
                    ->default('make_to_order')
                    ->live()
                    ->afterStateUpdated(fn (UtilitiesSet $set) => $set('cost_estimate_id', null))
                    ->disabled(fn (?JobOrder $record): bool => self::pricingLocked($record))
                    ->required(),
                Select::make('partner_id')
                    ->label('Customer')
                    ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('phone')
                            ->required(),
                        TextInput::make('email')
                            ->email(),
                        TextInput::make('address'),
                        Hidden::make('is_customer')->default(true),
                    ])
                    ->preload()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (UtilitiesSet $set) => $set('cost_estimate_id', null))
                    ->disabled(fn (?JobOrder $record): bool => self::pricingLocked($record))
                    ->required(fn (UtilitiesGet $get) => $get('production_mode') !== 'make_to_stock')
                    ->hidden(fn (UtilitiesGet $get) => $get('production_mode') === 'make_to_stock')
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn (mixed $state, UtilitiesGet $get): mixed => $get('production_mode') === 'make_to_stock' ? null : $state),
                Select::make('job_type')
                    ->label('Product family')
                    ->options([
                        'books' => 'Books',
                        'packages' => 'Packages',
                        'vouchers' => 'Vouchers',
                        'labels' => 'Labels',
                    ])
                    ->reactive()
                    ->afterStateUpdated(fn (UtilitiesSet $set) => $set('cost_estimate_id', null))
                    ->default('packages')
                    ->disabled(fn (?JobOrder $record): bool => self::pricingLocked($record))
                    ->required(),
                TextInput::make('quantity')->label('Run quantity')->integer()->minValue(1)->maxValue(4294967295)->required()->default(1)
                    ->disabled(fn (?JobOrder $record): bool => self::pricingLocked($record))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (UtilitiesGet $get, UtilitiesSet $set): void {
                        self::updatePricing($get, $set);
                        $set('cost_estimate_id', null);
                    }),
                DatePicker::make('production_due_date')
                    ->label('Production due date')
                    ->default(fn () => today()->addWeek())
                    ->required(fn (?JobOrder $record): bool => ! $record || $record->uses_production_ticket),
                TextInput::make('size')->label('Finished size')->maxLength(255),
                Grid::make(['default' => 1, 'md' => 3])->columnSpanFull()
                    ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'books')
                    ->schema([
                        Group::make([
                            Checkbox::make('services.typing')->label('Typing'),
                            Checkbox::make('services.layout_design')->label('Layout Design'),
                            Checkbox::make('services.cover_design')->label('Cover Design'),
                            Checkbox::make('services.selling_price')->label('Selling Price on Cover'),
                            Checkbox::make('services.spine_has_text')->label('Spine has text'),
                            Checkbox::make('services.cover_inner_printing')->label('Cover inner printing'),
                            Checkbox::make('services.dont_insert_printer_name')->label("Don't insert printer name"),
                            Checkbox::make('services.cover_proof')->label('Cover Proof'),
                            Checkbox::make('services.lamination')->label('Lamination'),
                        ])->columns(1)->columnSpan(1),
                        Group::make([
                            TextInput::make('services.page_no')->label('Page count')->integer()->minValue(1),
                            TextInput::make('services.edition')->label('Edition')->maxLength(100),
                            TextInput::make('services.text_color_no')->label('Number of Colors (Text)'),
                            TextInput::make('services.cover_color_no')->label('Number of Colors (Cover)'),
                            TextInput::make('services.cover_ups')->label('Cover Ups'),
                            TextInput::make('services.books_per_package')->label('Books per Package'),
                            Select::make('services.binding_type')
                                ->label('Binding Type')
                                ->options([
                                    'saddle' => 'Saddle stitch',
                                    'perfect' => 'Perfect Binding',
                                    'hardcover' => 'Hardcover',
                                ]),
                        ])->columnSpan(['default' => 1, 'md' => 2])->columns(['default' => 1, 'sm' => 2]),

                    ]),
                Grid::make(['default' => 1, 'md' => 3])->columnSpanFull()
                    ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'packages')
                    ->schema([
                        Group::make([
                            Checkbox::make('services.new_design')->label('New Design'),
                            Checkbox::make('services.redesign')->label('Redesign'),
                            Checkbox::make('services.new_dielines')->label('New dielines'),
                            Checkbox::make('services.old_dielines')->label('Old dielines'),
                            Checkbox::make('services.full_color')->label('Full color'),
                            Checkbox::make('services.one_side_print')->label('One side print'),
                            Checkbox::make('services.back_side_print')->label('Back side print'),
                            Checkbox::make('services.work_and_turn')->label('Work and Turn'),
                        ])->columnSpan(1)->columns(1),
                        Group::make([
                            TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                            TextInput::make('services.printing_ups')->label('Printing Ups'),
                            TextInput::make('services.diecutting_ups')->label('Diecutting Ups'),
                            TextInput::make('services.pieces_per_sheet')->label('Pieces per sheet'),
                            CheckboxList::make('services.colors_used')
                                ->options([
                                    'C' => 'C',
                                    'M' => 'M',
                                    'Y' => 'Y',
                                    'K' => 'K',
                                ])
                                ->label('Colors Used')
                                ->columns(4),
                            TextInput::make('services.panton_no1')->label('Pantone No'),
                            TextInput::make('services.panton_no2')->label('Pantone No'),
                            TextInput::make('services.panton_no3')->label('Pantone No'),
                        ])->columnSpan(['default' => 1, 'md' => 2])->columns(['default' => 1, 'sm' => 2]),
                    ]),
                Grid::make(['default' => 1, 'md' => 3])->columnSpanFull()
                    ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'labels')
                    ->schema([
                        Group::make([
                            Checkbox::make('services.new_design')->label('New Design'),
                            Checkbox::make('services.redesign')->label('Redesign'),
                            Checkbox::make('services.new_dielines')->label('New dielines'),
                            Checkbox::make('services.old_dielines')->label('Old dielines'),
                            Checkbox::make('services.full_color')->label('Full color'),
                            Checkbox::make('services.one_side_print')->label('One side print'),
                            Checkbox::make('services.back_side_print')->label('Back side print'),
                            Checkbox::make('services.work_and_turn')->label('Work and Turn'),
                        ])->columnSpan(1)->columns(1),
                        Group::make([
                            TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                            TextInput::make('services.printing_ups')->label('Printing Ups'),
                            TextInput::make('services.diecutting_ups')->label('Diecutting Ups'),
                            TextInput::make('services.pieces_per_sheet')->label('Pieces per sheet'),
                            CheckboxList::make('services.colors_used')
                                ->options([
                                    'C' => 'C',
                                    'M' => 'M',
                                    'Y' => 'Y',
                                    'K' => 'K',
                                ])
                                ->label('Colors Used')
                                ->columns(4),
                            TextInput::make('services.panton_no1')->label('Pantone No'),
                            TextInput::make('services.panton_no2')->label('Pantone No'),
                            TextInput::make('services.panton_no3')->label('Pantone No'),
                        ])->columnSpan(['default' => 1, 'md' => 2])->columns(['default' => 1, 'sm' => 2]),
                    ]),
                Grid::make(['default' => 1, 'md' => 3])->columnSpanFull()
                    ->visible(fn (UtilitiesGet $get) => $get('job_type') === 'vouchers')
                    ->schema([
                        Group::make([
                            Checkbox::make('services.new_design')->label('New Design'),
                            Checkbox::make('services.redesign')->label('Redesign'),
                            Checkbox::make('services.new_dielines')->label('New dielines'),
                            Checkbox::make('services.old_dielines')->label('Old dielines'),
                            Checkbox::make('services.full_color')->label('Full color'),
                            Checkbox::make('services.one_side_print')->label('One side print'),
                            Checkbox::make('services.back_side_print')->label('Back side print'),
                            Checkbox::make('services.work_and_turn')->label('Work and Turn'),
                        ])->columnSpan(1)->columns(1),
                        Group::make([
                            TextInput::make('services.amount_of_colors')->label('Amount of Colors'),
                            TextInput::make('services.printing_ups')->label('Printing Ups'),
                            TextInput::make('services.numbering_ups')->label('Numbering Ups'),
                            TextInput::make('services.pieces_per_sheet')->label('Pieces per sheet'),
                            CheckboxList::make('services.colors_used')
                                ->options([
                                    'C' => 'C',
                                    'M' => 'M',
                                    'Y' => 'Y',
                                    'K' => 'K',
                                ])
                                ->label('Colors Used')
                                ->columns(4),
                            TextInput::make('services.panton_no1')->label('Pantone No'),
                            TextInput::make('services.panton_no2')->label('Pantone No'),
                            TextInput::make('services.panton_no3')->label('Pantone No'),
                        ])->columnSpan(['default' => 1, 'md' => 2])->columns(['default' => 1, 'sm' => 2]),
                    ]),
                MinimalRepeater::make('paper')->label('Materials')
                    ->table([
                        TableColumn::make('Material')->alignLeft(),
                        TableColumn::make('Required quantity')->alignLeft(),
                        TableColumn::make('Reserve quantity')->alignLeft(),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('inventory_item_id')->label('Material')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => InventoryItem::query()->rawMaterials()->where('name', 'like', "%{$search}%")->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => InventoryItem::query()->rawMaterials()->find($value)?->name)
                            ->exists('inventory_items', 'id', modifyRuleUsing: fn ($rule) => $rule->where('type', 'raw_material'))
                            ->required(fn (UtilitiesGet $get): bool => (float) $get('required_quantity') !== 0.0 || (float) $get('reserve_quantity') !== 0.0),
                        TextInput::make('required_quantity')->numeric()->minValue(0)->required()->default(0),
                        TextInput::make('reserve_quantity')->numeric()->minValue(0)->default(0),
                    ])
                    ->defaultItems(1)->columnSpanFull()->reorderable(false)
                    ->mutateDehydratedStateUsing(fn (?array $state): array => array_values(array_filter($state ?? [], fn (array $row): bool => filled($row['inventory_item_id'] ?? null))))
                    ->inlineAdd('Add Material', 'add_paper'),
                MinimalRepeater::make('deliverables')->label('Required Files')
                    ->table([
                        TableColumn::make('Deliverable')->alignLeft(),
                        TableColumn::make('File Type')->alignLeft(),
                    ])
                    ->compact()
                    ->schema([
                        TextInput::make('label')->label('Deliverable')->maxLength(255)
                            ->required(fn (UtilitiesGet $get): bool => $get('type') === 'text_file'),
                        Select::make('type')->options(['artwork' => 'Artwork', 'text_file' => 'Text File'])->default('artwork')->required(),
                    ])
                    ->defaultItems(1)->columnSpanFull()->reorderable(false)
                    ->mutateDehydratedStateUsing(fn (?array $state): array => array_values(array_filter($state ?? [], fn (array $row): bool => filled($row['label'] ?? null))))
                    ->inlineAdd('Add Deliverable', 'add_deliverable'),
                Textarea::make('instructions')->label('Production instructions')->rows(3)->columnSpanFull(),
            ])->columns(['default' => 1, 'sm' => 2, 'lg' => 3])->columnSpan(['default' => 1, 'lg' => 2]),
            Section::make('Dates and costing')->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 1, 'xl' => 2])->schema([
                    DatePicker::make('submission_date')
                        ->label('Job received')
                        ->default(now())
                        ->required(),
                    DatePicker::make('proof_due_date')->label('Proof due date')->beforeOrEqual('production_due_date'),
                ]),
                Select::make('cost_estimate_id')
                    ->label('Cost estimate')
                    ->placeholder('Select a finalized estimate')
                    ->options(fn (UtilitiesGet $get): array => app(JobProductionCosting::class)
                        ->eligibleEstimates((string) $get('job_type'), (int) $get('quantity'), $get('production_mode') === 'make_to_stock' ? null : ($get('partner_id') ?: null))
                        ->pluck('estimate_number', 'id')->all())
                    ->searchable()
                    ->visible(fn (?JobOrder $record): bool => ! $record || $record->uses_production_ticket)
                    ->disabled(fn (?JobOrder $record): bool => filled($record?->proforma_task_id) || ($record && ! in_array((string) $record->status, ['draft', 'design'], true))),
                TextInput::make('unit_price')->label('Customer unit price')->numeric()->minValue(0)
                    ->suffix(fn (): string => Money::suffix())
                    ->formatStateUsing(fn ($state, ?JobOrder $record): float => $state === null
                        ? (float) ($record?->subtotal ?? 0) / max(1, (int) ($record?->quantity ?? 1))
                        : (float) $state)
                    ->live(onBlur: true)
                    ->dehydrated(false)
                    ->disabled(fn (?JobOrder $record): bool => $record?->pricingIsLocked() ?? false)
                    ->afterStateUpdated(fn (UtilitiesGet $get, UtilitiesSet $set) => self::updatePricing($get, $set))
                    ->visible(fn (UtilitiesGet $get): bool => PanelAccess::canSeeMoneyValues() && $get('production_mode') === 'make_to_order'),
                DatePicker::make('due_date')
                    ->label('Payment Due Date')
                    ->hidden(fn (UtilitiesGet $get) => $get('production_mode') === 'make_to_stock')
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn (mixed $state, UtilitiesGet $get): mixed => $get('production_mode') === 'make_to_stock' ? null : $state),
                Hidden::make('subtotal')
                    ->default(0)
                    ->dehydrated(fn (?JobOrder $record): bool => ! $record?->pricingIsLocked())
                    ->dehydrateStateUsing(fn (UtilitiesGet $get): float => self::subtotalFromUnitPrice($get)),
                Hidden::make('tax_amount')
                    ->default(0)
                    ->dehydrated(fn (?JobOrder $record): bool => ! $record?->pricingIsLocked())
                    ->dehydrateStateUsing(fn (UtilitiesGet $get): float => self::pricing($get)['tax_amount']),
                Hidden::make('total')
                    ->default(0)
                    ->dehydrated(fn (?JobOrder $record): bool => ! $record?->pricingIsLocked())
                    ->dehydrateStateUsing(fn (UtilitiesGet $get): float => self::pricing($get)['total']),
                Group::make([
                    Placeholder::make('summary_subtotal')
                        ->label('Subtotal')
                        ->content(fn (UtilitiesGet $get): HtmlString => self::summaryValue($get('subtotal')))
                        ->extraAttributes(['class' => 'cost-summary-metric']),
                    Placeholder::make('summary_tax_amount')
                        ->label('Tax (VAT)')
                        ->content(fn (UtilitiesGet $get): HtmlString => self::summaryValue($get('tax_amount')))
                        ->extraAttributes(['class' => 'cost-summary-metric']),
                    Placeholder::make('summary_total')
                        ->label('Total')
                        ->content(fn (UtilitiesGet $get): HtmlString => self::summaryValue($get('total'), isPrimary: true))
                        ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),
                ])->extraAttributes(['class' => 'orderSummary'])
                    ->visible(fn (UtilitiesGet $get): bool => PanelAccess::canSeeMoneyValues() && $get('production_mode') === 'make_to_order'),
                Textarea::make('remarks')->rows(2),
            ])->compact(),
        ])->columns(['default' => 1, 'lg' => 3]);
    }

    private static function pricingLocked(?JobOrder $record): bool
    {
        return $record && (filled($record->proforma_task_id) || (string) $record->status !== 'draft');
    }

    private static function subtotalFromUnitPrice(UtilitiesGet $get): float
    {
        return $get('production_mode') === 'make_to_stock' ? 0.0 : round((float) $get('quantity') * (float) $get('unit_price'), 2);
    }

    /** @return array{subtotal: float, tax_amount: float, total: float} */
    private static function pricing(UtilitiesGet $get): array
    {
        $subtotal = self::subtotalFromUnitPrice($get);
        $settings = Setting::getSettings();
        $tax = round($subtotal * ($settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0), 2);

        return ['subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => round($subtotal + $tax, 2)];
    }

    private static function updatePricing(UtilitiesGet $get, UtilitiesSet $set): void
    {
        foreach (self::pricing($get) as $field => $value) {
            $set($field, $value);
        }
    }

    private static function summaryValue(mixed $amount, bool $isPrimary = false): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-value%s">%s %s</span>',
            $isPrimary ? ' cost-summary-value-primary' : '',
            e(Number::format((float) ($amount ?? 0), precision: 2)),
            e(Money::suffix()),
        ));
    }
}
