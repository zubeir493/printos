<?php

namespace App\Filament\Resources\OvertimeRules;

use App\Filament\Resources\OvertimeRules\Pages\ManageOvertimeRules;
use App\Models\OvertimeRule;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OvertimeRuleResource extends Resource
{
    protected static ?string $model = OvertimeRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Overtime Rules';

    protected static ?string $navigationParentItem = 'Payroll';

    protected static ?int $navigationSort = 321;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('code'),
                        TextInput::make('name')
                            ->label('Rule name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, ?OvertimeRule $record): void {
                                if ($record || blank($state)) {
                                    return;
                                }

                                $set('code', Str::slug($state, '_'));
                            }),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                CheckboxList::make('applies_on_days')
                    ->label('When should this rule apply?')
                    ->options(OvertimeRule::dayOptions())
                    ->default([OvertimeRule::DAY_REGULAR])
                    ->columns(3)
                    ->helperText('Choose the day types that can generate this overtime candidate.')
                    ->required()
                    ->columnSpanFull(),
                Select::make('minutes_basis')
                    ->label('How should payable minutes be counted?')
                    ->options(OvertimeRule::minutesBasisOptions())
                    ->helperText('This decides where the payable minutes come from before the rule rate is applied.')
                    ->required()
                    ->live(),
                Placeholder::make('minutes_basis_explanation')
                    ->label('How this option works')
                    ->content(fn (Get $get): string => static::minutesBasisExplanation((string) $get('minutes_basis')))
                    ->columnSpanFull(),
                TextInput::make('multiplier')
                    ->label('Pay multiplier')
                    ->numeric()
                    ->default(1)
                    ->helperText('Example: 1.5 pays one and a half times the hourly rate.')
                    ->required(),
                TextInput::make('hourly_rate')
                    ->label('Fixed hourly rate override')
                    ->numeric()
                    ->helperText('Leave empty to use the employee payroll hourly rate.'),
                TimePicker::make('window_start_time')
                    ->label(fn (Get $get): string => $get('minutes_basis') === OvertimeRule::BASIS_AFTER_CLOCK_TIME ? 'Starts after' : 'Window starts')
                    ->seconds(false)
                    ->helperText(fn (Get $get): ?string => $get('minutes_basis') === OvertimeRule::BASIS_TIME_WINDOW ? 'Start of the payable clock window.' : null)
                    ->required(fn (Get $get): bool => in_array($get('minutes_basis'), [
                        OvertimeRule::BASIS_AFTER_CLOCK_TIME,
                        OvertimeRule::BASIS_TIME_WINDOW,
                    ], true))
                    ->visible(fn (Get $get): bool => in_array($get('minutes_basis'), [
                        OvertimeRule::BASIS_AFTER_CLOCK_TIME,
                        OvertimeRule::BASIS_TIME_WINDOW,
                    ], true)),
                TimePicker::make('window_end_time')
                    ->label(fn (Get $get): string => $get('minutes_basis') === OvertimeRule::BASIS_BEFORE_CLOCK_TIME ? 'Ends before' : 'Window ends')
                    ->seconds(false)
                    ->helperText(fn (Get $get): ?string => $get('minutes_basis') === OvertimeRule::BASIS_TIME_WINDOW ? 'End of the payable clock window. Overnight windows like 22:00 to 06:00 are supported.' : null)
                    ->required(fn (Get $get): bool => in_array($get('minutes_basis'), [
                        OvertimeRule::BASIS_BEFORE_CLOCK_TIME,
                        OvertimeRule::BASIS_TIME_WINDOW,
                    ], true))
                    ->visible(fn (Get $get): bool => in_array($get('minutes_basis'), [
                        OvertimeRule::BASIS_BEFORE_CLOCK_TIME,
                        OvertimeRule::BASIS_TIME_WINDOW,
                    ], true)),
                TextInput::make('minimum_minutes')
                    ->label('Ignore if under')
                    ->suffix('min')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('rounding_increment_minutes')
                    ->label('Round down to')
                    ->suffix('min')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('priority')
                    ->label('Conflict order')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(100)
                    ->helperText('Lower numbers are evaluated first when more than one rule could match.')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rule')
                    ->searchable()
                    ->description(fn (OvertimeRule $record): string => static::ruleDescription($record))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('minutes_basis')
                    ->label('Count from')
                    ->badge()
                    ->color(fn (string $state): string => static::basisColor($state))
                    ->formatStateUsing(fn (string $state): string => OvertimeRule::minutesBasisOptions()[$state] ?? str($state)->headline()->value()),
                TextColumn::make('rate_summary')
                    ->label('Rate')
                    ->state(fn (OvertimeRule $record): string => static::rateSummary($record)),
                TextColumn::make('window_summary')
                    ->label('Time window')
                    ->state(fn (OvertimeRule $record): string => static::windowSummary($record))
                    ->placeholder('Shift / attendance'),
            ])
            ->defaultSort('priority')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->mutateDataUsing(function (array $data): array {
                            return static::normalizeFormData($data);
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        $data['code'] = Str::slug((string) ($data['code'] ?? $data['name'] ?? ''), '_');

        if (($data['minutes_basis'] ?? null) === OvertimeRule::BASIS_TIME_WINDOW) {
            $data['window_start_time'] = static::normalizeTime($data['window_start_time'] ?? null);
            $data['window_end_time'] = static::normalizeTime($data['window_end_time'] ?? null);
        } elseif (($data['minutes_basis'] ?? null) === OvertimeRule::BASIS_AFTER_CLOCK_TIME) {
            $data['window_start_time'] = static::normalizeTime($data['window_start_time'] ?? null);
            $data['window_end_time'] = null;
        } elseif (($data['minutes_basis'] ?? null) === OvertimeRule::BASIS_BEFORE_CLOCK_TIME) {
            $data['window_start_time'] = null;
            $data['window_end_time'] = static::normalizeTime($data['window_end_time'] ?? null);
        } else {
            $data['window_start_time'] = null;
            $data['window_end_time'] = null;
        }

        return $data;
    }

    private static function normalizeTime(mixed $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        return CarbonImmutable::parse('2000-01-01 '.(string) $time)->format('H:i:s');
    }

    public static function minutesBasisExplanation(string $basis): string
    {
        return match ($basis) {
            OvertimeRule::BASIS_ATTENDANCE_OVERTIME => 'Uses overtime minutes already detected from attendance imports or summaries. Use this for normal OT reported by the attendance device.',
            OvertimeRule::BASIS_BEFORE_SHIFT => 'Counts worked minutes before the scheduled shift start. Use this when early arrival is approved as overtime.',
            OvertimeRule::BASIS_AFTER_SHIFT => 'Counts worked minutes after the scheduled shift end. Use this for approved extra time after a normal shift.',
            OvertimeRule::BASIS_AFTER_CLOCK_TIME => 'Counts worked minutes after one clock time, regardless of shift end. Use this for rules like overtime after 17:30.',
            OvertimeRule::BASIS_BEFORE_CLOCK_TIME => 'Counts worked minutes before one clock time. Use this for early morning rules such as work before 06:00.',
            OvertimeRule::BASIS_TIME_WINDOW => 'Counts only worked minutes inside the selected time range. Use this for night windows like 22:00 - 06:00.',
            OvertimeRule::BASIS_WORKED_DAY => 'Counts all worked minutes on matching holidays, weekends, or day-off days.',
            OvertimeRule::BASIS_MANUAL => 'Never generated from attendance. Use this when overtime should only be entered during payroll review.',
            default => 'Choose how attendance should propose payable overtime minutes for this rule.',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOvertimeRules::route('/'),
        ];
    }

    private static function ruleDescription(OvertimeRule $record): string
    {
        return ($record->is_active ? 'Active' : 'Inactive').' · '.static::daySummary($record->applies_on_days);
    }

    private static function daySummary(array|string|null $state): string
    {
        $days = is_array($state) ? $state : [];

        return collect($days)
            ->map(fn (string $day): string => OvertimeRule::dayOptions()[$day] ?? str($day)->headline()->value())
            ->join(', ');
    }

    private static function rateSummary(OvertimeRule $record): string
    {
        $base = $record->hourly_rate === null ? 'payroll rate' : number_format((float) $record->hourly_rate, 2);

        return number_format((float) $record->multiplier, 2).'x '.$base;
    }

    private static function windowSummary(OvertimeRule $record): string
    {
        return match ($record->minutes_basis) {
            OvertimeRule::BASIS_AFTER_CLOCK_TIME => $record->window_start_time ? 'After '.$record->window_start_time : 'Not set',
            OvertimeRule::BASIS_BEFORE_CLOCK_TIME => $record->window_end_time ? 'Before '.$record->window_end_time : 'Not set',
            OvertimeRule::BASIS_TIME_WINDOW => $record->window_start_time && $record->window_end_time
                ? $record->window_start_time.' - '.$record->window_end_time
                : 'Not set',
            default => 'Shift / attendance',
        };
    }

    private static function roundingSummary(OvertimeRule $record): string
    {
        return 'Min '.(int) $record->minimum_minutes.' min · round '.(int) $record->rounding_increment_minutes.' min';
    }

    private static function basisColor(string $state): string
    {
        return match ($state) {
            OvertimeRule::BASIS_WORKED_DAY => 'warning',
            OvertimeRule::BASIS_TIME_WINDOW,
            OvertimeRule::BASIS_AFTER_CLOCK_TIME,
            OvertimeRule::BASIS_BEFORE_CLOCK_TIME => 'info',
            OvertimeRule::BASIS_MANUAL => 'gray',
            default => 'primary',
        };
    }
}
