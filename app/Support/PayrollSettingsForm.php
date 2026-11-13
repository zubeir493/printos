<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class PayrollSettingsForm
{
    /** @return array<Section|Grid> */
    public static function fields(): array
    {
        return [
            Grid::make(4)->columnSpanFull()->schema([
                TextInput::make('monthly_day_divisor')->label('Days / month')->integer()->minValue(1)->maxValue(31)->default(30)->required()->columnSpan(1),
                TextInput::make('standard_daily_minutes')->label('Hours / day')->numeric()->minValue(0.25)->maxValue(24)->default(480)
                    ->formatStateUsing(fn ($state): float => (float) $state / 60)->dehydrateStateUsing(fn ($state): int => (int) round((float) $state * 60))->required()->columnSpan(1),
                Toggle::make('deduct_lateness')->label('Deduct lateness')->default(false)->columnSpan(1),
                Toggle::make('prorate_allowance')->label('Prorate transport allowance')->default(false)->columnSpan(1),
            ]),
            Grid::make(4)->columnSpanFull()->schema([
                ...collect(['day' => ['Day OT', 1.5], 'night' => ['Night OT', 1.75], 'rest' => ['Rest-day OT', 2], 'holiday' => ['Holiday OT', 2.5]])->map(fn (array $rate, string $key) => TextInput::make($key.'_overtime_multiplier')->label($rate[0])->numeric()->minValue(1)->suffix('×')->default($rate[1])->required()->columnSpan(1))->values()->all(),
            ]),
            Section::make('Advanced overtime')->compact()->collapsible()->collapsed()->columns(4)->columnSpanFull()->schema([
                TimePicker::make('night_start')->native()->seconds(false)->format('H:i:s')->default('22:00:00')->required()->columnSpan(2),
                TimePicker::make('night_end')->native()->seconds(false)->format('H:i:s')->default('06:00:00')->required()->columnSpan(2),
                TextInput::make('overtime_increment_minutes')->label('Payable minute increment')->integer()->minValue(1)->maxValue(60)->default(1)->required()->columnSpan(2),
                TextInput::make('maximum_daily_overtime_minutes')->label('Daily OT warning threshold')->suffix('min')->integer()->minValue(1)->default(240)->required()->columnSpan(1),
                TextInput::make('maximum_weekly_overtime_minutes')->label('Weekly OT warning threshold')->suffix('min')->integer()->minValue(1)->default(720)->required()->columnSpan(1),
            ]),
        ];
    }
}
