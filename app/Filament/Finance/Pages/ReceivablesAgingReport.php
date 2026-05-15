<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\ReceivablesAgingExporter;
use App\Models\SalesOrder;
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

class ReceivablesAgingReport extends Page implements HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'A/R Aging';

    protected static ?int $navigationSort = 340;

    protected static string|UnitEnum|null $navigationGroup = 'Financial Reports';

    protected string $view = 'filament.finance.pages.receivables-aging-report';

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
                TextColumn::make('order_number')->label('Document #')->searchable(),
                TextColumn::make('partner.name')->label('Customer')->searchable(),
                TextColumn::make('order_date')->label('Date')->date(),
                TextColumn::make('balance')->label('Outstanding')->suffix(' Birr')->sortable()->summarize(Sum::make()->label('Total Outstanding')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
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
                    ->label('Customer')
                    ->relationship('partner', 'name'),
            ])
            ->defaultSort('order_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(ReceivablesAgingExporter::class),
            ]);
    }

    protected function agingQuery(): Builder
    {
        return SalesOrder::query()
            ->with('partner')
            ->select('sales_orders.*')
            ->selectRaw('(sales_orders.total - COALESCE((SELECT SUM(payment_allocations.allocated_amount) FROM payment_allocations JOIN payments ON payments.id = payment_allocations.payment_id WHERE payment_allocations.allocatable_type = ? AND payment_allocations.allocatable_id = sales_orders.id AND payments.payment_date <= ?), 0)) as balance', [SalesOrder::class, $this->asOfDate])
            ->whereRaw('(sales_orders.total - COALESCE((SELECT SUM(payment_allocations.allocated_amount) FROM payment_allocations JOIN payments ON payments.id = payment_allocations.payment_id WHERE payment_allocations.allocatable_type = ? AND payment_allocations.allocatable_id = sales_orders.id AND payments.payment_date <= ?), 0)) > 0', [SalesOrder::class, $this->asOfDate])
            ->when($this->asOfDate, fn ($query) => $query->whereDate('sales_orders.order_date', '<=', Carbon::parse($this->asOfDate)->toDateString()))
            ->orderByDesc('sales_orders.order_date');
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
