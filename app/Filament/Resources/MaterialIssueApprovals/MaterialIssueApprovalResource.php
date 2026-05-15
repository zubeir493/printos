<?php

namespace App\Filament\Resources\MaterialIssueApprovals;

use App\Filament\Resources\MaterialIssueApprovals\Pages\ManageMaterialIssueApprovals;
use App\Filament\Support\PanelAccess;
use App\Models\MaterialIssueApproval;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialIssueApprovalResource extends Resource
{
    protected static ?string $model = MaterialIssueApproval::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Material Approvals';

    protected static ?int $navigationSort = 130;

    public static function canViewAny(): bool
    {
        return PanelAccess::canApproveMaterialOverIssues();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return MaterialIssueApprovalsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'materialRequest.jobOrderTask.jobOrder',
            'materialRequest.inventoryItem',
            'requester',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaterialIssueApprovals::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }
}
