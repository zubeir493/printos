<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\FinancialAccountExporter;
use App\Models\Account;
use App\Services\Accounting\FinancialReportService;
use App\Support\Money;
use BackedEnum;
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

class BalanceSheetReport extends Page implements HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Balance Sheet';

    protected static ?int $navigationSort = 380;

    protected static string|UnitEnum|null $navigationGroup = 'Financial Reports';

    protected string $view = 'filament.finance.pages.balance-sheet-report';

    public string $asOfDate;

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    public function report(): array
    {
        return app(FinancialReportService::class)->balanceSheet($this->asOfDate);
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
            ->query($this->accountQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->searchable(['code', 'name'])
                    ->formatStateUsing(fn (?string $state, $record) => "{$record->code} - {$record->name}"),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('debit_total')->label('Debit')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->summarize(Sum::make()->label('Total Debit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('credit_total')->label('Credit')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->summarize(Sum::make()->label('Total Credit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('display_amount')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->summarize(Sum::make()->label('Total Amount')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Account Type')
                    ->options($this->accountTypeOptions()),
            ])
            ->defaultSort('code')
            ->headerActions([
                ExportAction::make()
                    ->exporter(FinancialAccountExporter::class),
            ]);
    }

    protected function accountTypeOptions(): array
    {
        return Account::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type', 'type')
            ->toArray();
    }

    protected function accountQuery(): Builder
    {
        return Account::query()
            ->select('accounts.*')
            ->leftJoin('journal_items', 'journal_items.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join): void {
                $join->on('journal_entries.id', '=', 'journal_items.journal_entry_id')
                    ->where('journal_entries.status', 'posted')
                    ->when($this->asOfDate, fn ($query) => $query->whereDate('journal_entries.date', '<=', $this->asOfDate));
            })
            ->whereIn('accounts.type', ['Asset', 'Liability', 'Equity'])
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NULL THEN 0 ELSE journal_items.debit END), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NULL THEN 0 ELSE journal_items.credit END), 0) as credit_total')
            ->selectRaw("
                CASE
                    WHEN accounts.type = 'Asset' THEN COALESCE(SUM(CASE WHEN journal_entries.id IS NULL THEN 0 ELSE journal_items.debit - journal_items.credit END), 0)
                    ELSE COALESCE(SUM(CASE WHEN journal_entries.id IS NULL THEN 0 ELSE journal_items.credit - journal_items.debit END), 0)
                END as display_amount
            ")
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NULL THEN 0 ELSE journal_items.debit - journal_items.credit END), 0) as balance')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code');
    }
}
