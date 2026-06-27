<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

class BookCostingWizardSchema
{
    public static function steps(): array
    {
        return [
            Step::make('Book Specifications')
                ->visible(fn (Get $get): bool => $get('job_type') === 'books')
                ->schema([
                    Section::make('Book Format')
                        ->columns(3)
                        ->schema([
                            TextInput::make('services.book.page_count')
                                ->label('No of Pages')
                                ->numeric()
                                ->minValue(1)
                                ->default(64)
                                ->live(onBlur: true)
                                ->required(),
                            Select::make('services.book.size')
                                ->options(['A4' => 'A4', 'A5' => 'A5', 'A6' => 'A6', 'B5' => 'B5', 'B6' => 'B6', 'B7' => 'B7'])
                                ->default('A5')
                                ->live()
                                ->required(),
                            Select::make('services.book.binding')
                                ->options(['Saddle' => 'Saddle', 'Perfect' => 'Perfect', 'Hard cover' => 'Hard cover'])
                                ->default('Perfect')
                                ->live()
                                ->required(),
                        ]),
                    Section::make('Print Setup')
                        ->columns(3)
                        ->schema([
                            Select::make('services.book.inside_printing')
                                ->label('Inside Printing')
                                ->options(['Yes' => 'Yes', 'No' => 'No'])
                                ->default('Yes')
                                ->live(),
                            TextInput::make('services.book.text_colors')->label('Text Color')->numeric()->default(1)->minValue(1)->live(onBlur: true),
                            TextInput::make('services.book.cover_colors')->label('Cover Color')->numeric()->default(4)->minValue(0)->live(onBlur: true),
                            TextInput::make('services.book.text_ink_coverage')->label('Text Ink Coverage')->numeric()->suffix('%')->default(1)->live(onBlur: true),
                            TextInput::make('services.book.cover_ink_coverage')->label('Cover Ink Coverage')->numeric()->suffix('%')->default(1)->live(onBlur: true),
                            Select::make('services.book.cover_paper_format')
                                ->label('Cover Paper Format')
                                ->options(['A1' => 'A1', 'A2' => 'A2'])
                                ->default('A1')
                                ->live()
                                ->visible(fn (Get $get): bool => $get('services.book.binding') !== 'Hard cover'),
                            Select::make('services.book.cover_laminated')
                                ->label('Laminated')
                                ->options(['Yes' => 'Yes', 'No' => 'No'])
                                ->default('Yes')
                                ->live(),
                        ]),
                    Section::make('Allowances')
                        ->columns(2)
                        ->schema([
                            TextInput::make('services.book.text_allowance_per_signature')->label('Allowance / Signature')->numeric()->default(50)->live(onBlur: true),
                            TextInput::make('services.book.cover_allowance')->label('Cover Allowance')->numeric()->default(20)->live(onBlur: true),
                        ]),
                ]),
            Step::make('Book Materials')
                ->visible(fn (Get $get): bool => $get('job_type') === 'books')
                ->schema([
                    Section::make('Paper Costs')
                        ->columns(3)
                        ->schema([
                            TextInput::make('services.material.text_paper_unit_cost')->label('Text Paper / Ream')->numeric()->default(7000 / 1.15)->live(onBlur: true),
                            TextInput::make('services.material.cover_paper_unit_cost')->label('Cover Paper / Pack')->numeric()->default(0)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') !== 'Hard cover'),
                            TextInput::make('services.material.case_paper_unit_cost')->label('Case Paper / Sheet')->numeric()->default(0)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Hard cover'),
                            TextInput::make('services.material.grey_board_unit_cost')->label('Grey Board / Sheet')->numeric()->default(0)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Hard cover'),
                            TextInput::make('services.material.endsheet_unit_cost')->label('Endsheet / Sheet')->numeric()->default(0)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Hard cover'),
                        ]),
                    Section::make('Consumable Costs')
                        ->columns(3)
                        ->schema([
                            TextInput::make('services.material.plate_unit_cost')->label('Plate / Plate')->numeric()->default(900)->live(onBlur: true),
                            TextInput::make('services.material.ink_unit_cost')->label('Ink / Kg')->numeric()->default(3000)->live(onBlur: true),
                            TextInput::make('services.material.lamination_unit_cost')->label('Lamination / m2')->numeric()->default(0)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.cover_laminated') !== 'No'),
                            TextInput::make('services.material.wire_unit_cost')->label('Wire / Kg')->numeric()->default(160)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Saddle'),
                            TextInput::make('services.material.hotmelt_glue_unit_cost')->label('Hotmelt Glue')->numeric()->default(10000)->live(onBlur: true),
                            TextInput::make('services.material.white_glue_unit_cost')->label('White Glue')->numeric()->default(0)->live(onBlur: true),
                            TextInput::make('services.material.packing_unit_cost')->label('Packing Material')->numeric()->default(30)->live(onBlur: true),
                        ]),
                ]),
            Step::make('Book Production')
                ->visible(fn (Get $get): bool => $get('job_type') === 'books')
                ->schema([
                    Section::make('Operation Speeds')
                        ->columns(3)
                        ->schema([
                            TextInput::make('services.production.printing_speed')->label('Printing Speed')->numeric()->default(2500)->live(onBlur: true),
                            TextInput::make('services.production.folding_speed')->label('Folding Speed')->numeric()->default(2500)->live(onBlur: true),
                            TextInput::make('services.production.collating_speed')->label('Collating Speed')->numeric()->default(2500)->live(onBlur: true),
                            TextInput::make('services.production.laminating_speed')->label('Laminating Speed')->numeric()->default(300)->live(onBlur: true),
                            TextInput::make('services.production.perfect_binding_speed')->label('Perfect Binding Speed')->numeric()->default(700)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Perfect'),
                            TextInput::make('services.production.gluing_speed')->label('Gluing Speed')->numeric()->default(25)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Hard cover'),
                        ]),
                    Section::make('Operation Rates')
                        ->columns(3)
                        ->schema([
                            TextInput::make('services.production.printing_rate')->label('Printing / Hour')->numeric()->default(200)->live(onBlur: true),
                            TextInput::make('services.production.folding_rate')->label('Folding / Hour')->numeric()->default(175)->live(onBlur: true),
                            TextInput::make('services.production.collating_rate')->label('Collating / Hour')->numeric()->default(0.05)->live(onBlur: true),
                            TextInput::make('services.production.cutting_rate')->label('Cutting / Hour')->numeric()->default(100)->live(onBlur: true),
                            TextInput::make('services.production.laminating_rate')->label('Laminating / Hour')->numeric()->default(80)->live(onBlur: true),
                            TextInput::make('services.production.perfect_binding_rate')->label('Perfect Binding / Hour')->numeric()->default(140)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Perfect'),
                            TextInput::make('services.production.gluing_rate')->label('Gluing / Hour')->numeric()->default(60)->live(onBlur: true)->visible(fn (Get $get): bool => $get('services.book.binding') === 'Hard cover'),
                            TextInput::make('services.production.packing_rate')->label('Packing / Hour')->numeric()->default(30)->live(onBlur: true),
                            TextInput::make('services.production.packing_multiplier')->label('Packing Multiplier')->numeric()->default(12)->live(onBlur: true),
                            TextInput::make('services.production.typesetting_rate')->label('Typesetting / Page')->numeric()->default(20)->live(onBlur: true),
                            TextInput::make('services.production.artwork_hours')->label('Artwork Hours')->numeric()->default(3)->live(onBlur: true),
                            TextInput::make('services.production.artwork_rate')->label('Artwork / Hour')->numeric()->default(600)->live(onBlur: true),
                        ]),
                ]),
            self::commercialStep(),
        ];
    }

    private static function commercialStep(): Step
    {
        return Step::make('Commercial Review')
            ->visible(fn (Get $get): bool => $get('job_type') === 'books')
            ->columns(3)
            ->schema([
                TextInput::make('services.commercial.overhead_percent')->numeric()->suffix('%')->live(onBlur: true),
                TextInput::make('services.commercial.profit_margin_percent')->numeric()->suffix('%')->live(onBlur: true),
                TextInput::make('services.commercial.discount_percent')->numeric()->suffix('%')->live(onBlur: true),
            ]);
    }
}
