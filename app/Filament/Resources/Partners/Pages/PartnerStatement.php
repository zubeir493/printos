<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Partners\Widgets\PartnerStatementStats;
use App\Models\Partner;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PartnerStatement extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PartnerResource::class;

    protected string $view = 'filament.resources.partners.pages.statement';

    public function getTitle(): string|Htmlable
    {
        return $this->record->name.' Statement';
    }

    /**
     * @return array{
     *     receivable_total: float,
     *     payable_total: float,
     *     inbound_payments_total: float,
     *     outbound_payments_total: float,
     *     net_balance: float
     * }
     */
    public function statementSummary(): array
    {
        /** @var Partner $partner */
        $partner = $this->record;

        return $partner->statementSummary();
    }

    public function statementRows(): Collection
    {
        /** @var Partner $partner */
        $partner = $this->record;

        return $partner->statementRows();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $search, ?string $sortColumn, ?string $sortDirection, int|string $recordsPerPage, int|string $page): LengthAwarePaginator|Collection => $this->statementTableRecords(
                filters: $filters ?? [],
                search: $search,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
                recordsPerPage: $recordsPerPage,
                page: (int) $page,
            ))
            ->columns([
                TextColumn::make('reference')
                    ->label('Document')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->description(fn (array $record): string => collect([
                        $record['type'] ?? null,
                        $record['description'] ?? null,
                    ])->filter()->implode(' - ')),
                TextColumn::make('category')
                    ->label('Class')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'receivable' => 'Receivable',
                        'payable' => 'Payable',
                        'payment' => 'Payment',
                        default => str($state)->headline()->toString(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'receivable' => 'primary',
                        'payable' => 'danger',
                        'payment' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn (float|int|string|null $state): string => $state ? Money::format((float) $state) : '-')
                    ->sortable(),
                TextColumn::make('paid')
                    ->label('Paid')
                    ->alignEnd()
                    ->formatStateUsing(fn (float|int|string|null $state): string => $state ? Money::format((float) $state) : '-')
                    ->color('success')
                    ->sortable(),
                TextColumn::make('open_balance')
                    ->label('Open Balance')
                    ->alignEnd()
                    ->formatStateUsing(fn (float|int|string|null $state): string => $state ? Money::format((float) $state) : '-')
                    ->color(fn (float|int|string|null $state): string => ((float) $state) > 0 ? 'warning' : 'gray')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        'paid', 'posted', 'completed' => 'success',
                        'partial', 'sent', 'submitted', 'approved', 'received' => 'warning',
                        'cancelled', 'voided' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('activity')
                    ->label('Activity')
                    ->default('documents')
                    ->options([
                        'documents' => 'Documents only',
                        'all' => 'Documents and payments',
                        'payment' => 'Payments only',
                    ]),
                SelectFilter::make('category')
                    ->label('Class')
                    ->options([
                        'receivable' => 'Receivables',
                        'payable' => 'Payables',
                        'payment' => 'Payments',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => $this->statementRows()
                        ->pluck('status')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->mapWithKeys(fn (string $status): array => [$status => str($status)->headline()->toString()])
                        ->all()),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No financial activity yet')
            ->emptyStateDescription('Orders, invoices, and payments for this partner will appear here.');
    }

    protected function statementTableRecords(
        array $filters,
        ?string $search,
        ?string $sortColumn,
        ?string $sortDirection,
        int|string $recordsPerPage,
        int $page,
    ): LengthAwarePaginator|Collection {
        $records = $this->statementRows();

        $activity = $filters['activity']['value'] ?? 'documents';
        if ($activity === 'documents') {
            $records = $records->whereIn('category', ['receivable', 'payable']);
        } elseif ($activity === 'payment') {
            $records = $records->where('category', 'payment');
        }

        $category = $filters['category']['value'] ?? null;
        if (filled($category)) {
            $records = $records->where('category', $category);
        }

        $status = $filters['status']['value'] ?? null;
        if (filled($status)) {
            $records = $records->where('status', $status);
        }

        if (filled($search)) {
            $needle = str($search)->lower()->toString();
            $records = $records->filter(fn (array $row): bool => str($row['type'])->lower()->contains($needle)
                || str($row['reference'])->lower()->contains($needle)
                || str($row['description'])->lower()->contains($needle)
                || str($row['status'])->lower()->contains($needle));
        }

        if (filled($sortColumn)) {
            $records = $records->sortBy(
                fn (array $row): mixed => $row[$sortColumn] ?? null,
                descending: $sortDirection === 'desc',
            );
        }

        $records = $records->values();

        if ($recordsPerPage === 'all') {
            return $records;
        }

        $perPage = (int) $recordsPerPage;

        return new LengthAwarePaginator(
            items: $records->forPage($page, $perPage)->values(),
            total: $records->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPartner')
                ->label('Partner Details')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->record])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PartnerStatementStats::class,
        ];
    }
}
