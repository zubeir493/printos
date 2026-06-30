<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\GeneralLedgerExporter;
use App\Models\Account;
use App\Models\JournalItem;
use App\Support\FiscalCalendar;
use App\Support\Money;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid as ComponentsGrid;
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

class GeneralLedgerReport extends Page implements HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'General Ledger';

    protected static ?int $navigationSort = 320;

    protected static string|UnitEnum|null $navigationGroup = 'Financial Reports';

    protected string $view = 'filament.finance.pages.general-ledger-report';

    public ?int $accountId = null;

    public string $startDate;

    public string $endDate;

    public function mount(): void
    {
        $this->accountId = null;
        $this->startDate = FiscalCalendar::currentFiscalYearStart()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function accounts(): array
    {
        return Account::orderBy('code')->pluck('name', 'id')->toArray();
    }

    public function report(): array
    {
        $query = $this->ledgerQuery()->get();

        return [
            'total_debit' => (float) $query->sum('debit'),
            'total_credit' => (float) $query->sum('credit'),
            'net_movement' => (float) $query->sum(fn ($row) => $row->debit - $row->credit),
            'entry_count' => $query->count(),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                ComponentsGrid::make(3)
                    ->schema([
                        Select::make('accountId')
                            ->label('Account')
                            ->options($this->accounts())
                            ->live()
                            ->searchable()
                            ->placeholder('All Accounts'),
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->live()
                            ->required(),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->live()
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->ledgerQuery())
            ->columns([
                TextColumn::make('account_name')
                    ->label('Account')
                    ->searchable(['account_code', 'account_name'])
                    ->formatStateUsing(fn (?string $state, $record) => "{$record->account_code} - {$record->account_name}"),
                TextColumn::make('account_type')->label('Account Type')->badge(),
                TextColumn::make('entry_reference')->label('Reference')->searchable(),
                TextColumn::make('entry_date')->label('Date')->date()->sortable(),
                TextColumn::make('debit')->label('Debit')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->summarize(Sum::make()->label('Total Debit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('credit')->label('Credit')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->summarize(Sum::make()->label('Total Credit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('running_balance')->label('Running Balance')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->label('Account Type')
                    ->options($this->accountTypeOptions())
                    ->query(fn (Builder $query, array $data) => $query->where('accounts.type', $data['value'])),
            ])
            ->defaultSort('entry_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(GeneralLedgerExporter::class),
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

    protected function ledgerQuery(): Builder
    {
        return JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
            ->where('journal_entries.status', 'posted')
            ->when($this->accountId, fn ($query) => $query->where('journal_items.account_id', $this->accountId))
            ->when($this->startDate, fn ($query) => $query->whereDate('journal_entries.date', '>=', Carbon::parse($this->startDate)->toDateString()))
            ->when($this->endDate, fn ($query) => $query->whereDate('journal_entries.date', '<=', Carbon::parse($this->endDate)->toDateString()))
            ->select('journal_items.*')
            ->selectRaw('journal_entries.date as entry_date')
            ->selectRaw('journal_entries.reference as entry_reference')
            ->selectRaw('journal_entries.narration as entry_narration')
            ->selectRaw('accounts.code as account_code')
            ->selectRaw('accounts.name as account_name')
            ->selectRaw('accounts.type as account_type')
            ->selectRaw(
                'SUM(journal_items.debit - journal_items.credit) OVER (PARTITION BY journal_items.account_id ORDER BY journal_entries.date, journal_entries.id, journal_items.id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as running_balance',
            )
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_items.id');
    }
}
