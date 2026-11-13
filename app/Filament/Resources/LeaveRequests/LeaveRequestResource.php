<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\ReviewLeaveRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationParentItem = 'Attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')->relationship('employee', 'first_name')->searchable()->required(),
            Select::make('leave_type_id')->label('Leave type')->relationship('leaveType', 'name')->searchable()->preload()->required()
                ->createOptionForm([
                    TextInput::make('name')->required()->maxLength(255)->unique(LeaveType::class, 'name'),
                    TextInput::make('annual_allowance_days')->label('Annual limit')->numeric()->minValue(0)->maxValue(366)->suffix('days')->placeholder('No limit'),
                    Toggle::make('is_paid')->label('Paid leave')->default(true),
                ])
                ->createOptionAction(fn (Action $action): Action => $action->modalHeading('New leave type')->modalWidth('md')->authorize(fn (): bool => Gate::allows('create', LeaveType::class)))
                ->createOptionUsing(function (array $data): int {
                    Gate::authorize('create', LeaveType::class);

                    return LeaveType::create(collect($data)->only(['name', 'annual_allowance_days', 'is_paid'])->all())->getKey();
                }),
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
            TimePicker::make('start_time')->seconds(false),
            TimePicker::make('end_time')->seconds(false),
            TextInput::make('minutes')->numeric()->integer()->minValue(1),
            Hidden::make('status')->default('pending'),
            Textarea::make('reason')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['first_name', 'last_name']),
                TextColumn::make('leaveType.name')->label('Type'),
                TextColumn::make('start_date')->date(),
                TextColumn::make('end_date')->date(),
                TextColumn::make('status')->badge(),
                TextColumn::make('remaining_leave')->label('Remaining days')->state(fn (LeaveRequest $record): string => ($balance = app(ReviewLeaveRequest::class)->balance($record->employee_id, $record->leaveType, $record->start_date->year)) === null ? 'No annual limit configured' : number_format($balance / 480, 2)),
                TextColumn::make('decision_reason')->limit(60),
                TextColumn::make('approved_at')->dateTime()->label('Decided at'),
            ])
            ->recordActions([
                Action::make('approve')->visible(fn (LeaveRequest $record): bool => $record->status === 'pending')->schema([Textarea::make('decision_reason')->required()])->action(fn (LeaveRequest $record, array $data) => app(ReviewLeaveRequest::class)->handle($record, 'approved', $data['decision_reason'])),
                Action::make('reject')->color('danger')->visible(fn (LeaveRequest $record): bool => $record->status === 'pending')->schema([Textarea::make('decision_reason')->required()])->action(fn (LeaveRequest $record, array $data) => app(ReviewLeaveRequest::class)->handle($record, 'rejected', $data['decision_reason'])),
            ])
            ->filters([
                DateRangeFilter::make('leave_date_range', 'start_date', 'Leave dates', 'end_date'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveRequests::route('/'),
        ];
    }
}
