<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Primary column: who did what to which record
                TextColumn::make('activity')
                    ->label('Activity')
                    ->weight('bold')
                    ->state(fn ($record): string => self::buildSentence($record))
                    ->description(fn ($record): string => $record->created_at->format('M j, Y H:i'))
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('causer', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                    )
                    ->wrap(),

                // Area + action in one compact badge column
                TextColumn::make('log_name')
                    ->label('Area')
                    ->badge()
                    ->color(fn ($record): string => match ($record->event) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title()),

                // What actually changed — only meaningful for updates
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(fn ($record): string => self::buildChangeSummary($record))
                    ->color('gray')
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created' => '✦ Created',
                        'updated' => '✎ Updated',
                        'deleted' => '✕ Deleted',
                    ]),

                SelectFilter::make('log_name')
                    ->label('Area')
                    ->options([
                        'job_order' => 'Job Orders',
                        'job_order_task' => 'Job Order Tasks',
                        'purchase_order' => 'Purchase Orders',
                        'sales_order' => 'Sales Orders',
                        'invoice' => 'Invoices',
                        'finance' => 'Payments',
                        'bank_transfer' => 'Bank Transfers',
                        'journal_entry' => 'Journal Entries',
                        'partner' => 'Partners',
                        'inventory' => 'Inventory Items',
                        'material_request' => 'Material Requests',
                        'stock_adjustment' => 'Stock Adjustments',
                        'goods_receipt' => 'Goods Receipts',
                        'dispatch' => 'Dispatches',
                        'artwork' => 'Artworks',
                        'employee' => 'Employees',
                    ])
                    ->searchable(),

                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                Filter::make('date_range')
                    ->label('Date range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->format('M j, Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->format('M j, Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordUrl(fn ($record) => ActivityLogResource::getUrl('view', ['record' => $record]))
            ->paginated([25, 50, 100])
            ->striped();
    }

    /**
     * Build a plain-English sentence: "Zubeyr created Job Order #42"
     */
    private static function buildSentence(mixed $record): string
    {
        $actor = $record->causer?->name ?? 'System';
        $action = match ($record->event) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'logged',
        };
        $subject = $record->subject_type ? class_basename($record->subject_type) : null;
        $id = $record->subject_id;

        if ($subject && $id) {
            $attrs = $record->properties['attributes'] ?? [];
            $ref = $attrs['job_order_number'] ?? $attrs['po_number'] ?? $attrs['order_number']
                ?? $attrs['invoice_number'] ?? $attrs['payment_number'] ?? $attrs['transfer_number']
                ?? $attrs['adjustment_number'] ?? $attrs['receipt_number'] ?? $attrs['employee_id']
                ?? $attrs['name'] ?? null;

            $subjectLabel = $ref ? "#{$ref}" : "#{$id}";

            return "{$actor} {$action} {$subjectLabel}";
        }

        return "{$actor} {$action} a record";
    }

    /**
     * Summarise what changed: "Status: active → completed | Due date: changed"
     */
    private static function buildChangeSummary(mixed $record): string
    {
        if ($record->event !== 'updated') {
            return $record->event === 'created' ? 'New record created' : 'Record deleted';
        }

        $new = $record->properties['attributes'] ?? [];
        $old = $record->properties['old'] ?? [];

        if (empty($new) && empty($old)) {
            return '—';
        }

        // Skip noisy system fields
        $skip = ['updated_at', 'created_at', 'remember_token', 'password'];
        $parts = [];

        foreach ($new as $field => $newVal) {
            if (in_array($field, $skip, true)) {
                continue;
            }

            $oldVal = $old[$field] ?? null;
            $fieldLabel = str($field)->replace('_', ' ')->title();

            if (is_array($newVal) || is_array($oldVal)) {
                $parts[] = "{$fieldLabel}: changed";
            } elseif ((string) $oldVal !== (string) $newVal) {
                $oldDisplay = filled($oldVal) ? "\"{$oldVal}\"" : 'empty';
                $newDisplay = filled($newVal) ? "\"{$newVal}\"" : 'empty';
                $parts[] = "{$fieldLabel}: {$oldDisplay} → {$newDisplay}";
            }

            if (count($parts) >= 3) {
                $remaining = count($new) - count($skip) - 3;
                if ($remaining > 0) {
                    $parts[] = "+{$remaining} more fields";
                }
                break;
            }
        }

        return implode(' | ', $parts) ?: '—';
    }
}
