<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\PaymentsStatsWidget;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(PaymentExporter::class),
            ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentsStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'income' => Tab::make('Income')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereIn('transaction_type', [
                    PaymentTransactionType::CUSTOMER_RECEIPT->value,
                    PaymentTransactionType::CASH_SALE_RECEIPT->value,
                ])),
            'expenses' => Tab::make('Expenses')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereIn('transaction_type', [
                    PaymentTransactionType::DIRECT_EXPENSE->value,
                    PaymentTransactionType::PETTY_CASH_EXPENSE->value,
                ])),
        ];
    }
}
