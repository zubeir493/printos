<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::getSettings();
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Company Information')
                    ->description('Basic company details used in invoices and emails')
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
                                    ]),
                                FileUpload::make('company_logo')
                                    ->label('Company Logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('logos')
                                    ->visibility('public')
                                    ->imagePreviewHeight('80')
                                    ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => $this->getCompanyLogoUploadInfo($component, $file, $storedFileNames))
                                    ->helperText('Used on invoices and receipts. Recommended: square PNG, max 200×200px.'),
                            ]),
                        Textarea::make('company_address')
                            ->label('Address')
                            ->rows(3)
                            ->placeholder('Street, City, Country'),
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

                Section::make('Tax & VAT Settings')
                    ->description('Configure tax rates and VAT settings for invoices')
                    ->schema([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                Toggle::make('vat_enabled')
                                    ->label('VAT Enabled')
                                    ->live(),
                                TextInput::make('vat_rate')
                                    ->label('VAT Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->visible(fn (Get $get) => $get('vat_enabled'))
                                    ->required(),
                            ]),
                    ]),

                Section::make('Payroll Settings')
                    ->description('Company-wide payroll defaults')
                    ->schema([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                Toggle::make('workers_union_enabled')
                                    ->label('Workers Union Enabled')
                                    ->live(),
                                TextInput::make('workers_union_rate')
                                    ->label('Workers Union Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->visible(fn (Get $get) => $get('workers_union_enabled')),
                                TextInput::make('employee_pension_rate')
                                    ->label('Employee Pension Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required(),
                                TextInput::make('employer_pension_rate')
                                    ->label('Employer Pension Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Invoice Settings')
                    ->description('Default invoice configuration')
                    ->schema([
                        Grid::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('invoice_prefix')
                                    ->label('Invoice Prefix')
                                    ->placeholder('INV')
                                    ->required(),
                                TextInput::make('receipt_prefix')
                                    ->label('Receipt Prefix')
                                    ->placeholder('RCP')
                                    ->required(),
                                TextInput::make('invoice_due_days')
                                    ->label('Default Due Days')
                                    ->numeric()
                                    ->suffix('days')
                                    ->required(),
                            ]),
                        Textarea::make('invoice_terms')
                            ->label('Default Terms & Conditions')
                            ->rows(5)
                            ->placeholder('Enter default terms to appear on invoices'),
                    ]),

                Section::make('Currency Settings')
                    ->description('Default currency configuration')
                    ->schema([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('currency_code')
                                    ->label('Currency Code')
                                    ->placeholder('Birr')
                                    ->required()
                                    ->maxLength(3),
                                TextInput::make('currency_symbol')
                                    ->label('Currency Symbol')
                                    ->placeholder('Birr')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Costing Settings')
                    ->description('Default commercial assumptions for estimates')
                    ->schema([
                        Grid::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('costing_defaults.overhead_percent')
                                    ->label('Default Overhead (%)')
                                    ->numeric()
                                    ->suffix('%'),
                                TextInput::make('costing_defaults.profit_margin_percent')
                                    ->label('Default Profit Margin (%)')
                                    ->numeric()
                                    ->suffix('%'),
                                TextInput::make('costing_defaults.waste_percent')
                                    ->label('Default Waste Allowance (%)')
                                    ->numeric()
                                    ->suffix('%'),
                                TextInput::make('costing_defaults.plate_unit_cost')
                                    ->label('Plate Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.make_ready_unit_cost')
                                    ->label('Make Ready Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.label_cutting_unit_cost')
                                    ->label('Label Cutting Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.label_diecutting_unit_cost')
                                    ->label('Label Diecutting Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.package_die_unit_cost')
                                    ->label('Package Die Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.manual_finishing_unit_cost')
                                    ->label('Manual Finishing Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.varnish_unit_cost')
                                    ->label('Varnish Cost')
                                    ->numeric()
                                    ->suffix('Birr'),
                                TextInput::make('costing_defaults.currency_precision')
                                    ->label('Currency Precision')
                                    ->numeric(),
                                TextInput::make('costing_defaults.rounding_strategy')
                                    ->label('Rounding Strategy'),
                            ]),
                    ]),

                Section::make('Email Settings')
                    ->description('Email configuration for invoice notifications')
                    ->schema([
                        TextInput::make('email_from_name')
                            ->label('From Name')
                            ->placeholder(config('app.name')),
                        TextInput::make('email_from_address')
                            ->label('From Address')
                            ->email()
                            ->placeholder('noreply@company.com'),
                        Textarea::make('email_footer')
                            ->label('Email Footer Text')
                            ->rows(2)
                            ->placeholder('Text to appear at the bottom of all emails'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
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

        if ($existingSettings) {
            $existingSettings->update($data);
            $settings = $existingSettings->fresh();
        } else {
            $settings = Setting::create($data);
        }

        Cache::forget('app_settings');
        $this->form->fill($settings->toArray());

        Notification::make()
            ->title('Settings saved')
            ->body('Your settings have been saved successfully.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action(fn () => $this->save())
                ->icon(Heroicon::OutlinedDocumentCheck),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
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
