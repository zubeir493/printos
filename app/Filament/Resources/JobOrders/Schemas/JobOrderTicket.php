<?php

namespace App\Filament\Resources\JobOrders\Schemas;

use App\Filament\Support\PanelAccess;
use App\Models\JobOrder;
use App\Services\JobOrders\JobProductionCosting;
use App\Support\Money;
use App\Support\PrivateStorage;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobOrderTicket
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Overview')->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('name')->label('Job name'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state): string => ucfirst((string) $state)),
                TextEntry::make('production_mode')->label('Job source')->formatStateUsing(fn (JobOrder $record): string => $record->isInternal() ? 'Internal job' : 'Client job'),
                TextEntry::make('partner.name')->label('Customer')->visible(fn (JobOrder $record): bool => ! $record->isInternal())->placeholder('—'),
                TextEntry::make('job_type')->label('Product family')->formatStateUsing(fn ($state): string => ucfirst((string) $state)),
                TextEntry::make('quantity')->label('Run quantity')->numeric(),
                TextEntry::make('produced_quantity')->label('Good copies recorded')->numeric(),
                TextEntry::make('production_due_date')->date()->placeholder('Pending'),
                TextEntry::make('submission_date')->label('Job received')->date()->placeholder('—'),
                TextEntry::make('proof_due_date')->date()->placeholder('—'),
                TextEntry::make('proforma.proforma_number')->label('Approved quotation')->visible(fn (JobOrder $record): bool => filled($record->proforma_id)),
                TextEntry::make('outputInventoryItem.name')->label('Finished product')->visible(fn (JobOrder $record): bool => $record->isInternal())->placeholder('Pending'),
                TextEntry::make('productionWarehouse.name')->label('Finished-goods warehouse')
                    ->visible(fn (JobOrder $record): bool => $record->isInternal() && filled($record->stock_received_at)),
            ]),
            Section::make('Production readiness')->columnSpanFull()->schema([
                TextEntry::make('next_step')->hiddenLabel()->state(fn (JobOrder $record): string => self::nextStep($record)),
                TextEntry::make('release_issues')->label('Before release')->listWithLineBreaks()->bulleted()
                    ->visible(fn (JobOrder $record): bool => in_array((string) $record->status, ['draft', 'design'], true))
                    ->state(fn (JobOrder $record): array => $record->productionReleaseIssues())->placeholder('Ready for release'),
            ]),
            Section::make('Specifications')->id('job-order-specifications')->collapsible()->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                    TextEntry::make('size')->label('Finished size')->placeholder('Pending'),
                    TextEntry::make('services.edition')->label('Edition')->visible(fn (JobOrder $record): bool => $record->job_type === 'books')->placeholder('—'),
                    TextEntry::make('designer.name')->label('Designer')->placeholder('Unassigned'),
                    TextEntry::make('typist.name')->label('Typist')->placeholder('Unassigned'),
                    RepeatableEntry::make('specification_values')->hiddenLabel()->columnSpanFull()
                        ->state(fn (JobOrder $record): array => self::specifications($record))
                        ->visible(fn (JobOrder $record): bool => self::specifications($record) !== [])
                        ->schema([TextEntry::make('label')->hiddenLabel()->weight('semibold'), TextEntry::make('value')->hiddenLabel()])->columns(2),
                    TextEntry::make('instructions')->label('Production instructions')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('remarks')->columnSpanFull()->visible(fn (JobOrder $record): bool => filled($record->remarks)),
                ]),
            Section::make('Materials')->id('job-order-materials')->collapsible()->columnSpanFull()->schema([
                RepeatableEntry::make('materials')->hiddenLabel()->state(fn (JobOrder $record): array => $record->materials_summary)
                    ->placeholder('No material requirements added.')
                    ->columns(['default' => 2, 'md' => 4, 'xl' => 7])->schema([
                        TextEntry::make('material_name')->label('Material'),
                        TextEntry::make('unit'),
                        TextEntry::make('planned')->label('Required')->numeric(maxDecimalPlaces: 4),
                        TextEntry::make('reserved')->label('Reserve allowance')->numeric(maxDecimalPlaces: 4),
                        TextEntry::make('issued')->numeric(maxDecimalPlaces: 4),
                        TextEntry::make('available')->label('Available stock')->numeric(maxDecimalPlaces: 4),
                        TextEntry::make('shortage')->label('Short incl. reserve')->numeric(maxDecimalPlaces: 4),
                    ]),
            ]),
            Section::make('Artwork and proofs')->collapsible()->columnSpanFull()->schema([
                RepeatableEntry::make('deliverables')->label('Required files')->placeholder('No required files configured.')
                    ->schema([
                        TextEntry::make('label')->label('File'),
                        TextEntry::make('type')->formatStateUsing(fn ($state): string => str((string) $state)->headline()->toString()),
                    ])->columns(2),
            ]),
            Section::make('Production costing')->id('job-order-costing')->collapsible()->columnSpanFull()
                ->visible(fn (JobOrder $record): bool => PanelAccess::canSeeMoneyValues() && $record->uses_production_ticket)->schema([
                    TextEntry::make('costEstimate.estimate_number')->label('Cost estimate')->visible(fn (JobOrder $record): bool => filled($record->cost_estimate_id)),
                    TextEntry::make('cost_calc_file')->label('Cost calculation file')->formatStateUsing(fn (): string => 'Download document')
                        ->url(fn (JobOrder $record): ?string => filled($record->cost_calc_file) ? PrivateStorage::downloadUrl($record->cost_calc_file) : null)
                        ->visible(fn (JobOrder $record): bool => filled($record->cost_calc_file) && $record->cost_calc_file !== 'legacy-cost-calculation'),
                    TextEntry::make('cost_reviewed_at')->label('Cost reviewed')->dateTime()->placeholder('Pending'),
                    RepeatableEntry::make('worksheet')->hiddenLabel()->state(fn (JobOrder $record): array => self::worksheet($record))
                        ->schema([
                            TextEntry::make('category')->label('Production cost'),
                            TextEntry::make('estimated')->formatStateUsing(fn ($state): string => Money::format((float) $state))->placeholder('—'),
                            TextEntry::make('actual')->formatStateUsing(fn ($state): string => Money::format((float) $state))->placeholder('—'),
                        ])->columns(3),
                ]),
            Section::make('Client billing')->collapsible()->columnSpanFull()->columns(3)
                ->visible(fn (JobOrder $record): bool => ! $record->isInternal() && PanelAccess::canSeeMoneyValues())->schema([
                    TextEntry::make('subtotal')->formatStateUsing(fn ($state): string => Money::format((float) $state)),
                    TextEntry::make('tax_amount')->label('VAT')->formatStateUsing(fn ($state): string => Money::format((float) $state)),
                    TextEntry::make('total')->formatStateUsing(fn ($state): string => Money::format((float) $state)),
                    TextEntry::make('due_date')->label('Payment due date')->date()->placeholder('—'),
                    TextEntry::make('paid_amount')->formatStateUsing(fn ($state): string => Money::format((float) $state)),
                    TextEntry::make('balance')->formatStateUsing(fn ($state): string => Money::format((float) $state)),
                ]),
        ]);
    }

    private static function nextStep(JobOrder $job): string
    {
        return match ((string) $job->status) {
            'draft' => 'Start the job when you are ready to assign and prepare it.',
            'design' => $job->productionReleaseIssues() === [] ? 'Send this job to production.' : 'Resolve the outstanding items before sending this job to production.',
            'production' => $job->production_completed_at
                ? ($job->requiresCostedStockReceipt()
                    ? 'Have Finance review actual costs and receive the finished run into inventory.'
                    : 'Production is complete. Review settlement and dispatch.')
                : 'Issue the required materials and record good output.',
            'completed' => $job->isInternal() ? 'This run is complete. Finished copies are available in inventory.' : 'Production and payment are complete. Review any outstanding dispatch.',
            'cancelled' => 'This job has been cancelled.',
            default => '',
        };
    }

    /** @return list<array{label: string, value: string}> */
    private static function specifications(JobOrder $job): array
    {
        return collect($job->services ?? [])->except(['edition'])->filter(fn ($value): bool => ! in_array($value, [false, null, '', []], true))
            ->map(fn ($value, string $name): array => [
                'label' => match ($name) {
                    'page_no' => 'Page count',
                    'text_color_no' => 'Text colors',
                    'cover_color_no' => 'Cover colors',
                    default => str($name)->headline()->toString(),
                },
                'value' => match (true) {
                    $value === true => 'Yes',
                    is_array($value) => implode(', ', $value),
                    $name === 'binding_type' && $value === 'saddle' => 'Saddle stitch',
                    default => str((string) $value)->replace('_', ' ')->toString(),
                },
            ])->values()->all();
    }

    /** @return list<array{category: string, estimated: ?float, actual: ?float}> */
    private static function worksheet(JobOrder $job): array
    {
        $actual = $job->actual_production_costs ?? [];
        if (! $job->stock_received_at && in_array((string) $job->status, ['production', 'completed'], true)) {
            $actual['materials'] = app(JobProductionCosting::class)->issuedMaterialCost($job);
        }
        $rows = [];
        foreach (JobProductionCosting::CATEGORIES as $key => $label) {
            $rows[] = [
                'category' => $label, 'estimated' => $job->estimated_production_costs[$key] ?? null,
                'actual' => $actual[$key] ?? null,
            ];
        }
        $rows[] = [
            'category' => 'Total', 'estimated' => $job->estimated_production_costs === null ? null : array_sum($job->estimated_production_costs),
            'actual' => $actual === [] ? null : array_sum($actual),
        ];

        return $rows;
    }
}
