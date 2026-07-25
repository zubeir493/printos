<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Enums\PaymentTransactionType;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bank;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return PanelAccess::canAccessFinanceSection();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount')
                ->numeric()
                ->required()
                ->live(onBlur: true)
                ->suffix(fn (): string => Money::suffix())
                ->maxValue(fn (?Model $record): float => $this->remainingBalance($record)),
            TextInput::make('withholding_amount')
                ->label('Withholding')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(fn (callable $get): float => (float) ($get('amount') ?? 0))
                ->suffix(fn (): string => Money::suffix()),
            Select::make('method')
                ->label('Payment method')
                ->options([
                    'cash' => 'Cash',
                    'bank' => 'Bank Transfer',
                    'cheque' => 'Cheque',
                ])
                ->default('bank')
                ->required()
                ->live(),
            Select::make('bank_id')
                ->label('Bank Account')
                ->options(fn (): array => Bank::query()->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->visible(fn (callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                ->required(fn (callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
            TextInput::make('reference')
                ->label('Memo / Reference')
                ->maxLength(255),
            DatePicker::make('payment_date')
                ->default(now())
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_number')
            ->columns([
                TextColumn::make('payment_number')->label('Payment #')->weight('bold')->searchable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('reference')->limit(40),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold'),
                TextColumn::make('withholding_amount')
                    ->label('Withholding')
                    ->formatStateUsing(fn ($state) => Money::format($state)),
            ])
            ->filters([
                DateRangeFilter::make('payment_date_range', 'payment_date', 'Payment date'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $purchaseOrder = $this->getOwnerRecord();

                        return [
                            ...$data,
                            'partner_id' => $purchaseOrder->partner_id,
                            'direction' => 'outbound',
                            'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
                            'reference' => $data['reference'] ?: 'Payment for '.$purchaseOrder->po_number,
                        ];
                    }),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    private function remainingBalance(?Model $record = null): float
    {
        $owner = $this->getOwnerRecord();
        $currentAmount = $record ? (float) $record->amount : 0.0;

        return max(0, (float) $owner->total - ((float) $owner->paid_amount - $currentAmount));
    }
}
