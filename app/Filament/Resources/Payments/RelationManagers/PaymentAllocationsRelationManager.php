<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentAllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentAllocations';

    protected static ?string $title = 'Applied To';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        $transactionType = $ownerRecord->transaction_type ?? match ($ownerRecord->payment_type ?? null) {
            'expense' => 'direct_expense',
            'petty_cash' => $ownerRecord->direction === 'inbound' ? 'petty_cash_funding' : 'petty_cash_expense',
            default => $ownerRecord->direction === 'outbound' ? 'supplier_payment' : 'customer_receipt',
        };

        return in_array($transactionType, ['customer_receipt', 'supplier_payment'], true);
    }

    public function form(Schema $schema): Schema
    {
        // Allocations are created from the order side — no create form needed here.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('allocatable_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        JobOrder::class => 'info',
                        SalesOrder::class => 'success',
                        PurchaseOrder::class => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        JobOrder::class => 'Job Order',
                        SalesOrder::class => 'Sales Order',
                        PurchaseOrder::class => 'Purchase Order',
                        default => class_basename($state),
                    }),
                TextColumn::make('document_number')
                    ->label('Document #')
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn ($record) => $record->allocatable?->partner?->name),
                TextColumn::make('allocated_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold')
                    ->color('success'),
            ])
            ->recordActions([
                ActionGroup::make([
                    DeleteAction::make(),
                ]),
            ]);
    }
}
