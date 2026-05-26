<?php

namespace App\Filament\Resources\Proformas\Schemas;

use App\Filament\Support\PanelAccess;
use App\Models\InventoryItem;
use App\Models\Proforma;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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

                                    return "PF-{$year}-" . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
                                })
                                ->readOnly()
                                ->dehydrated(false),
                            Select::make('partner_id')
                                ->label('Customer')
                                ->relationship('partner', 'name', modifyQueryUsing: fn($query) => $query->where('is_customer', true))
                                ->searchable()
                                ->preload()
                                ->required(),
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
                                    ->afterStateUpdated(fn(Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set))
                                    ->afterStateHydrated(fn(Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set)),
                                TextInput::make('size'),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('Birr')
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set))
                                    ->afterStateHydrated(fn(Get $get, Set $set): mixed => self::refreshCurrentTaskTotals($get, $set)),
                                TextInput::make('task_cost')->numeric()->suffix('Birr')->default(0)->readOnly()->required(),
                                Repeater::make('paper')
                                    ->table([
                                        TableColumn::make('Paper')->alignLeft(),
                                        TableColumn::make('Required qty')->alignLeft(),
                                        TableColumn::make('Reserve qty')->alignLeft(),
                                    ])
                                    ->schema([
                                        Select::make('inventory_item_id')
                                            ->label('Material')
                                            ->options(fn(): array => InventoryItem::query()
                                                ->rawMaterials()
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('required_quantity')->numeric()->minValue(0)->default(0),
                                        TextInput::make('reserve_quantity')->numeric()->minValue(0)->default(0),
                                    ])
                                    ->columnSpanFull(),
                                Repeater::make('deliverables')
                                    ->schema([
                                        TextInput::make('label'),
                                        Select::make('type')
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
                        Hidden::make('cost_estimate_id'),
                        TextInput::make('subtotal')
                            ->numeric()
                            ->suffix('Birr')
                            ->readOnly()
                            ->hidden(fn() => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),
                        TextInput::make('tax_amount')
                            ->label('VAT')
                            ->numeric()
                            ->suffix('Birr')
                            ->readOnly()
                            ->hidden(fn() => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),
                        TextInput::make('total')
                            ->numeric()
                            ->suffix('Birr')
                            ->readOnly()
                            ->hidden(fn() => ! PanelAccess::canSeeMoneyValues())
                            ->dehydratedWhenHidden(),
                        Hidden::make('email_recipient'),
                    ]),
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
}
