<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('employee_id')
                                    ->label('Employee ID')
                                    ->default(function () {
                                        $lastEmployee = \App\Models\Employee::orderBy('id', 'desc')->first();
                                        $lastNumber = 0;
                                        if ($lastEmployee && preg_match('/EMP-(\d+)/', $lastEmployee->employee_id, $matches)) {
                                            $lastNumber = (int) $matches[1];
                                        }
                                        return 'EMP-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                                    })
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('first_name')
                                    ->required(),

                                TextInput::make('last_name')
                                    ->required(),
                                TextInput::make('phone')
                                    ->tel(),
                                TextInput::make('department'),
                                TextInput::make('position'),
                                TextInput::make('basic_salary')
                                    ->label('Basic Salary (Monthly)')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(0),
                                TextInput::make('hourly_overtime_rate')
                                    ->label('Hourly Overtime Rate')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(0),
                                TextInput::make('holiday_overtime_rate')
                                    ->label('Holiday Overtime Rate')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(0),
                                Select::make('payment_method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'bank_transfer' => 'Bank Transfer',
                                        'check' => 'Check',
                                    ]),
                                TextInput::make('bank_name'),
                                TextInput::make('account_number'),
                            ])->columnSpan(3)->columns(2),

                        Section::make()
                            ->schema([
                                Placeholder::make('image_view')
                                    ->label('Photo')
                                    ->visibleOn('view')
                                    ->content(function ($record) {
                                        $name = $record?->full_name ?? 'Employee';

                                        if (! $record || ! $record->image) {
                                            return new \Illuminate\Support\HtmlString('<img src="https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=FFFFFF&background=020617" class="w-full aspect-square rounded-xl object-cover shadow-sm" />');
                                        }
                                        $url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($record->image, now()->addMinutes(10));
                                        return new \Illuminate\Support\HtmlString('<img src="' . $url . '" class="w-full aspect-square rounded-xl object-cover shadow-sm" />');
                                    })
                                    ->columnSpan(5),
                                FileUpload::make('image')
                                    ->image()
                                    ->imageEditor()
                                    ->panelAspectRatio('1:1')
                                    ->imageAspectRatio('1:1')
                                    ->maxSize(512)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->disk('s3')
                                    ->directory('employees/photos')
                                    ->label('Photo')
                                    ->previewable(false)
                                    ->hiddenOn('view')
                                    ->columnSpan(5),
                                DatePicker::make('hire_date')
                                    ->required()
                                    ->default(now())
                                    ->columnSpanFull(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'terminated' => 'Terminated',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->columnSpanFull(),
                            ])->columnSpan(1)->columns(5),
                    ])->columnSpanFull()->columns(4),
            ]);
    }
}
