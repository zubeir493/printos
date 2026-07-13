<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Models\Account;
use App\Models\AccountingIntegration;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'asset' => 'Asset',
                        'liability' => 'Liability',
                        'equity' => 'Equity',
                        'revenue' => 'Revenue',
                        'expense' => 'Expense',
                    ]),
            ])
            ->recordActions([
                Action::make('mapAccountingAccounts')
                    ->label('Account overrides')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->hidden(fn (): bool => self::activeIntegrations()->isEmpty())
                    ->fillForm(fn ($record): array => self::mappingFormState($record))
                    ->schema(fn ($record): array => self::mappingFormSchema($record))
                    ->modalWidth('lg')
                    ->action(function (array $data, $record): void {
                        foreach (self::activeIntegrations() as $integration) {
                            $field = self::mappingField($integration);
                            $value = trim((string) ($data[$field] ?? ''));

                            if (blank($value) || $value === $record->code) {
                                $integration->mappings()
                                    ->where('account_id', $record->id)
                                    ->delete();

                                continue;
                            }

                            $integration->mappings()->updateOrCreate(
                                ['account_id' => $record->id],
                                ['external_account_id' => $value],
                            );
                        }

                        Notification::make()
                            ->title('Account overrides saved')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return Collection<int, AccountingIntegration> */
    private static function activeIntegrations(): Collection
    {
        return AccountingIntegration::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get();
    }

    private static function mappingField(AccountingIntegration $integration): string
    {
        return "integration_{$integration->id}";
    }

    private static function mappingFormState(Account $record): array
    {
        return self::activeIntegrations()
            ->mapWithKeys(function (AccountingIntegration $integration) use ($record): array {
                $mapping = $record->accountingMappings
                    ->firstWhere('accounting_integration_id', $integration->id);

                return [self::mappingField($integration) => $mapping?->external_account_id ?? $record->code];
            })
            ->all();
    }

    private static function mappingFormSchema(Account $record): array
    {
        return self::activeIntegrations()
            ->map(fn (AccountingIntegration $integration): TextInput => TextInput::make(self::mappingField($integration))
                ->label("{$integration->name} Account Override")
                ->placeholder($record->code)
                ->helperText("Leave blank to use {$record->code}.")
                ->maxLength(255))
            ->values()
            ->all();
    }
}
