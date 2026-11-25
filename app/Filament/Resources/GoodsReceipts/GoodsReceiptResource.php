<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Services\PostDirectGoodsReceipt;
use App\Support\Money;
use App\Support\PrivateStorage;
use App\Support\WarehouseAccess;
use App\UserRole;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 70;

    public static function getNavigationBadge(): ?string
    {
        // Count only draft goods receipts (exclude posted)
        $count = static::getEloquentQuery()->where('status', 'draft')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return WarehouseAccess::scope(parent::getEloquentQuery(), 'goods_receipts.warehouse_id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['supplier', 'purchaseOrder.partner', 'supplierBill']))
            ->columns([
                Tables\Columns\TextColumn::make('source')
                    ->state(fn (GoodsReceipt $record): string => $record->sourceLabel())
                    ->badge()
                    ->description(fn (GoodsReceipt $record): ?string => $record->supplier?->name ?? $record->purchaseOrder?->partner?->name),
                Tables\Columns\TextColumn::make('source_reference')
                    ->label('Reference')
                    ->state(fn (GoodsReceipt $record): string => $record->source_reference ?? $record->purchaseOrder?->po_number ?? $record->receipt_number)
                    ->description(fn (GoodsReceipt $record): string => $record->receipt_number)
                    ->searchable(['source_reference', 'receipt_number']),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                Tables\Columns\TextColumn::make('receipt_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (string $state, GoodsReceipt $record): string => $record->reversed_at ? 'Reversed' : ucfirst($state))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('receipt_date_range', 'receipt_date', 'Receipt date'),
            ])
            ->recordActions([self::receiptActions()])
            ->defaultSort('receipt_date', 'desc');
    }

    private static function receiptActions(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('viewReceipt')->label('View receipt')->icon('heroicon-o-eye')
                ->modalHeading(fn (GoodsReceipt $record): string => $record->receipt_number)
                ->modalDescription('Review the quantities received and any bill or reversal linked to this receipt.')
                ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->schema([
                    TextEntry::make('reversal_reason')->visible(fn (GoodsReceipt $record): bool => filled($record->reversed_at)),
                    TextEntry::make('supplierBill.invoice_number')->label('Matched bill')
                        ->visible(fn (GoodsReceipt $record): bool => PanelAccess::canAccessFinanceSection() && $record->supplierBill !== null)
                        ->url(fn (GoodsReceipt $record): ?string => $record->supplierBill
                            ? InvoiceResource::getUrl('view', ['record' => $record->supplierBill]) : null),
                    RepeatableEntry::make('items')->label('Received items')
                        ->state(fn (GoodsReceipt $record): array => $record->items()->with(['inventoryItem', 'purchaseOrderItem.inventoryItem'])->get()
                            ->map(fn (GoodsReceiptItem $line): array => [
                                'item_name' => ($line->inventoryItem ?? $line->purchaseOrderItem?->inventoryItem)?->name,
                                'quantity_received' => $line->quantity_received,
                                'unit_cost' => $line->unit_cost,
                            ])->all())
                        ->schema([
                            TextEntry::make('item_name')->label('Item'),
                            TextEntry::make('quantity_received')->label('Received'),
                            TextEntry::make('unit_cost')->label('Cost per base unit')
                                ->money(fn (): string => Money::currencyCode())
                                ->visible(fn (): bool => PanelAccess::canSeeMoneyValues()),
                        ])->columns(3),
                ]),
            Action::make('matchSupplierBill')->label('Match supplier bill')->icon('heroicon-o-link')
                ->authorize(fn (): bool => PanelAccess::canAccessFinanceSection()
                    && in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Finance], true))
                ->visible(fn (GoodsReceipt $record): bool => $record->purchase_order_id === null && $record->supplier_id !== null
                    && $record->posted_at && ! $record->reversed_at && ! $record->supplierBill)
                ->modalDescription('Create a draft supplier bill for Finance review. Enter the amounts from the bill and attach its PDF.')
                ->modalSubmitActionLabel('Match bill')
                ->schema([
                    TextInput::make('bill_number')->label('Supplier invoice number')->required()->maxLength(120),
                    DatePicker::make('invoice_date')->default(today())->required(),
                    DatePicker::make('due_date')->default(today()->addDays(30))->required(),
                    TextInput::make('subtotal')->numeric()->minValue(0.01)->step(0.01)->required(),
                    TextInput::make('tax_amount')->numeric()->minValue(0)->step(0.01)->default(0)->required(),
                    FileUpload::make('document')->label('Supplier bill PDF')->model(Invoice::class)
                        ->disk(PrivateStorage::diskName())
                        ->directory('invoices/supplier-bills')->visibility('private')->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)->preventFilePathTampering()->required(),
                ])
                ->action(fn (GoodsReceipt $record, array $data, Schema $schema, PostDirectGoodsReceipt $service) => self::runReceiptAction(
                    $schema, fn () => $service->recordSupplierBill($record, $data), 'Supplier bill matched',
                )),
            Action::make('unmatchSupplierBill')->label('Unmatch draft bill')->icon('heroicon-o-link-slash')->color('gray')
                ->authorize(fn (): bool => PanelAccess::canAccessFinanceSection()
                    && in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Finance], true))
                ->visible(fn (GoodsReceipt $record): bool => in_array($record->supplierBill?->status, ['draft', 'cancelled'], true))
                ->modalDescription('Unlink the bill so you can match the correct one. Unmatching also cancels a draft bill.')
                ->modalSubmitActionLabel('Unmatch bill')
                ->schema([Textarea::make('reason')->label('Reason for unmatching')->required()->maxLength(1000)])
                ->action(fn (GoodsReceipt $record, array $data, Schema $schema, PostDirectGoodsReceipt $service) => self::runReceiptAction(
                    $schema, fn () => $service->unmatchSupplierBill($record, $data['reason']), 'Supplier bill unmatched',
                )),
            Action::make('reverseDirectReceipt')->label('Reverse receipt')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->authorize(fn (GoodsReceipt $record): bool => in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Operations], true)
                    && WarehouseAccess::allows((int) $record->warehouse_id))
                ->visible(fn (GoodsReceipt $record): bool => $record->purchase_order_id === null
                    && $record->posted_at && ! $record->reversed_at && ! $record->supplierBill)
                ->modalDescription('Remove the received quantities from this warehouse and record why. There must be enough stock on hand to reverse the receipt.')
                ->modalSubmitActionLabel('Reverse receipt')
                ->schema([Textarea::make('reason')->label('Reason for reversal')->required()->maxLength(1000)])
                ->action(fn (GoodsReceipt $record, array $data, Schema $schema, PostDirectGoodsReceipt $service) => self::runReceiptAction(
                    $schema, fn () => $service->reverse($record, $data['reason']), 'Receipt reversed',
                )),
        ])->label('Receipt actions');
    }

    private static function runReceiptAction(Schema $schema, \Closure $callback, string $title): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$schema->getStatePath().'.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        Notification::make()->success()->title($title)->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
        ];
    }
}
