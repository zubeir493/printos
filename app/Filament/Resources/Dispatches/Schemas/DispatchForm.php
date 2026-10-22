<?php

namespace App\Filament\Resources\Dispatches\Schemas;

use App\Models\JobOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('job_order_id')->label('Job Order')
                ->default(fn (): ?int => JobOrder::query()->whereKey(request()->integer('job_order_id'))->where('production_mode', 'make_to_order')->whereIn('status', ['production', 'completed'])->value('id'))
                ->relationship('jobOrder', 'job_order_number', modifyQueryUsing: fn ($query) => $query
                    ->where('production_mode', 'make_to_order')->whereIn('status', ['production', 'completed'])->with('partner'))
                ->getOptionLabelFromRecordUsing(fn (JobOrder $record): string => $record->job_order_number.' — '.$record->name)
                ->searchable()->preload()->required(),
            DatePicker::make('delivery_date')->default(today())->required(),
            Select::make('warehouse_id')->relationship('warehouse', 'name')->required()->searchable()->preload(),
            TextInput::make('quantity')->label('Delivered Quantity')->integer()->minValue(0)->required()->default(0),
            Textarea::make('remarks')->columnSpanFull(),
        ])->columns(2);
    }
}
