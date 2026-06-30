<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Filament\Support\PanelAccess;
use App\Models\Partner;
use App\Services\Costing\CostingRegistry;
use App\Services\Costing\CostingResult;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

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
                            ...BookCostingWizardSchema::steps(),
                            ...LabelCostingWizardSchema::steps(),
                            ...PackageCostingWizardSchema::steps(),
                        ])
                            ->nextAction(fn (Action $action): Action => $action->color(Color::Indigo))
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(5),
                Section::make()
                    ->schema([
                        Placeholder::make('material_consumption')
                            ->label('Material Consumption')
                            ->content(fn (Get $get): HtmlString => self::materials($get))
                            ->hidden(fn (Get $get): bool => ! self::hasMaterialConsumption($get))
                            ->extraAttributes(['class' => 'cost-summary-block']),
                        Placeholder::make('machine_usage')
                            ->label('Machine Usage')
                            ->content(fn (Get $get): HtmlString => self::machines($get))
                            ->hidden(fn (Get $get): bool => ! self::hasMachineUsage($get))
                            ->extraAttributes(['class' => 'cost-summary-block']),
                        Placeholder::make('unit_price')
                            ->label('Unit Price')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'unitPrice'))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('subtotal')
                            ->label('Subtotal')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'subtotal', precision: 2, fixedPrecision: true))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('margin')
                            ->label(fn (Get $get): string => self::marginLabel($get))
                            ->content(fn (Get $get): HtmlString => self::marginValue($get))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('tax_amount')
                            ->label(fn (Get $get): string => self::taxLabel($get))
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'taxAmount'))
                            ->hidden(fn (Get $get): bool => ! self::hasTax($get))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('discount_amount')
                            ->label('Discount')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'discountAmount'))
                            ->extraAttributes(['class' => 'cost-summary-metric']),
                        Placeholder::make('total')
                            ->label('Final Price')
                            ->content(fn (Get $get): HtmlString => self::summaryText($get, 'total', isPrimary: true))
                            ->extraAttributes(['class' => 'cost-summary-metric cost-summary-total']),
                    ])
                    ->extraAttributes(['class' => 'lg:sticky lg:top-6 liveSummary'])
                    ->hidden(fn (): bool => ! PanelAccess::canSeeMoneyValues())
                    ->columnSpan(2),
            ])
            ->columns(7);
    }

    private static function jobStep(): Step
    {
        return Step::make('Job Information')
            ->columns(2)
            ->schema([
                Hidden::make('estimate_number')
                    ->dehydrated(false),
                Select::make('partner_id')
                    ->label('Customer')
                    ->options(fn (): array => Partner::query()->where('is_customer', true)->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                TextInput::make('description')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255),
                Hidden::make('job_type')
                    ->default('labels'),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->required(),
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
            'books' => filled($get('services.book.page_count'))
                && filled($get('services.book.size'))
                && filled($get('services.book.binding')),
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

    private static function money(float $amount, int $maxPrecision = 2, bool $fixedPrecision = false): string
    {
        return Number::format($amount, precision: $fixedPrecision ? $maxPrecision : null, maxPrecision: $fixedPrecision ? null : $maxPrecision).' '.Money::suffix();
    }

    private static function materials(Get $get): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::materials($preview->materialConsumption);
    }

    private static function machines(Get $get): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::machines($preview->machineUsage);
    }

    private static function hasMaterialConsumption(Get $get): bool
    {
        $preview = self::preview($get);

        return $preview !== null && $preview->materialConsumption !== [];
    }

    private static function hasMachineUsage(Get $get): bool
    {
        $preview = self::preview($get);

        return $preview !== null && $preview->machineUsage !== [];
    }

    private static function summaryText(Get $get, string $field, int $precision = 2, bool $isPrimary = false, bool $fixedPrecision = false): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::moneyValue(self::money((float) $preview->{$field}, $precision, $fixedPrecision), $isPrimary);
    }

    private static function marginValue(Get $get): HtmlString
    {
        $preview = self::preview($get);

        if (! $preview) {
            return CostingSnapshotPresenter::waitingValue();
        }

        return CostingSnapshotPresenter::moneyValue(self::money((float) $preview->profitAmount));
    }

    private static function marginLabel(Get $get): string
    {
        $preview = self::preview($get);

        if (! $preview) {
            return 'Profit Margin';
        }

        return 'Profit Margin ('.Number::format($preview->marginPercent, maxPrecision: 2).'%)';
    }

    private static function taxLabel(Get $get): string
    {
        $preview = self::preview($get);

        if (! $preview) {
            return 'VAT';
        }

        return 'VAT ('.Number::format($preview->vatRate, maxPrecision: 2).'%)';
    }

    private static function hasTax(Get $get): bool
    {
        $preview = self::preview($get);

        return $preview !== null && $preview->taxAmount > 0;
    }

    private static function warnings(Get $get): HtmlString
    {
        return CostingSnapshotPresenter::warnings(self::warningMessages($get));
    }

    private static function hasWarnings(Get $get): bool
    {
        return self::warningMessages($get) !== [];
    }

    /**
     * @return array<int, string>
     */
    private static function warningMessages(Get $get): array
    {
        if (! self::isPreviewReady($get)) {
            return [];
        }

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

        return $warnings ?? [];
    }
}
