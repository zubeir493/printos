<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\PayablesAgingExporter;
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
        return PurchaseOrder::query()
            ->with('partner')
            ->select('purchase_orders.*')
            ->selectRaw('(COALESCE(purchase_orders.subtotal, 0) - COALESCE((SELECT SUM(payment_allocations.allocated_amount) FROM payment_allocations JOIN payments ON payments.id = payment_allocations.payment_id WHERE payment_allocations.allocatable_type = ? AND payment_allocations.allocatable_id = purchase_orders.id AND payments.payment_date <= ?), 0)) as balance', [PurchaseOrder::class, $this->asOfDate])
            ->whereRaw('(COALESCE(purchase_orders.subtotal, 0) - COALESCE((SELECT SUM(payment_allocations.allocated_amount) FROM payment_allocations JOIN payments ON payments.id = payment_allocations.payment_id WHERE payment_allocations.allocatable_type = ? AND payment_allocations.allocatable_id = purchase_orders.id AND payments.payment_date <= ?), 0)) > 0', [PurchaseOrder::class, $this->asOfDate])
            ->when($this->asOfDate, fn ($query) => $query->whereDate('purchase_orders.order_date', '<=', Carbon::parse($this->asOfDate)->toDateString()))
            ->orderByDesc('purchase_orders.order_date');
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
