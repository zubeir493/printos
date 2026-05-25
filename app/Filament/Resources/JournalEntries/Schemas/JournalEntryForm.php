<?php

namespace App\Filament\Resources\JournalEntries\Schemas;

use App\Models\Account;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class JournalEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()->schema([
                    Repeater::make('JournalItems')
                        ->relationship('JournalItems') // This is the magic link!
                        ->table([
                            TableColumn::make('Account')->alignLeft(),
                            TableColumn::make('Debit')->alignLeft(),
                            TableColumn::make('Credit')->alignLeft(),
                            TableColumn::make('Party')->alignRight(),
                        ])
                        ->schema([
                            Select::make('account_id')
                                ->relationship('account', 'name')
                                ->options(function () {
                                    return Account::all()
                                        ->groupBy('type')
                                        ->map(function ($accounts) {
                                            return $accounts->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"]);
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('debit')->numeric()->default(0)->suffix('Birr')->live(),
                            TextInput::make('credit')->numeric()->default(0)->suffix('Birr')->live(),
                            Select::make('party')
                                ->label('Party')
                                ->options([
                                    '🤝 Partners' => Partner::pluck('name', 'id')
                                        ->mapWithKeys(fn ($n, $id) => ["Partner:{$id}" => "{$n}"])
                                        ->toArray(),
                                    '📑 Job Orders' => JobOrder::pluck('job_order_number', 'id')
                                        ->mapWithKeys(fn ($n, $id) => ["JobOrder:{$id}" => "#{$n}"])
                                        ->toArray(),
                                ])
                                ->searchable()
                                ->preload(),

                        ])
                        ->minItems(2)->defaultItems(2)->columnSpanFull()->compact()->addActionLabel('Add line')
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $items = $get('JournalItems') ?? [];

                            $totalDebit = collect($items)->sum(fn ($item) => (float) ($item['debit'] ?? 0));
                            $totalCredit = collect($items)->sum(fn ($item) => (float) ($item['credit'] ?? 0));

                            $set('total_debit', $totalDebit);
                            $set('total_credit', $totalCredit);
                        })
                        // Handle recalculation when a row is removed
                        ->deleteAction(
                            fn ($action) => $action->after(function (Get $get, Set $set) {
                                $items = $get('JournalItems') ?? [];
                                $set('total_debit', collect($items)->sum(fn ($item) => (float) ($item['debit'] ?? 0)));
                                $set('total_credit', collect($items)->sum(fn ($item) => (float) ($item['credit'] ?? 0)));
                            })
                        ),
                ])->columnSpanFull()->columns(2),
                Section::make('Additional Information')->schema([
                    Group::make()->schema([
                        Group::make()->schema([
                            TextInput::make('reference')
                                ->placeholder('PT-CASH-01-2026')
                                ->required(),
                            DatePicker::make('date')
                                ->default(now())
                                ->required(),
                        ])->columns(2),
                        FileUpload::make('attachment')
                            ->maxSize(5120)
                            ->disk('s3')
                            ->visibility('private')
                            ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                            ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                            ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                            ->directory('accounting/attachments'),
                        Placeholder::make('download_attachment')
                            ->label('')
                            ->hidden(fn ($record) => empty($record?->attachment))
                            ->content(function ($record) {
                                if (! $record || empty($record->attachment)) {
                                    return null;
                                }
                                $url = PrivateStorage::downloadUrl($record->attachment, now()->addMinutes(60));

                                return new HtmlString(
                                    '<div class="p-4 bg-gray-50 rounded-xl border border-dashed border-gray-300 flex items-center justify-center">'.
                                    '<a href="'.$url.'" target="_blank" class="text-primary-600 hover:text-primary-800 font-medium flex items-center gap-2">'.
                                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>'.
                                    'Download Attachment'.
                                    '</a>'.
                                    '</div>'
                                );
                            }),
                        Textarea::make('narration')->columnSpanFull(),
                    ])->columnSpan(3),
                    Group::make()->schema([
                        TextInput::make('total_debit')->numeric()->default(0)->suffix('Birr')->disabled()->dehydrated()->same('total_credit'),
                        TextInput::make('total_credit')->numeric()->default(0)->suffix('Birr')->disabled()->dehydrated(),
                    ])->columnSpan(2),
                ])->columnSpanFull()->columns(5),
            ])->columns(3)->alignCenter();
    }
}
