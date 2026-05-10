<?php

namespace App\Filament\Resources\JobOrderTasks;

use App\Filament\Resources\JobOrderTasks\Pages\CreateJobOrderTask;
use App\Filament\Resources\JobOrderTasks\Pages\EditJobOrderTask;
use App\Filament\Resources\JobOrderTasks\Pages\ListJobOrderTasks;
use App\Filament\Resources\JobOrderTasks\Pages\ViewJobOrderTask;
use App\Filament\Resources\JobOrderTasks\Schemas\JobOrderTaskForm;
use App\Filament\Resources\JobOrderTasks\Tables\JobOrderTasksTable;
use App\Filament\Support\PanelAccess;
use App\Models\JobOrderTask;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobOrderTaskResource extends Resource
{
    protected static ?string $model = JobOrderTask::class;

    protected static ?string $navigationLabel = 'Tasks';

    protected static ?string $navigationParentItem = 'Job Orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return PanelAccess::canManageJobOrderTasks();
    }

    public static function canEdit($record): bool
    {
        return PanelAccess::canManageJobOrderTasks();
    }

    public static function form(Schema $schema): Schema
    {
        return JobOrderTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobOrderTasksTable::configure($table)
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['jobOrder.partner']);

        if (Filament::getCurrentPanel()?->getId() === 'production') {
            $query->where('status', 'production');
        }

        return $query;
    }

    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\ArtworksRelationManager::class,
        ];

        // Only show material requests to non-design panels
        if (Filament::getCurrentPanel()?->getId() !== 'design') {
            $relations[] = RelationManagers\MaterialRequestsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobOrderTasks::route('/'),
            'create' => CreateJobOrderTask::route('/create'),
            'view' => ViewJobOrderTask::route('/{record}'),
            'edit' => EditJobOrderTask::route('/{record}/edit'),
        ];
    }
}
