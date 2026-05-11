<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\Cache;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 100;

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
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required()
                            ->placeholder('Your Company Name'),
                        FileUpload::make('company_logo')
                            ->label('Company Logo')
                            ->image()
                            ->disk('public')
                            ->directory('logos')
                            ->visibility('public')
                            ->imagePreviewHeight('80')
                            ->helperText('Used on invoices and receipts. Recommended: square PNG, max 200×200px.'),
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
                        TextInput::make('company_tax_id')
                            ->label('Tax ID / VAT Number')
                            ->placeholder('TAX-123456789'),
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
                                    ->placeholder('ETB')
                                    ->required()
                                    ->maxLength(3),
                                TextInput::make('currency_symbol')
                                    ->label('Currency Symbol')
                                    ->placeholder('Birr')
                                    ->required(),
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

        if ($existingSettings) {
            $existingSettings->update($data);
        } else {
            Setting::create($data);
        }

        Cache::forget('app_settings');

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
}
