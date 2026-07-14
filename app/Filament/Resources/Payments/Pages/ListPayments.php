<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Imports\PaymentImporter;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\PaymentsStatsWidget;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public const TABLE_TABS = [
        'all' => 'All',
        'income' => 'Income',
        'expenses' => 'Expenses',
    ];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ImportAction::make()
                    ->importer(PaymentImporter::class),
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(self::TABLE_TABS['all']),
            'income' => Tab::make(self::TABLE_TABS['income'])
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereIn('transaction_type', [
                    PaymentTransactionType::CUSTOMER_RECEIPT->value,
                    PaymentTransactionType::CASH_SALE_RECEIPT->value,
                ])),
            'expenses' => Tab::make(self::TABLE_TABS['expenses'])
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereIn('transaction_type', [
                    PaymentTransactionType::DIRECT_EXPENSE->value,
                    PaymentTransactionType::PETTY_CASH_EXPENSE->value,
                ])),
        ];
    }
}
