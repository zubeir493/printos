<?php

namespace App\Filament\Resources\PayrollOvertimeEntries;

use App\Filament\Resources\PayrollOvertimeEntries\Pages\ManagePayrollOvertimeEntries;
use App\Models\PayrollOvertimeEntry;
use App\Services\Hr\ReviewOvertime;
use App\UserRole;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayrollOvertimeEntryResource extends Resource
{
    protected static ?string $model = PayrollOvertimeEntry::class;

    protected static ?string $navigationLabel = 'Overtime review';

    protected static ?string $modelLabel = 'overtime entry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationParentItem = 'Payroll';

    public static function getNavigationParentItem(): ?string
    {
        return auth()->user()?->role === UserRole::HR ? 'Attendance' : 'Payroll';
    }

    protected static ?int $navigationSort = 313;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('pay_policy_id')->with(['employee', 'payPolicy', 'payrollRun']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')->date()->sortable(),
            TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'last_name'])->description(fn (PayrollOvertimeEntry $record): string => 'AC No. '.$record->employee->attendance_device_id),
            TextColumn::make('category_label')->label('Category'),
            TextColumn::make('minutes')->suffix(' min')->numeric(),
            TextColumn::make('hourly_rate')->label('Hourly base')->numeric(decimalPlaces: 2),
            TextColumn::make('multiplier')->numeric(decimalPlaces: 2)->suffix('×'),
            TextColumn::make('amount')->numeric(decimalPlaces: 2),
            TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                'approved' => 'success', 'rejected' => 'gray', default => 'warning'
            }),
            TextColumn::make('limit_warning')->label('Limits')->state(fn (PayrollOvertimeEntry $record): string => ($record->calculation_snapshot['daily_limit_exceeded'] ?? false) || ($record->calculation_snapshot['weekly_limit_exceeded'] ?? false) ? 'Review OT limit' : 'Within threshold')->badge(),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('pending'),
            SelectFilter::make('employee_id')->relationship('employee', 'first_name')->searchable(),
        ])->recordActions([
            ...collect(['approve' => true, 'reject' => false])->map(fn (bool $approve, string $name) => Action::make($name)->authorize('update')->visible(fn (PayrollOvertimeEntry $record): bool => $record->status !== ($approve ? 'approved' : 'rejected'))
                ->schema([Textarea::make('reason')->required()])->action(fn (PayrollOvertimeEntry $record, array $data) => app(ReviewOvertime::class)->handle($record, $approve, $data['reason'])))->values()->all(),
        ])->toolbarActions([
            BulkAction::make('approveSelected')->label('Approve selected')->schema([Textarea::make('reason')->required()])
                ->action(function (Collection $records, array $data): void {
                    foreach ($records as $record) {
                        app(ReviewOvertime::class)->handle($record, true, $data['reason']);
                    }
                })->deselectRecordsAfterCompletion(),
        ])->defaultSort('date', 'desc')->emptyStateHeading('No overtime awaiting review')->emptyStateDescription(null);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayrollOvertimeEntries::route('/')];
    }
}
