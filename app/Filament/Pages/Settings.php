<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use App\Models\Setting;
use App\Services\Accounting\GenerateAccountingExport;
use App\Support\FiscalCalendar;
use App\Support\Money;
use App\UserRole;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class Settings extends Page implements HasForms
{
    use HasUnsavedDataChangesAlert;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::getSettings();
        AccountingIntegration::ensureConfiguredProviders();
        $integrations = AccountingIntegration::query()->get()->keyBy('provider');
        $peachtree = $integrations->get(AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP);
        $this->form->fill([
            ...$settings->toArray(),
            'timezone' => $settings->timezone ?? $peachtree?->timezone,
            ...collect(AccountingIntegration::providerDefinitions())
                ->mapWithKeys(fn (array $definition, string $provider): array => [
                    $this->integrationToggleKey($provider) => (bool) $integrations->get($provider)?->enabled,
                ])
                ->all(),
            'peachtree_daily_cutoff' => $peachtree?->daily_cutoff,
        ]);

        $this->rememberData();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Tabs::make('Settings sections')
                    ->hiddenLabel()
                    ->contained(false)
                    ->id('settings-tabs')
                    ->extraAttributes(['class' => 'settings-tabs'])
                    ->tabs([
                        Tab::make('Company')
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->schema($this->companySettingsSchema()),
                        Tab::make('Finance')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema($this->financeSettingsSchema()),
                        Tab::make('Payroll')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema($this->payrollSettingsSchema()),
                        Tab::make('Communication')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->schema($this->communicationSettingsSchema()),
                        Tab::make('Integrations')
                            ->icon(Heroicon::OutlinedPuzzlePiece)
                            ->schema($this->integrationSettingsTabSchema()),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $integrationData = Arr::only($data, [
            ...array_map(
                fn (string $provider): string => $this->integrationToggleKey($provider),
                array_keys(AccountingIntegration::providerDefinitions()),
            ),
        ]);
        $data = Arr::except($data, array_keys($integrationData));
        // Keep tax_configuration in sync with the vat_rate/vat_enabled fields
        // so that any non-VAT entries are preserved while VAT stays current.
        $existingSettings = Setting::first();
        $existingTaxConfig = $existingSettings?->tax_configuration ?? [];
        $nonVatTaxes = array_values(
            array_filter($existingTaxConfig, fn ($t) => strtoupper($t['name']) !== 'VAT')
        );

        $vatEnabled = (bool) ($data['vat_enabled'] ?? false);
        $vatRate = (float) ($data['vat_rate'] ?? 0);

        if ($vatEnabled && $vatRate > 0) {
            $data['tax_configuration'] = array_merge(
                $nonVatTaxes,
                [['name' => 'VAT', 'rate' => $vatRate / 100]]
            );
        } else {
            $data['tax_configuration'] = $nonVatTaxes;
        }

        if (array_key_exists('company_logo', $data) && is_array($data['company_logo'])) {
            $data['company_logo'] = Arr::first($data['company_logo']);
        }

        if (blank($data['company_logo'] ?? null) && filled($existingSettings?->company_logo)) {
            $data['company_logo'] = $existingSettings->company_logo;
        }

        $data['currency_symbol'] = Money::currencies()[$data['currency_code']]['suffix'] ?? $data['currency_code'];

        if ($existingSettings) {
            $existingSettings->update($data);
            $settings = $existingSettings->fresh();
        } else {
            $settings = Setting::create($data);
        }

        Cache::forget('app_settings');
        foreach (array_keys(AccountingIntegration::providerDefinitions()) as $provider) {
            AccountingIntegration::integrationFor($provider)->update([
                'enabled' => (bool) ($integrationData[$this->integrationToggleKey($provider)] ?? false),
                'timezone' => $settings->timezone ?? config('app.timezone'),
            ]);
        }

        $peachtree = AccountingIntegration::peachtreeDesktop();
        $this->form->fill([
            ...$settings->toArray(),
            ...collect(AccountingIntegration::providerDefinitions())
                ->mapWithKeys(fn (array $definition, string $provider): array => [
                    $this->integrationToggleKey($provider) => AccountingIntegration::integrationFor($provider)->enabled,
                ])
                ->all(),
            'peachtree_daily_cutoff' => $peachtree->daily_cutoff,
        ]);

        $this->rememberData();

        Notification::make()
            ->title('Settings saved')
            ->body('Your settings have been saved successfully.')
            ->success()
            ->send();
    }

    public function generatePeachtreeExport(): void
    {
        $integration = AccountingIntegration::peachtreeDesktop();
        $export = app(GenerateAccountingExport::class)->handle(
            $integration,
            now($integration->timezone),
            auth()->user(),
        );

        if ($export?->status === AccountingExport::STATUS_FAILED) {
            Notification::make()
                ->title('Peachtree export failed')
                ->body($export->error_message)
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($export ? 'Peachtree export generated' : 'No journals to export')
            ->body($export ? "{$export->row_count} journal lines are ready to download." : 'All eligible posted journals are already exported.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Admin;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action(fn () => $this->save())
                ->keyBindings(['command+s', 'ctrl+s']),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    /** @return array<int, mixed> */
    private function companySettingsSchema(): array
    {
        return [
            Group::make()
                // ->description('Identity, location, timezone, and public contact details.')
                ->schema([
                    Grid::make()
                        ->schema([
                            Grid::make()
                                ->columns(1)
                                ->schema([
                                    TextInput::make('company_name')
                                        ->label('Company Name')
                                        ->required()
                                        ->placeholder('Your Company Name'),
                                    TextInput::make('company_tax_id')
                                        ->label('Tax ID / VAT Number')
                                        ->placeholder('TAX-123456789'),
                                    Select::make('timezone')
                                        ->label('Company timezone')
                                        ->options(array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                                        ->searchable()
                                        ->native(false)
                                        ->required(),
                                ]),
                            Group::make()
                                ->schema([
                                    FileUpload::make('company_logo')
                                        ->label('Company Logo')
                                        ->image()
                                        ->disk('public')
                                        ->directory('logos')
                                        ->visibility('public')
                                        ->imagePreviewHeight('80')
                                        ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => $this->getCompanyLogoUploadInfo($component, $file, $storedFileNames))
                                        ->helperText('Used on invoices and receipts.'),
                                    Textarea::make('company_address')
                                        ->label('Address')
                                        ->placeholder('Street, City, Country'),
                                ]),
                        ]),
                    Grid::make()
                        ->columns(3)
                        ->schema([
                            TextInput::make('company_phone')
                                ->label('Phone')
                                ->tel()
                                ->placeholder('+1 234 567 8900'),
                            TextInput::make('company_email')
                                ->label('Email')
                                ->email()
                                ->placeholder('billing@company.com'),
                            TextInput::make('company_website')
                                ->label('Website')
                                ->url()
                                ->placeholder('www.company.com'),
                        ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function financeSettingsSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('currency_code')
                                ->label('Currency')
                                ->options(collect(Money::currencies())->mapWithKeys(fn (array $currency, string $code): array => [
                                    $code => "{$code} ({$currency['suffix']})",
                                ]))
                                ->searchable()
                                ->native(false)
                                ->required(),
                            Select::make('fiscal_calendar')
                                ->label('Fiscal year')
                                ->options(FiscalCalendar::calendarOptions())
                                ->native(false)
                                ->required(),
                            Toggle::make('vat_enabled')
                                ->label('Enable VAT')
                                ->live(),
                        ]),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('vat_rate')
                                ->label('VAT Rate (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%')
                                ->visible(fn (Get $get): bool => (bool) $get('vat_enabled')),
                            TextInput::make('invoice_prefix')
                                ->label('Invoice Prefix')
                                ->placeholder('INV'),
                            TextInput::make('receipt_prefix')
                                ->label('Receipt Prefix')
                                ->placeholder('REC'),
                            TextInput::make('invoice_due_days')
                                ->label('Default Due Days')
                                ->numeric()
                                ->minValue(0)
                                ->suffix('days'),
                        ]),
                    Textarea::make('invoice_terms')
                        ->label('Invoice Terms')
                        ->placeholder('Payment terms and conditions...')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function payrollSettingsSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Toggle::make('workers_union_enabled')
                                ->label('Enable Workers Union')
                                ->live(),
                            TextInput::make('workers_union_rate')
                                ->label('Workers Union Rate (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%')
                                ->visible(fn (Get $get): bool => (bool) $get('workers_union_enabled')),
                            TextInput::make('employee_pension_rate')
                                ->label('Employee Pension (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%'),
                            TextInput::make('employer_pension_rate')
                                ->label('Employer Pension (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%'),
                        ]),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function communicationSettingsSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('email_from_name')
                                ->label('From Name')
                                ->placeholder('Your Company'),
                            TextInput::make('email_from_address')
                                ->label('From Email')
                                ->email()
                                ->placeholder('noreply@company.com'),
                        ]),
                    Textarea::make('email_footer')
                        ->label('Email Footer')
                        ->placeholder('Thank you for your business...')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private function integrationSettingsTabSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Grid::make(3)
                        ->schema($this->integrationCards()),
                ]),
        ];
    }

    /** @return array<int, Section> */
    private function integrationCards(): array
    {
        return collect(AccountingIntegration::providerDefinitions())
            ->map(fn (array $definition, string $provider): Section => $this->integrationCard($provider, $definition))
            ->values()
            ->all();
    }

    /** @param  array{name: string, summary: string, logo: string, domain: string, exportable: bool}  $definition */
    private function integrationCard(string $provider, array $definition): Section
    {
        return Section::make()
            ->compact()
            ->gap(0)
            ->schema([
                Placeholder::make("{$provider}_brand")
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString($this->integrationBrandMarkup($definition))),
                Group::make()
                    ->extraAttributes(['class' => 'integration-footer'])
                    ->schema([
                        SchemaActions::make([
                            Action::make("configure_{$provider}")
                                ->label('Configure')
                                ->icon(Heroicon::OutlinedCog6Tooth)
                                ->color('gray')
                                ->modalHeading("{$definition['name']} settings")
                                ->fillForm(fn (): array => $this->integrationSettingsState($provider))
                                ->schema(fn (): array => $this->integrationSettingsSchema($provider))
                                ->modalSubmitActionLabel($provider === AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP ? 'Save integration' : 'Close')
                                ->modalSubmitAction($provider === AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP ? null : false)
                                ->action(fn (array $data): null => $this->saveIntegrationSettings($provider, $data))
                                ->size('sm')
                                ->modalWidth('lg'),
                        ]),
                        Toggle::make($this->integrationToggleKey($provider))
                            ->hiddenLabel()
                            ->live(),
                    ]),
            ]);
    }

    /** @param  array{name: string, summary: string, logo: string, domain: string, exportable: bool}  $definition */
    private function integrationBrandMarkup(array $definition): string
    {
        $name = e($definition['name']);
        $summary = e($definition['summary']);

        return <<<HTML
            <div>
                <div class="flex items-start justify-between gap-3">
                    <img src="images/{$definition['name']}.jpg" alt="{$name} logo" class="h-11 object-contain" />
                </div>
                <div class="py-4">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{$name}</div>
                    <p class="text-sm leading-5 text-gray-600 dark:text-gray-300">{$summary}</p>
                </div>
            </div>
        HTML;
    }

    /** @return array<string, string|null> */
    private function integrationSettingsState(string $provider): array
    {
        if ($provider !== AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP) {
            return [];
        }

        return ['daily_cutoff' => AccountingIntegration::peachtreeDesktop()->daily_cutoff];
    }

    /** @return array<int, mixed> */
    private function integrationSettingsSchema(string $provider): array
    {
        if ($provider !== AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP) {
            return [
                Placeholder::make("{$provider}_placeholder")
                    ->label('Status')
                    ->content('Connector settings are reserved for the upcoming API integration. You can enable this provider now to prepare account overrides.'),
            ];
        }

        return [
            TimePicker::make('daily_cutoff')
                ->label('Daily cutoff')
                ->seconds(false)
                ->required(),
            Placeholder::make('default_mapping')
                ->label('Account mapping default')
                ->content('Exports use each Packledge account code as the Peachtree Account ID unless an override is set.'),
            Placeholder::make('mapping_status')
                ->label('Override mappings')
                ->content(function (): HtmlString {
                    $mapped = AccountingIntegration::peachtreeDesktop()->mappings()->count();
                    $url = AccountResource::getUrl('index');

                    return new HtmlString("<span>{$mapped} overrides configured. <a class=\"text-primary-600 hover:underline\" href=\"{$url}\">Manage overrides</a></span>");
                }),
        ];
    }

    private function saveIntegrationSettings(string $provider, array $data): null
    {
        if ($provider !== AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP) {
            return null;
        }

        $integration = AccountingIntegration::peachtreeDesktop();
        $integration->update([
            'daily_cutoff' => $data['daily_cutoff'],
        ]);
        $this->data['peachtree_daily_cutoff'] = $integration->daily_cutoff;

        Notification::make()
            ->title('Peachtree settings saved')
            ->success()
            ->send();

        return null;
    }

    private function integrationToggleKey(string $provider): string
    {
        if ($provider === AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP) {
            return 'peachtree_enabled';
        }

        return "{$provider}_enabled";
    }

    /**
     * @return array{name: string, size: int, type: string|null, url: string}|null
     */
    private function getCompanyLogoUploadInfo(BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array
    {
        $storage = Storage::disk($component->getDiskName());

        if (! $storage->exists($file)) {
            return null;
        }

        return [
            'name' => is_array($storedFileNames) ? ($storedFileNames[$file] ?? basename($file)) : ($storedFileNames ?? basename($file)),
            'size' => $storage->size($file),
            'type' => $storage->mimeType($file),
            'url' => '/storage/'.ltrim($file, '/'),
        ];
    }
}
