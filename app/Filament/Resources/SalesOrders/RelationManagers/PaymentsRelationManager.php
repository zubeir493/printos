<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Enums\PaymentTransactionType;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Payment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentAllocations';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return PanelAccess::canAccessFinanceSection();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('allocated_amount')
                    ->label('Amount')
                    ->numeric()
                    ->required()
                    ->suffix('Birr')
                    ->maxValue(function ($record) {
                        $owner = $this->getOwnerRecord();
                        $currentAllocation = $record ? $record->allocated_amount : 0;

                        return max(0, $owner->total - ($owner->paid_amount - $currentAllocation));
                    }),
                Select::make('method')
                    ->label('Paid Via')
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
                    ->options(fn () => Bank::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get) => $get('method') === 'bank')
                    ->required(fn (callable $get) => $get('method') === 'bank')
                    ->helperText('Select the bank account for this payment'),

                TextInput::make('reference')
                    ->label('Memo / Reference')
                    ->maxLength(255)
                    ->placeholder('For example: receipt number, bill number, or short note')
                    ->helperText('Use this field for later lookup and audit reference.'),
                DatePicker::make('payment_date')
                    ->default(now())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment.payment_number')
            ->columns([
                TextColumn::make('payment.payment_number')
                    ->label('Payment #')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn ($record) => $record->payment->payment_date?->format('M j, Y') ?? 'No date'),
                TextColumn::make('payment.method')
                    ->label('Method')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'cash' => 'success',
                        'bank' => 'info',
                        'cheque' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('payment.reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Reference copied')
                    ->copyMessageDuration(1500),
                TextColumn::make('payment.payment_date')
                    ->label('Date')
                    ->date(),
                TextColumn::make('allocated_amount')
                    ->label('Allocated')
                    ->suffix(' Birr')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(
                        Sum::make()
                            ->label('Payment Summary')
                            ->formatStateUsing(function ($state) {
                                $owner = $this->getOwnerRecord();
                                $allocated = $state ?? 0;
                                $total = $owner->subtotal ?? 0;

                                return "{$allocated}/{$total} Birr";
                            })
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        return DB::transaction(function () use ($data) {
                            $salesOrder = $this->getOwnerRecord();

                            $payment = Payment::create([
                                'partner_id' => $salesOrder->partner_id,
                                'amount' => $data['allocated_amount'],
                                'direction' => 'inbound',
                                'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                'method' => $data['method'],
                                'bank_id' => $data['bank_id'] ?? null,
                                'reference' => $data['reference'] ?? null,
                                'payment_date' => $data['payment_date'],
                            ]);

                            return $salesOrder->paymentAllocations()->create([
                                'payment_id' => $payment->id,
                                'allocated_amount' => $data['allocated_amount'],
                            ]);
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
