<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Event Details')
                    ->columns(6)            
                    ->columnSpanFull()        
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('When')
                            ->dateTime('M j, Y H:i:s'),

                        TextEntry::make('causer.name')
                            ->label('User')
                            ->default('System'),

                        TextEntry::make('event')
                            ->label('Action')
                            ->badge()
                            ->color(fn (?string $state) => match ($state) {
                                'created' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state) => ucfirst($state ?? 'logged')),

                        TextEntry::make('log_name')
                            ->label('Area')
                            ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title()),

                        TextEntry::make('subject_type')
                            ->label('Record Type')
                            ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                        TextEntry::make('subject_id')
                            ->label('Record ID')
                            ->default('—'),
                    ]),

                Section::make('What Changed')
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('new_values')
                            ->label('New Values')
                            ->state(fn ($record) => self::formatProperties(
                                $record->properties['attributes'] ?? []
                            ))
                            ->html()
                            ->columnSpanFull(),

                        TextEntry::make('old_values')
                            ->label('Previous Values')
                            ->state(fn ($record) => self::formatProperties(
                                $record->properties['old'] ?? []
                            ))
                            ->html()
                            ->columnSpanFull(),
                    ]),
        ]);
    }

    private static function formatProperties(mixed $data): string
    {
        if (empty($data) || ! is_array($data)) {
            return '<span class="text-gray-400 text-sm">—</span>';
        }

        $rows = '';
        foreach ($data as $key => $value) {
            $label = htmlspecialchars(str($key)->replace('_', ' ')->title()->toString());
            $display = is_array($value)
                ? htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT))
                : htmlspecialchars((string) ($value ?? ''));
            $rows .= "<tr><td class=\"pr-4 py-0.5 text-xs font-medium text-gray-500 whitespace-nowrap\">{$label}</td>"
                ."<td class=\"py-0.5 text-xs text-gray-800 break-all\">{$display}</td></tr>";
        }

        return "<table class=\"w-full\">{$rows}</table>";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Log')
                ->url(ActivityLogResource::getUrl())
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray'),
        ];
    }
}
