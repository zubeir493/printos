<?php

namespace App\Filament\Resources\Proformas\Schemas;

use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Models\Proforma;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ProformaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('proforma_number')
                                ->label('Proforma #')
                                ->default(function (): string {
                                    $year = now()->format('Y');
                                    $lastProforma = Proforma::query()
                                        ->where('proforma_number', 'like', "PF-{$year}-%")
                                        ->orderByDesc('id')
                                        ->first();

                                    $lastNumber = 0;

                                    if ($lastProforma && preg_match("/PF-{$year}-(\d+)/", $lastProforma->proforma_number, $matches)) {
                                        $lastNumber = (int) $matches[1];
                                    }

                                    return "PF-{$year}-".str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
                                })
                                ->readOnly()
                                ->dehydrated(false),
                            Select::make('partner_id')
                                ->label('Customer')
                                ->relationship('partner', 'name', modifyQueryUsing: fn ($query) => $query->where('is_customer', true))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                    TextInput::make('phone'),
                                    TextInput::make('email')->email(),
                                    TextInput::make('address'),
                                    Hidden::make('is_customer')->default(true),
                                ]),
                            Select::make('job_type')
                                ->options([
                                    'books' => 'Books',
                                    'packages' => 'Packages',
                                    'labels' => 'Labels',
                                    'vouchers' => 'Vouchers',
                                ])
                                ->required(),
                            DatePicker::make('issue_date')
                                ->default(now())
                                ->required(),
                            DatePicker::make('expiry_date')
                                ->afterOrEqual('issue_date')
                                ->default(now()->addDays(15))
                                ->required(),
                        ]),
                        Repeater::make('tasks')
                            ->relationship()
                            ->label('Tasks')
                            ->table([
                                TableColumn::make('Task')->alignLeft(),
                                TableColumn::make('Qty')->alignLeft(),
                                TableColumn::make('Size')->alignLeft(),
                                TableColumn::make('Unit price')->alignLeft(),
                                TableColumn::make('Total')->alignLeft(),
                            ])
                            ->compact()
                            ->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set))
                                    ->afterStateHydrated(fn (Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set)),
                                TextInput::make('size'),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('Birr')
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set))
                                    ->afterStateHydrated(fn (Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set)),
                                TextInput::make('task_cost')->numeric()->suffix('Birr')->default(0)->readOnly()->required(),
                                Repeater::make('paper')
                                    ->label('Paper used for this task')
                                    ->table([
                                        TableColumn::make('Paper')->alignLeft(),
                                        TableColumn::make('Required qty (sheets)')->alignLeft(),
                                        TableColumn::make('Reserve qty (sheets)')->alignLeft(),
                                    ])
                                    ->compact()
                                    ->schema([
                                        Select::make('inventory_item_id')
                                            ->label('Material')
                                            ->options(fn (): array => InventoryItem::query()
                                                ->orderBy('name')
                                                ->rawMaterials()
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('required_quantity')->numeric()->minValue(0)->default(0),
                                        TextInput::make('reserve_quantity')->numeric()->minValue(0)->default(0),
                                    ])
                                    ->defaultItems(1)
                                    ->addable(false)
                                    ->reorderable(false)
                                    ->extraItemActions([
                                        Action::make('add_paper')
                                            ->label('Add Paper')
                                            ->icon('heroicon-o-plus')
                                            ->action(function (Repeater $component): void {
                                                $state = $component->getState() ?? [];
                                                $state[(string) Str::uuid()] = [
                                                    'inventory_item_id' => null,
                                                    'required_quantity' => 0,
                                                    'reserve_quantity' => 0,
                                                ];
                                                $component->state($state);
                                            }),
                                    ])
                                    ->columnSpanFull(),
                                Repeater::make('deliverables')
                                    ->label('Required files for this task')
                                    ->table([
                                        TableColumn::make('Name')->alignLeft(),
                                        TableColumn::make('File Type')->alignLeft(),
                                    ])
                                    ->compact()
                                    ->defaultItems(1)
                                    ->addable(false)
                                    ->reorderable(false)
                                    ->extraItemActions([
                                        Action::make('add_deliverable')
                                            ->label('Add Deliverable')
                                            ->icon('heroicon-o-plus')
                                            ->action(function (Repeater $component): void {
                                                $state = $component->getState() ?? [];
                                                $state[(string) Str::uuid()] = [
                                                    'label' => '',
                                                    'type' => 'artwork',
                                                ];
                                                $component->state($state);
                                            }),
                                    ])
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Deliverable')
                                            ->placeholder('Cover Artwork'),
                                        Select::make('type')
                                            ->label('Type')
                                            ->options([
                                                'artwork' => 'Artwork',
                                                'text_file' => 'Text File',
                                            ])
                                            ->default('artwork')
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                                Textarea::make('instructions')->columnSpanFull(),
                            ])
                            ->columns(5)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addable(false)
                            ->extraItemActions([
                                Action::make('add_task')
                                    ->label('Add Task')
                                    ->icon('heroicon-o-plus')
                                    ->action(function (Repeater $component): void {
                                        $state = $component->getState() ?? [];
                                        $state[(string) Str::uuid()] = [
                                            'name' => '',
                                            'quantity' => 1,
                                            'size' => null,
                                            'unit_price' => 0,
                                            'task_cost' => 0,
                                            'paper' => [
                                                (string) Str::uuid() => [
                                                    'inventory_item_id' => null,
                                                    'required_quantity' => 0,
                                                    'reserve_quantity' => 0,
                                                ],
                                            ],
                                            'deliverables' => [],
                                        ];
                                        $component->state($state);
                                    }),
                            ])
                            ->columnSpanFull(),
                        Textarea::make('remarks')->columnSpanFull(),
                    ])
                    ->columnSpan(3),
                Section::make()
                    ->schema([
                        Hidden::make('subtotal')
                            ->default(0)
                            ->dehydrated(),
                        Hidden::make('tax_amount')
                            ->default(0)
                            ->dehydrated(),
                        Hidden::make('total')
                            ->default(0)
                            ->dehydrated(),

                        Placeholder::make('summary_subtotal')
                            ->label('Subtotal')
                            ->content(fn (Get $get): HtmlString => self::summaryValue($get('subtotal')))
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_tax_amount')
                            ->label('Tax (VAT)')
                            ->content(fn (Get $get): HtmlString => self::summaryValue($get('tax_amount')))
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->extraAttributes(['class' => 'cost-summary-metric']),

                        Placeholder::make('summary_total')
                            ->label('Total')
                            ->content(fn (Get $get): HtmlString => self::summaryValue($get('total'), isPrimary: true))
                            ->hidden(fn () => ! PanelAccess::canSeeMoneyValues())
                            ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),
                    ])
                    ->extraAttributes(['class' => 'lg:sticky lg:top-6 orderSummary']),
            ])
            ->columns(4);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $tasks
     */
    private static function refreshCurrentTaskTotals(Get $get, Set $set): void
    {
        $quantity = max(1, (int) ($get('quantity') ?? 1));
        $unitPrice = round((float) ($get('unit_price') ?? 0), 2);

        $set('task_cost', round($quantity * $unitPrice, 2));
        self::refreshTotals($get('../../tasks') ?? [], $set);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $tasks
     */
    private static function refreshTotals(array $tasks, Set $set): void
    {
        $subtotal = round((float) collect($tasks)
            ->sum(function (array $task): float {
                $quantity = max(1, (int) ($task['quantity'] ?? 1));
                $unitPrice = round((float) ($task['unit_price'] ?? 0), 2);

                return round($quantity * $unitPrice, 2);
            }), 2);
        $settings = Setting::getSettings();
        $taxRate = $settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0.0;
        $taxAmount = round($subtotal * $taxRate, 2);

        $set('../../subtotal', $subtotal);
        $set('../../tax_amount', $taxAmount);
        $set('../../total', round($subtotal + $taxAmount, 2));
    }

    private static function summaryValue(mixed $amount, bool $isPrimary = false): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-value%s">%s Birr</span>',
            $isPrimary ? ' cost-summary-value-primary' : '',
            e(Number::format((float) ($amount ?? 0), precision: 2)),
        ));
    }
}
