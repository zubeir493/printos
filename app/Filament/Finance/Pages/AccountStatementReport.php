<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\AccountStatementExporter;
use App\Models\Account;
use App\Models\JournalItem;
use App\Support\Money;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AccountStatementReport extends Page implements HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Account Statements';

    protected static ?int $navigationSort = 330;

    protected static string|UnitEnum|null $navigationGroup = 'Financial Reports';

    protected string $view = 'filament.finance.pages.account-statement-report';

    public ?int $accountId = null;

    public string $startDate;

    public string $endDate;

    public function mount(): void
    {
        $this->accountId = Account::orderBy('code')->value('id');
        $this->startDate = now()->startOfYear()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function accounts()
    {
        return Account::orderBy('code')->pluck('name', 'id');
    }

    public function openingBalance(): float
    {
        if (! $this->accountId) {
            return 0.0;
        }

        return (float) JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_items.account_id', $this->accountId)
            ->when($this->startDate, fn ($query) => $query->whereDate('journal_entries.date', '<', Carbon::parse($this->startDate)->toDateString()))
            ->sum(DB::raw('journal_items.debit - journal_items.credit'));
    }

    public function report(): array
    {
        $openingBalance = $this->openingBalance();
        $statementRows = $this->statementQuery()->get();

        return [
            'opening_balance' => $openingBalance,
            'total_debit' => (float) $statementRows->sum('debit'),
            'total_credit' => (float) $statementRows->sum('credit'),
            'closing_balance' => $openingBalance + (float) $statementRows->sum(fn ($row) => $row->debit - $row->credit),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        Select::make('accountId')
                            ->label('Account')
                            ->options(Account::orderBy('code')->pluck('name', 'id'))
                            ->live()
                            ->required()
                            ->searchable(),
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
            ->query($this->statementQuery())
            ->columns([
                TextColumn::make('entry_date')->label('Date')->date()->sortable(),
                TextColumn::make('entry_reference')->label('Reference')->searchable(),
                TextColumn::make('entry_narration')->label('Narration')->wrap()->searchable(),
                TextColumn::make('debit')->label('Debit')->formatStateUsing(fn ($state) => Money::format($state))->summarize(Sum::make()->label('Total Debit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('credit')->label('Credit')->formatStateUsing(fn ($state) => Money::format($state))->summarize(Sum::make()->label('Total Credit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('running_balance')
                    ->label('Running Balance')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable(),
            ])
            ->defaultSort('entry_date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(AccountStatementExporter::class),
            ]);
    }

    protected function statementQuery(): Builder
    {
        if (! $this->accountId) {
            return JournalItem::query()->whereRaw('1 = 0');
        }

        return JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_items.account_id', $this->accountId)
            ->when($this->startDate, fn ($query) => $query->whereDate('journal_entries.date', '>=', Carbon::parse($this->startDate)->toDateString()))
            ->when($this->endDate, fn ($query) => $query->whereDate('journal_entries.date', '<=', Carbon::parse($this->endDate)->toDateString()))
            ->select('journal_items.*')
            ->selectRaw('journal_entries.date as entry_date')
            ->selectRaw('journal_entries.reference as entry_reference')
            ->selectRaw('journal_entries.narration as entry_narration')
            ->selectRaw('
                SUM(journal_items.debit - journal_items.credit) OVER (
                    ORDER BY journal_entries.date, journal_entries.id, journal_items.id
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) as running_balance
            ')
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_items.id');
    }
}
