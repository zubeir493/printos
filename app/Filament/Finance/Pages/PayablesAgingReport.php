<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\PayablesAgingExporter;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Support\Money;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
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
use Illuminate\Database\Query\JoinClause;
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

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(PayablesAgingExporter::class),
            ]),
        ];
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
            ->defaultSort('order_date', 'desc');
    }

    protected function agingQuery(): Builder
    {
        $invoices = $this->withPostedPayable(Invoice::query(), Invoice::class)
            ->selectRaw('invoices.id * 2 + 1 as id, invoices.invoice_number as po_number')
            ->addSelect('invoices.partner_id', 'invoices.due_date', 'invoices.status', 'invoices.created_at', 'invoices.updated_at', 'invoices.subtotal', 'invoices.tax_amount')
            ->selectRaw('invoices.invoice_date as order_date, posted_payable.amount as total')
            ->selectRaw('posted_payable.amount - COALESCE(disbursements.paid_amount, 0) as balance')
            ->where('invoices.invoice_type', 'purchase')
            ->where(fn (Builder $query) => $query->whereNull('invoices.order_type')
                ->orWhere('invoices.order_type', '!=', 'purchase_order')
                ->orWhereNull('invoices.order_id'))
            ->whereDate('invoices.invoice_date', '<=', $this->asOfDate);

        $orders = $this->withPostedPayable(PurchaseOrder::query(), PurchaseOrder::class)
            ->selectRaw('purchase_orders.id * 2 as id, purchase_orders.po_number')
            ->addSelect('purchase_orders.partner_id', 'purchase_orders.due_date', 'purchase_orders.status', 'purchase_orders.created_at', 'purchase_orders.updated_at', 'purchase_orders.subtotal', 'purchase_orders.tax_amount')
            ->selectRaw('purchase_orders.order_date, posted_payable.amount as total')
            ->selectRaw('posted_payable.amount - COALESCE(disbursements.paid_amount, 0) as balance')
            ->where('purchase_orders.status', 'received')
            ->whereDate('purchase_orders.order_date', '<=', $this->asOfDate);

        return PurchaseOrder::query()
            ->fromSub($orders->toBase()->unionAll($invoices->toBase()), 'purchase_orders')
            ->select('purchase_orders.*')
            ->where('balance', '>', 0)
            ->with('partner')
            ->orderByDesc('order_date');
    }

    /** @param class-string<Invoice|PurchaseOrder> $model */
    private function withPostedPayable(Builder $query, string $model): Builder
    {
        $afterDate = Carbon::parse($this->asOfDate)->startOfDay()->addDay();
        $postedPayable = JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
            ->where('accounts.code', Account::CODE_AP)
            ->where('journal_entries.source_type', $model)
            ->where('journal_entries.status', 'posted')
            ->whereNull('journal_entries.reversal_of_journal_entry_id')
            ->whereDate('journal_entries.date', '<=', $this->asOfDate)
            ->where(fn (Builder $query) => $query->whereNull('journal_entries.posted_at')->orWhere('journal_entries.posted_at', '<', $afterDate))
            ->where(fn (Builder $query) => $query->whereNull('journal_entries.voided_at')->orWhere('journal_entries.voided_at', '>=', $afterDate))
            ->select('journal_entries.source_id')->selectRaw('SUM(journal_items.credit - journal_items.debit) as amount')
            ->groupBy('journal_entries.source_id');
        $payments = Payment::query()->where('payable_type', $model)->where('direction', 'outbound')
            ->effectiveOn($this->asOfDate)
            ->select('payable_id')->selectRaw('SUM(amount) as paid_amount')->groupBy('payable_id');
        $key = $query->getModel()->getQualifiedKeyName();

        return $query
            ->joinSub($postedPayable, 'posted_payable', fn (JoinClause $join) => $join->on('posted_payable.source_id', '=', $key))
            ->leftJoinSub($payments, 'disbursements', fn (JoinClause $join) => $join->on('disbursements.payable_id', '=', $key));
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
