<?php

namespace App\Filament\Resources\JobOrders;

use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\EditJobOrder;
use App\Filament\Resources\JobOrders\Pages\ListJobOrders;
use App\Filament\Resources\JobOrders\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\JobOrders\Schemas\JobOrderForm;
use App\Filament\Resources\JobOrders\Tables\JobOrdersTable;
use App\Filament\Support\PanelAccess;
use App\Models\JobOrder;
use App\Support\Money;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\GlobalSearch\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobOrderResource extends Resource
{
    protected static ?string $model = JobOrder::class;

    protected static bool $canCreateAnother = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 20;

    public static function getGlobalSearchActions(Model $record): array
    {
        return [
            Action::make('view')
                ->label('View')
                ->url(static::getUrl('view', ['record' => $record])),
            Action::make('edit')
                ->label('Edit')
                ->url(static::getUrl('edit', ['record' => $record])),
        ];
    }

    public static function canCreate(): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public static function canEdit($record): bool
    {
        return PanelAccess::canManageJobOrders() && (string) $record->status === 'draft' && blank($record->proforma_task_id);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = JobOrder::query()->inProgress()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return JobOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobOrdersTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\ArtworksRelationManager::class,
            RelationManagers\TextFilesRelationManager::class,
        ];
        if (! in_array(Filament::getCurrentPanel()?->getId(), ['design', 'typist'], true)) {
            $relations[] = RelationManagers\MaterialRequestsRelationManager::class;
        }

        if (PanelAccess::canAccessFinanceSection()) {
            $relations[] = PaymentsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobOrders::route('/'),
            'create' => CreateJobOrder::route('/create'),
            'view' => Pages\ViewJobOrder::route('/{record}'),
            'edit' => EditJobOrder::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['job_order_number', 'name', 'partner.name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->job_order_number;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Customer' => $record->partner?->name,
            'Status' => ucfirst((string) $record->status),
            'Total' => Money::format($record->total),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['partner', 'designer', 'typist', 'proforma'])->withSum('productionMovements', 'quantity')->withSum(['payments' => fn ($query) => $query->whereNull('voided_at')], 'amount');
        $panel = Filament::getCurrentPanel()?->getId();
        if ($panel === 'design') {
            $query->where('designer_id', auth()->id())->whereNotIn('status', ['draft', 'cancelled']);
        } elseif ($panel === 'typist') {
            $query->where('typist_id', auth()->id())->where('status', '!=', 'cancelled');
        } elseif ($panel === 'production') {
            $query->where('status', 'production')->whereNull('production_completed_at');
        }

        return $query;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        if (! PanelAccess::canManageJobOrders()) {
            return static::getModel()::query()->whereRaw('1 = 0');
        }

        return parent::getGlobalSearchEloquentQuery()
            ->with(['partner']);
    }
}
