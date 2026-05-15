<?php

namespace App\Filament\Finance\Pages;

use App\Filament\Exports\FinancialAccountExporter;
use App\Models\Account;
use App\Services\Accounting\FinancialReportService;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
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

class TrialBalanceReport extends Page implements HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public function getReportProperty(): array
    {
        return app(FinancialReportService::class)->trialBalance($this->startDate, $this->endDate);
    }

    protected static ?string $navigationLabel = 'Trial Balance';

    protected static ?int $navigationSort = 310;

    protected static string|UnitEnum|null $navigationGroup = 'Financial Reports';

    protected string $view = 'filament.finance.pages.trial-balance-report';

    public string $startDate;

    public string $endDate;

    public function mount(): void
    {
        $this->startDate = now()->startOfYear()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function report(): array
    {
        return app(FinancialReportService::class)->trialBalance($this->startDate, $this->endDate);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
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
            ->query($this->accountQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->searchable(['code', 'name'])
                    ->formatStateUsing(fn (?string $state, $record) => "{$record->code} - {$record->name}"),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('debit_total')->label('Debit')->suffix(' Birr')->summarize(Sum::make()->label('Total Debit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('credit_total')->label('Credit')->suffix(' Birr')->summarize(Sum::make()->label('Total Credit')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
                TextColumn::make('balance')->label('Balance')->suffix(' Birr')->summarize(Sum::make()->label('Total Balance')->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])),
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
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entries.id', '=', 'journal_items.journal_entry_id')
                    ->where('journal_entries.status', 'posted');
            })
            ->when($this->startDate, fn ($query) => $query->whereDate('journal_entries.date', '>=', $this->startDate))
            ->when($this->endDate, fn ($query) => $query->whereDate('journal_entries.date', '<=', $this->endDate))
            ->selectRaw('COALESCE(SUM(journal_items.debit), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(journal_items.credit), 0) as credit_total')
            ->selectRaw('COALESCE(SUM(journal_items.debit), 0) - COALESCE(SUM(journal_items.credit), 0) as balance')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code');
    }
}
