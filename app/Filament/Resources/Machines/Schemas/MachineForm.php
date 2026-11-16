<?php

namespace App\Filament\Resources\Machines\Schemas;

use App\Models\Machine;
use App\Support\Money;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MachineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->maxLength(255),
                Select::make('operation_type')
                    ->options(Machine::OPERATION_TYPES)
                    ->searchable()
                    ->live()
                    ->required(),
                TextInput::make('baseline_rounds_per_week')
                    ->label('Baseline Rounds/Week')
                    ->numeric()
                    ->default(0)
                    ->helperText('Expected number of rounds per week for this machine'),
                Toggle::make('is_active')->label('Available for estimating')->default(true),
                Section::make('Costing configuration')
                    ->description('The hourly rate includes equipment, power, crew and shop overhead. Use effective production speed rather than rated maximum speed.')
                    ->schema([
                        TextInput::make('hourly_cost')->label('All-inclusive hourly cost')->numeric()->minValue(0)->default(0)->suffix(fn (): string => Money::suffix()),
                        TextInput::make('production_speed')->label('Effective running speed')->numeric()->minValue(0)->default(0),
                        Select::make('speed_unit')->options(['sheets_per_hour' => 'Sheets / hour', 'products_per_hour' => 'Finished pieces / hour', 'm2_per_hour' => 'm² / hour'])->live(),
                        TextInput::make('setup_time_minutes')->label('Setup time per form / operation')->numeric()->minValue(0)->default(0)->suffix('min'),
                        TextInput::make('setup_waste')->label('Setup waste per pass / operation')->numeric()->minValue(0)->default(0)->helperText('Sheets for sheet operations; pieces for assembly operations.'),
                        TextInput::make('run_waste_percent')->label('Running reject rate')->numeric()->minValue(0)->maxValue(99.99)->default(0)->suffix('%'),
                        TextInput::make('minimum_run_minutes')->label('Minimum chargeable running time')->numeric()->minValue(0)->default(0)->suffix('min'),
                        Toggle::make('costing_configured')->label('Rates, speed, setup and waste have been verified')->default(false)->columnSpanFull(),
                    ])->columns(3)->columnSpanFull(),
                Section::make('Sheet and material limits')->schema([
                    ...collect(['min_sheet_width_mm' => 'Minimum sheet width', 'min_sheet_height_mm' => 'Minimum sheet height', 'max_sheet_width_mm' => 'Maximum sheet width', 'max_sheet_height_mm' => 'Maximum sheet height', 'max_print_width_mm' => 'Maximum image width', 'max_print_height_mm' => 'Maximum image height', 'gripper_margin_mm' => 'Gripper margin', 'side_margin_mm' => 'Margin on each side', 'trailing_margin_mm' => 'Trailing margin'])->map(fn (string $label, string $key): TextInput => TextInput::make($key)->label($label)->numeric()->minValue(0)->default(str_contains($key, 'margin') ? 0 : null)->suffix('mm'))->values()->all(),
                    TextInput::make('min_gsm')->numeric()->minValue(0)->suffix('g/m²'),
                    TextInput::make('max_gsm')->numeric()->minValue(0)->suffix('g/m²'),
                    TextInput::make('min_thickness_mm')->label('Minimum caliper')->numeric()->minValue(0)->suffix('mm'),
                    TextInput::make('max_thickness_mm')->label('Maximum caliper')->numeric()->minValue(0)->suffix('mm'),
                ])->columns(3)->columnSpanFull()->collapsible(),
                Section::make('Offset press configuration')->schema([
                    TextInput::make('color_units')->label('Printing units')->integer()->minValue(1)->maxValue(12),
                    TextInput::make('perfecting_colors_per_side')->label('Perfecting colors per side')->integer()->minValue(0)->maxValue(6)->default(0)->helperText('Zero means sheetwise printing. A 4/4 perfector uses eight printing units.'),
                ])->visible(fn (Get $get): bool => $get('operation_type') === 'printing')->columns(2)->columnSpanFull(),
            ]);
    }
}
