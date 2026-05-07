<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Payment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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
                Hidden::make('payment_number')
                    ->dehydrated(false),

                TextInput::make('allocated_amount')
                    ->label('Amount')
                    ->numeric()
                    ->required()
                    ->suffix('Birr'),

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
                    ->helperText('Select bank account for this payment'),

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
            ->recordTitleAttribute('payment_number')
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
                    ->label('Reference'),
                TextColumn::make('payment.payment_date')
                    ->label('Date')
                    ->date(),
                TextColumn::make('allocated_amount')
                    ->label('Allocated')
                    ->suffix(' Birr')
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
                    ->using(function (array $data, string $model): Model {
                        return DB::transaction(function () use ($data) {
                            $purchaseOrder = $this->getOwnerRecord();

                            $payment = Payment::create([
                                'partner_id' => $purchaseOrder->partner_id,
                                'amount' => $data['allocated_amount'],
                                'direction' => 'outbound',
                                'method' => $data['method'],
                                'bank_id' => $data['bank_id'] ?? null,
                                'reference' => $data['reference'] ?? null,
                                'payment_date' => $data['payment_date'],
                            ]);

                            return $purchaseOrder->paymentAllocations()->create([
                                'payment_id' => $payment->id,
                                'allocated_amount' => $data['allocated_amount'],
                            ]);
                        });
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
