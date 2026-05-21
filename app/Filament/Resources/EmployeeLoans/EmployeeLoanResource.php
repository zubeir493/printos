<?php

namespace App\Filament\Resources\EmployeeLoans;

use App\Filament\Resources\EmployeeLoans\Pages\ManageEmployeeLoans;
use App\Models\EmployeeLoan;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EmployeeLoanResource extends Resource
{
    protected static ?string $model = EmployeeLoan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 315;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->label('Employee')
                ->relationship('employee', 'first_name')
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('loan_date')
                ->label('Loan Date')
                ->default(now())
                ->required(),
            DatePicker::make('return_date')
                ->label('Deduct In Payroll Month')
                ->default(now())
                ->required(),
            TextInput::make('amount')
                ->numeric()
                ->suffix('Birr')
                ->required(),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'deducted' => 'Deducted',
                    'cancelled' => 'Cancelled',
                ])
                ->default('active')
                ->required(),
            Textarea::make('reason')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee'),
                TextColumn::make('loan_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('return_date')
                    ->label('Payroll Month')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('ETB')
                    ->summarize(Sum::make()->money('ETB')),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('reason')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'deducted' => 'Deducted',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('return_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeLoans::route('/'),
        ];
    }
}
