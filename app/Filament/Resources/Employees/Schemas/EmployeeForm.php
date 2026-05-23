<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                                        $lastEmployee = Employee::orderBy('id', 'desc')->first();
                                        $lastNumber = 0;
                                        if ($lastEmployee && preg_match('/EMP-(\d+)/', $lastEmployee->employee_id, $matches)) {
                                            $lastNumber = (int) $matches[1];
                                        }

                                        return 'EMP-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                                    })
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('attendance_device_id')
                                    ->label('Punch Machine AC No')
                                    ->unique(ignoreRecord: true),
                                TextInput::make('first_name')
                                    ->required(),

                                TextInput::make('last_name')
                                    ->required(),
                                TextInput::make('phone')
                                    ->tel(),
                                TextInput::make('department'),
                                TextInput::make('position'),
                                Select::make('employment_type')
                                    ->label('Employment Type')
                                    ->options([
                                        'permanent' => 'Permanent',
                                        'contract' => 'Contract',
                                        'temporary' => 'Temporary',
                                        'part_time' => 'Part-time',
                                    ])
                                    ->default('permanent')
                                    ->required(),
                                TextInput::make('tax_id')
                                    ->label('Tax ID'),
                                Select::make('pension_enabled')
                                    ->label('Pension')
                                    ->options([
                                        true => 'Enabled',
                                        false => 'Disabled',
                                    ])
                                    ->default(true)
                                    ->required(),
                                TextInput::make('basic_salary')
                                    ->label('Monthly Rate')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(0),
                                TextInput::make('transport_allowance')
                                    ->label('Transportation Allowance')
                                    ->numeric()
                                    ->suffix('Birr')
                                    ->default(0),
                                TextInput::make('overtime_multiplier')
                                    ->label('Overtime Multiplier')
                                    ->numeric()
                                    ->default(1),
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
                                            return new HtmlString('<img src="https://ui-avatars.com/api/?name='.urlencode($name).'&color=FFFFFF&background=020617" class="w-full aspect-square rounded-xl object-cover shadow-sm" />');
                                        }
                                        $url = PrivateStorage::url($record->image, now()->addMinutes(10));

                                        return new HtmlString('<img src="'.$url.'" class="w-full aspect-square rounded-xl object-cover shadow-sm" />');
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
                                    ->visibility('private')
                                    ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                                    ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                                    ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                                    ->directory('employees/photos')
                                    ->label('Photo')
                                    ->previewable(false)
                                    ->hiddenOn('view')
                                    ->columnSpan(5),
                                DatePicker::make('hire_date')
                                    ->required()
                                    ->default(now())
                                    ->columnSpanFull(),
                                DatePicker::make('termination_date')
                                    ->visible(fn (Get $get): bool => in_array($get('status'), ['inactive', 'terminated'], true))
                                    ->columnSpanFull(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'terminated' => 'Terminated',
                                    ])
                                    ->default('active')
                                    ->live()
                                    ->required()
                                    ->columnSpanFull(),
                            ])->columnSpan(1)->columns(5),
                    ])->columnSpanFull()->columns(4),
            ]);
    }
}
