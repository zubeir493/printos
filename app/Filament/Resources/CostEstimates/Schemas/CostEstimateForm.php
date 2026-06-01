<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Filament\Support\PanelAccess;
use App\Services\Costing\CostingRegistry;
use App\Services\Costing\CostingResult;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CostEstimateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Wizard::make([
                            self::jobStep(),
                            ...LabelCostingWizardSchema::steps(),
                            ...PackageCostingWizardSchema::steps(),
                        ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(3),
                Section::make('Live Summary')
                    ->schema([
                        Placeholder::make('total')
                            ->label('Selling Price')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'total', isPrimary: true))
                            ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),
                        Placeholder::make('unit_price')
                            ->label('Unit Price')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'unitPrice', 4))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('subtotal')
                            ->label('Estimated Cost')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'subtotal'))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('margin')
                            ->label('Margin')
                            ->content(fn (Get $get): HtmlString => self::marginValue($get))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('material_consumption')
                            ->label('Material Consumption')
                            ->content(fn (Get $get): HtmlString => self::materials($get))
                            ->extraAttributes(['class' => 'cost-summary-block']),
                        Placeholder::make('summary_warnings')
                            ->label('Warnings')
                            ->content(fn (Get $get): HtmlString => self::warnings($get))
                            ->extraAttributes(['class' => 'cost-summary-block cost-summary-warnings']),
                    ])
                    ->extraAttributes(['class' => 'lg:sticky lg:top-6 liveSummary'])
                    ->hidden(fn (): bool => ! PanelAccess::canSeeMoneyValues())
                    ->columnSpan(1),
            ])
            ->columns(4);
    }

    private static function jobStep(): Step
    {
        return Step::make('Job Information')
            ->columns(2)
            ->schema([
                Hidden::make('estimate_number')
                    ->dehydrated(false),
                TextInput::make('description')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255),
                Select::make('job_type')
                    ->label('Service Type')
                    ->options(fn (): array => app(CostingRegistry::class)->options())
                    ->default('labels')
                    ->live()
                    ->required(),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->required(),
                DatePicker::make('deadline'),
                Textarea::make('remarks')
                    ->columnSpanFull(),
            ]);
    }

    private static function preview(Get $get): ?CostingResult
    {
        if (! self::isPreviewReady($get)) {
            return null;
        }

        return app(CostingRegistry::class)
            ->calculator((string) ($get('job_type') ?: 'labels'))
            ->calculate([
                'quantity' => (int) ($get('quantity') ?: 1),
                'job_type' => (string) ($get('job_type') ?: 'labels'),
                'services' => $get('services') ?? [],
            ]);
    }

    private static function isPreviewReady(Get $get): bool
    {
        if (blank($get('description')) || (int) ($get('quantity') ?: 0) < 1) {
            return false;
        }

        return match ($get('job_type') ?: 'labels') {
            'packages' => filled($get('services.box.length'))
                && filled($get('services.box.width'))
                && filled($get('services.box.height'))
                && filled($get('services.layout.ups'))
                && filled($get('services.material.board_item_id')),
            default => filled($get('services.label.width'))
                && filled($get('services.label.height'))
                && filled($get('services.material.material_item_id')),
        };
    }

    private static function money(float $amount, int $precision = 2): string
    {
        return number_format($amount, $precision).' Birr';
    }

    private static function materials(Get $get): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::materials($preview->materialConsumption);
    }

    private static function summaryText(Get $get, string $field, int $precision = 2, bool $isPrimary = false): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::moneyValue(self::money((float) $preview->{$field}, $precision), $isPrimary);
    }

    private static function marginValue(Get $get): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::margin($preview->marginPercent);
    }

    private static function warnings(Get $get): HtmlString
    {
        if (! self::isPreviewReady($get)) {
            return CostingSnapshotPresenter::warnings(['Complete product specs and material selection to calculate.']);
        }

        $warnings = [];

        if (($get('job_type') ?: 'labels') === 'labels' && blank($get('services.production.machine_id'))) {
            $warnings[] = 'No printing machine selected. Default machine rates are being used.';
        }

        if (($get('job_type') ?: 'labels') === 'packages') {
            foreach (['printing_machine_id', 'diecutting_machine_id', 'folder_gluer_machine_id'] as $field) {
                if (blank($get("services.operations.{$field}"))) {
                    $warnings[] = 'Some machine selections are missing. Default rates are being used.';

                    break;
                }
            }
        }

        if ($warnings === []) {
            return CostingSnapshotPresenter::warnings([]);
        }

        return CostingSnapshotPresenter::warnings($warnings);
    }
}
