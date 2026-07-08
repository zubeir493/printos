<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\LeaveRequest;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationParentItem = 'Payroll';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')->relationship('employee', 'first_name')->searchable()->required(),
            Select::make('leave_type_id')->relationship('leaveType', 'name')->required(),
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date')->required(),
            TimePicker::make('start_time')->seconds(false),
            TimePicker::make('end_time')->seconds(false),
            TextInput::make('minutes')->numeric(),
            Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->default('pending')
                ->required(),
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
