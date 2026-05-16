<?php

namespace App\Filament\Resources\Partners\RelationManagers;

use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'JobOrders';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_order_number')
                    ->required(),
                TextInput::make('job_type')
                    ->required(),
                Textarea::make('services')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('submission_date')
                    ->required(),
                Textarea::make('remarks')
                    ->columnSpanFull(),
                Toggle::make('advance_paid')
                    ->required(),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('status')
                    ->required(),
                TextInput::make('advance_amount')
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('job_order_number')
                    ->searchable(),
                TextColumn::make('job_type')
                    ->searchable(),
                TextColumn::make('submission_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('advance_paid')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->paymentAllocations()->exists())
                    ->label('Adv. Paid'),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->defaultSort('submission_date', 'desc')
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DissociateAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
