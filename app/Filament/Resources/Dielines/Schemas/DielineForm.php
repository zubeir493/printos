<?php

namespace App\Filament\Resources\Dielines\Schemas;

use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class DielineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Placeholder::make('preview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::preview($get))
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => 'dieline-generator-preview'])
                    ->columnSpan(['default' => 1, 'xl' => 8]),
                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Hidden::make('name')
                                    ->default('New dieline'),
                                Select::make('template_key')
                                    ->label('Dieline type')
                                    ->options(fn (): array => app(DielineTemplateRegistry::class)->options())
                                    ->default('reverse-tuck-flap-box')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, ?string $state): mixed => self::applyTemplateDefaults($set, $state))
                                    ->columnSpanFull(),
                                Hidden::make('job_order_task_id'),
                                ...self::baseDimensionFields(),
                                ...self::advancedDimensionFields(),
                                Hidden::make('geometry'),
                                Hidden::make('created_by'),
                            ])
                            ->columns(1)
                            ->extraAttributes(['class' => 'dieline-generator-sidebar']),
                    ])
                    ->columnSpan(['default' => 1, 'xl' => 4]),
            ])
            ->columns(['default' => 1, 'xl' => 12]);
    }

    /**
     * @return array<int, TextInput>
     */
    private static function baseDimensionFields(): array
    {
        return [
            self::dimensionField('l', 'Length', 160),
            self::dimensionField('w', 'Width', 50),
            self::dimensionField('h', 'Height', 90),
        ];
    }

    /**
     * @return array<int, TextInput>
     */
    private static function advancedDimensionFields(): array
    {
        return collect(app(DielineTemplateRegistry::class)->advancedFields())
            ->map(fn (array $field): TextInput => self::dimensionField(
                $field['key'],
                $field['label'],
                (float) $field['default'],
                (float) ($field['min'] ?? 0),
                $field['suffix'] ?? 'mm',
            )
                ->visible(fn (Get $get): bool => in_array($get('template_key') ?: 'reverse-tuck-flap-box', $field['templates'], true))
                ->required(fn (Get $get): bool => in_array($get('template_key') ?: 'reverse-tuck-flap-box', $field['templates'], true)))
            ->all();
    }

    private static function dimensionField(string $key, string $label, float $default, float $min = 1, string $suffix = 'mm'): TextInput
    {
        return TextInput::make("dimensions.{$key}")
            ->label($label)
            ->numeric()
            ->minValue($min)
            ->suffix($suffix)
            ->default($default)
            ->afterStateHydrated(function (TextInput $component, mixed $state, Get $get) use ($key, $default): void {
                if ($state !== null && $state !== '') {
                    return;
                }

                $length = (float) ($get('dimensions.l') ?? 0);
                $component->state($key === 'dust_flap' && $length > 0 ? $length * 0.5 : $default);
            })
            ->live(onBlur: true)
            ->required();
    }

    private static function applyTemplateDefaults(Set $set, ?string $templateKey): null
    {
        $template = app(DielineTemplateRegistry::class)->template($templateKey ?: 'reverse-tuck-flap-box');

        foreach ($template->defaults() as $key => $value) {
            $set("dimensions.{$key}", $value);
        }

        return null;
    }

    private static function preview(Get $get): HtmlString
    {
        try {
            $geometry = app(DielineGeometryService::class)->generate(
                (string) ($get('template_key') ?: 'reverse-tuck-flap-box'),
                (array) ($get('dimensions') ?? []),
            );

            return app(SvgDielineRenderer::class)->preview($geometry);
        } catch (\Throwable $e) {
            return new HtmlString('<div class="dieline-preview-frame dieline-preview-empty">Enter valid measurements to preview the dieline.</div>');
        }
    }
}
