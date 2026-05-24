<?php

namespace App\Filament\Resources\Users\Tables;

use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->color(function ($state): string|array {
                        $roleValue = is_string($state) ? $state : ($state instanceof UserRole ? $state->value : (string) $state);

                        return match ($roleValue) {
                            'admin' => Color::Indigo,
                            'operations' => 'info',
                            'finance' => 'warning',
                            'sales' => 'success',
                            'retail' => Color::Lime,
                            'hr' => Color::Purple,
                            'design' => Color::Pink,
                            'typist' => Color::Teal,
                            'production' => Color::Orange,
                            'warehouse' => Color::Cyan,
                            default => 'gray',
                        };
                    })
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        UserRole::Admin->value => 'Admin',
                        UserRole::Design->value => 'Design',
                        UserRole::Typist->value => 'Typist',
                        UserRole::Operations->value => 'Operations',
                        UserRole::Finance->value => 'Finance',
                        UserRole::Sales->value => 'Sales',
                        UserRole::Retail->value => 'Retail',
                        UserRole::HR->value => 'HR',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('changePassword')
                        ->label('Change Password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->form([
                            TextInput::make('password')
                                ->password()
                                ->required()
                                ->revealable(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'password' => $data['password'], // Casts to hashed in model
                            ]);
                            Notification::make()
                                ->title('Password updated successfully')
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
