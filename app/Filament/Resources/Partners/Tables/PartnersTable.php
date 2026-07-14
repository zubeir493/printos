<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Filament\Exports\PartnerExporter;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Partner')
                    ->description(fn ($record) => $record->phone)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->is_customer && $record->is_supplier) {
                            return 'Both';
                        }

                        return $record->is_customer ? 'Customer' : 'Supplier';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Customer' => 'success',
                        'Supplier' => 'info',
                        'Both' => 'primary',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('partner_type')
                    ->label('Type')
                    ->options([
                        'supplier' => 'Supplier',
                        'customer' => 'Customer',
                    ])
                    ->query(function ($query, $state) {
                        if (is_array($state)) {
                            return $query;
                        }

                        return $state === 'supplier'
                            ? $query->where('is_supplier', true)
                            : ($state === 'customer' ? $query->where('is_customer', true) : $query);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->color('gray')
                        ->visible(fn () => PanelAccess::canManagePartners()),
                    Action::make('statement')
                        ->label('Statement')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->color('gray')
                        ->url(fn ($record): string => PartnerResource::getUrl('statement', ['record' => $record])),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManagePartners()),
                    ExportBulkAction::make()
                        ->exporter(PartnerExporter::class)
                        ->visible(fn () => PanelAccess::canManagePartners()),
                ]),
            ]);
    }
}
