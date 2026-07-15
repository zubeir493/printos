<?php

namespace App\Filament\Resources\MaterialRequests;

use App\Filament\Resources\MaterialRequests\Pages\ManageMaterialRequests;
use App\Filament\Support\MaterialRequestActionForms;
use App\Filament\Support\PanelAccess;
use App\Models\JobOrder;
use App\Models\MaterialRequest;
use App\Services\MaterialIssueService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialRequestResource extends Resource
{
    protected static ?string $model = MaterialRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?int $navigationSort = 120;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereColumn('issued_quantity', '<', 'requested_quantity')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['inventoryItem', 'jobOrderTask.jobOrder']);
    }

    // public static function canViewAny(): bool
    // {
    //     return PanelAccess::canAccessWarehouseSection();
    // }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Select::make('job_order_task_id')
                            ->label('Production Task')
                            ->relationship(
                                'jobOrderTask',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->with('jobOrder'),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->jobOrder->job_order_number})")
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('inventory_item_id')
                            ->label('Material')
                            ->relationship('inventoryItem', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])->columnSpanFull(),
                Grid::make(3)
                    ->schema([
                        TextInput::make('required_quantity')
                            ->label('Total Required')
                            ->required()
                            ->numeric()
                            ->readOnly(),
                        TextInput::make('requested_quantity')
                            ->label('Quantity Requested')
                            ->required()
                            ->numeric(),
                        TextInput::make('issued_quantity')
                            ->label('Quantity Issued')
                            ->numeric()
                            ->default(0)
                            ->readOnly(),
                    ])->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobOrderTask.name')
                    ->label('Task')
                    ->description(fn ($record) => $record->jobOrderTask->jobOrder->job_order_number)
                    ->weight('bold')
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('inventoryItem.name')
                    ->label('Material')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->pendingIssueApprovals()->exists()) {
                            return 'Awaiting Approval';
                        }

                        if ($record->issued_quantity >= $record->requested_quantity) {
                            return 'Issued';
                        }

                        if ($record->issued_quantity > 0) {
                            return 'Partial';
                        }

                        return 'Pending';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Issued' => 'success',
                        'Awaiting Approval' => 'warning',
                        'Partial' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('requested_quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state, $record) => "{$record->issued_quantity} / {$state} {$record->inventoryItem->unit}")
                    ->description('Issued / Requested')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('job_order_id')
                    ->label('Job Order')
                    ->options(JobOrder::pluck('job_order_number', 'id'))
                    ->searchable()
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $query->whereHas('jobOrderTask', fn ($q) => $q->where('job_order_id', $data['value']));
                        }
                    }),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'issued' => 'Issued',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'pending') {
                            $query->where('issued_quantity', 0);
                        }

                        if ($data['value'] === 'partial') {
                            $query->where('issued_quantity', '>', 0)->whereColumn('issued_quantity', '<', 'requested_quantity');
                        }

                        if ($data['value'] === 'issued') {
                            $query->whereColumn('issued_quantity', '>=', 'requested_quantity');
                        }
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('issue')
                        ->label('Issue')
                        ->icon('heroicon-m-archive-box-arrow-down')
                        ->color('gray')
                        ->modalHeading(fn (MaterialRequest $record): string => "Issue {$record->inventoryItem->name}")
                        ->modalWidth('lg')
                        ->visible(fn ($record) => PanelAccess::canAccessWarehouseSection() && $record->issued_quantity < $record->requested_quantity && ! $record->pendingIssueApprovals()->exists())
                        ->form([
                            MaterialRequestActionForms::warehouseSelect(),
                            MaterialRequestActionForms::singleIssueQuantityInput(),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                \DB::beginTransaction();
                                if (! $record) {
                                    throw new \Exception('Material request not found.');
                                }
                                $result = app(MaterialIssueService::class)->issue($record, (int) $data['warehouse_id'], (float) $data['quantity'], auth()->user());
                                \DB::commit();
                                Notification::make()
                                    ->title($result['status'] === 'pending_approval' ? 'Over-issue sent for approval' : 'Materials Issued')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \DB::rollBack();
                                Notification::make()
                                    ->title('Error Issuing Materials')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaterialRequests::route('/'),
        ];
    }
}
