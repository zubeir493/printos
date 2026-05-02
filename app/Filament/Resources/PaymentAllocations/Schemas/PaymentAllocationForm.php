<?php

namespace App\Filament\Resources\PaymentAllocations\Schemas;

use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentAllocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payment_id')
                    ->relationship('payment', 'payment_number')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('allocated_amount')
                    ->required()
                    ->numeric()
                    ->prefix('Birr'),

                Select::make('allocatable_type')
                    ->label('Document Type')
                    ->options([
                        'App\\Models\\JobOrder'      => 'Job Order',
                        'App\\Models\\PurchaseOrder' => 'Purchase Order',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn(Set $set) => $set('allocatable_id', null)),

                Select::make('allocatable_id')
                    ->label('Document #')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function (Get $get) {
                        $type = $get('allocatable_type');

                        return match ($type) {
                            'App\\Models\\JobOrder'      => JobOrder::pluck('job_order_number', 'id'),
                            'App\\Models\\PurchaseOrder' => PurchaseOrder::pluck('po_number', 'id'),
                            default                     => [],
                        };
                    })
                    ->disabled(fn(Get $get) => !$get('allocatable_type')),
            ]);
    }
}
