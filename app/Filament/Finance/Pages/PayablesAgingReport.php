<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\PayablesAgingExporter;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Support\Money;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PayablesAgingReport extends Page implements HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'A/P Aging';

    protected static ?int $navigationSort = 350;

    protected static string|UnitEnum|null $navigationGroup = 'Financial Reports';

    protected string $view = 'filament.finance.pages.payables-aging-report';

    public string $asOfDate;

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    public function report(): array
    {
        $rows = $this->agingQuery()->get()->map(function ($record) {
            $record->age_days = $this->ageDays($record);
            $record->bucket = $this->bucket($record);

            return $record;
        });

        return [
            'total' => (float) $rows->sum('balance'),
            'over_30' => (float) $rows->where('age_days', '>', 30)->sum('balance'),
            'count' => $rows->count(),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                DatePicker::make('asOfDate')
                    ->label('As Of Date')
                    ->live()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->agingQuery())
            ->columns([
                TextColumn::make('po_number')->label('Document #')->searchable(),
                TextColumn::make('partner.name')->label('Vendor')->searchable(),
                TextColumn::make('order_date')->label('Date')->date(),
                TextColumn::make('balance')->label('Outstanding')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->summarize(Sum::make()->label('Total Outstanding')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('age_days')
                    ->label('Age (Days)')
                    ->state(fn ($record) => $this->ageDays($record)),
                TextColumn::make('bucket')
                    ->label('Bucket')
                    ->state(fn ($record) => $this->bucket($record))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('partner_id')
                    ->label('Vendor')
                    ->relationship('partner', 'name'),
            ])
            ->defaultSort('order_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(PayablesAgingExporter::class),
            ]);
    }

    protected function agingQuery(): Builder
    {
        $invoiceQuery = Invoice::query()
            ->selectRaw('invoices.id as id')
            ->selectRaw('invoices.invoice_number as po_number')
            ->selectRaw('invoices.partner_id as partner_id')
            ->selectRaw('invoices.invoice_date as order_date')
            ->selectRaw('invoices.due_date as due_date')
            ->selectRaw("'received' as status")
            ->selectRaw('invoices.created_at as created_at')
            ->selectRaw('invoices.updated_at as updated_at')
            ->selectRaw('invoices.subtotal as subtotal')
            ->selectRaw('invoices.tax_amount as tax_amount')
            ->selectRaw('invoices.total_amount as total')
            ->selectRaw('invoices.balance_due as balance')
            ->where('invoices.invoice_type', 'purchase')
            ->whereNotIn('invoices.status', ['cancelled', 'paid'])
            ->where('invoices.balance_due', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('invoices.order_type')
                    ->orWhere('invoices.order_type', '!=', 'purchase_order')
                    ->orWhereNull('invoices.order_id');
            })
            ->when($this->asOfDate, fn ($query) => $query->whereDate('invoices.invoice_date', '<=', Carbon::parse($this->asOfDate)->toDateString()));

        return PurchaseOrder::query()
            ->with('partner')
            ->selectRaw('purchase_orders.id as id')
            ->selectRaw('purchase_orders.po_number as po_number')
            ->selectRaw('purchase_orders.partner_id as partner_id')
            ->selectRaw('purchase_orders.order_date as order_date')
            ->selectRaw('purchase_orders.due_date as due_date')
            ->selectRaw('purchase_orders.status as status')
            ->selectRaw('purchase_orders.created_at as created_at')
            ->selectRaw('purchase_orders.updated_at as updated_at')
            ->selectRaw('purchase_orders.subtotal as subtotal')
            ->selectRaw('purchase_orders.tax_amount as tax_amount')
            ->selectRaw('purchase_orders.total as total')
            ->selectRaw('(COALESCE(purchase_orders.total, 0) - COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.payable_type = ? AND payments.payable_id = purchase_orders.id AND payments.payment_date <= ? AND payments.voided_at IS NULL), 0)) as balance', [PurchaseOrder::class, $this->asOfDate])
            ->whereRaw('(COALESCE(purchase_orders.total, 0) - COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.payable_type = ? AND payments.payable_id = purchase_orders.id AND payments.payment_date <= ? AND payments.voided_at IS NULL), 0)) > 0', [PurchaseOrder::class, $this->asOfDate])
            ->when($this->asOfDate, fn ($query) => $query->whereDate('purchase_orders.order_date', '<=', Carbon::parse($this->asOfDate)->toDateString()))
            ->unionAll($invoiceQuery)
            ->orderByDesc('order_date');
    }

    public function ageDays($record): int
    {
        return Carbon::parse($record->order_date)->diffInDays(Carbon::parse($this->asOfDate));
    }

    public function bucket($record): string
    {
        $days = $this->ageDays($record);

        return match (true) {
            $days <= 30 => 'Current',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }
}
