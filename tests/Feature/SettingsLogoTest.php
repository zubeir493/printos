<?php

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('company logo uses a local file path for invoice rendering and a public url for previews', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('logos/company.png', 'logo image contents');

    $settings = Setting::create([
        'company_name' => 'Packledge',
        'company_logo' => 'logos/company.png',
    ]);

    $companyInfo = $settings->getCompanyInfo();

    expect($companyInfo['logo'])->toBe(Storage::disk('public')->path('logos/company.png'))
        ->and($companyInfo['logo_url'])->toBe(Storage::disk('public')->url('logos/company.png'))
        ->and($companyInfo['logo_data_uri'])->toStartWith('data:');
});

test('invoice templates use embedded logo data when available', function (): void {
    $html = view('invoices.sales-order', [
        'invoiceData' => [
            'invoice_number' => 'INV-2026-000001',
            'invoice_date' => '2026-05-16',
            'due_date' => '2026-05-30',
            'order' => (object) [
                'partner' => (object) [
                    'name' => 'Customer',
                    'address' => null,
                    'phone' => null,
                    'email' => null,
                ],
                'paid_amount' => 0,
            ],
            'items' => [],
            'company_info' => [
                'name' => 'Packledge',
                'address' => null,
                'phone' => null,
                'email' => null,
                'tax_id' => null,
                'logo' => '/storage/logos/company.png',
                'logo_data_uri' => 'data:image/png;base64,'.base64_encode('logo image contents'),
            ],
            'tax_calculations' => [],
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'balance_due' => 0,
            'status' => 'unpaid',
            'options' => [],
        ],
    ])->render();

    expect($html)
        ->toContain('data:image/png;base64,')
        ->not->toContain('src="/storage/logos/company.png"');
});

test('proforma pdf template uses embedded logo data when available', function (): void {
    $html = view('proformas.pdf', [
        'proformaData' => [
            'proforma_number' => 'PRO-2026-000001',
            'issue_date' => '2026-05-16',
            'company_info' => [
                'name' => 'Packledge',
                'phone' => null,
                'email' => null,
                'tax_id' => null,
                'logo' => '/storage/logos/company.png',
                'logo_data_uri' => 'data:image/png;base64,'.base64_encode('logo image contents'),
            ],
            'customer_info' => ['name' => 'Customer'],
            'items' => [],
            'subtotal' => 0,
            'vat_rate' => 15,
            'tax_amount' => 0,
            'total' => 0,
            'amount_in_words' => 'Zero Birr',
            'validity_days' => 7,
            'remarks' => null,
            'bank_accounts' => [],
        ],
    ])->render();

    expect($html)
        ->toContain('class="document-logo"')
        ->toContain('data:image/png;base64,')
        ->not->toContain('src="/storage/logos/company.png"');
});

test('pdf shared styles keep the page white and totals understated', function (): void {
    $styles = file_get_contents(resource_path('views/invoices/_styles.blade.php'));

    expect($styles)
        ->toContain('body {')
        ->toContain('background: #ffffff;')
        ->toContain('border-bottom: 1px dashed #cfd5dd;')
        ->not->toContain('background: #f4f5f7;')
        ->not->toContain('tr.total td:nth-child(2)');
});

test('settings page stores a single logo path and reloads it after saving', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    Storage::fake('public');
    Storage::disk('public')->put('logos/company.png', 'logo image contents');

    $settings = Setting::createDefault();
    $settings->update(['company_logo' => 'logos/company.png']);

    $component = Livewire::test(SettingsPage::class)
        ->call('save');

    expect(Arr::wrap($component->get('data.company_logo')))->toContain('logos/company.png');

    $logo = Setting::first()->company_logo;

    expect($logo)->toBe('logos/company.png')
        ->and(Storage::disk('public')->exists($logo))->toBeTrue();
});

test('settings page is organized into non persistent tabs', function (): void {
    $source = file_get_contents(app_path('Filament/Pages/Settings.php'));

    expect($source)
        ->toContain("Tabs::make('Settings sections')")
        ->toContain('->contained(false)')
        ->not->toContain('->persistTab()')
        ->not->toContain('->persistTabInQueryString(')
        ->toContain("->keyBindings(['command+s', 'ctrl+s'])")
        ->toContain("Tab::make('Company')")
        ->toContain("Tab::make('Finance')")
        ->toContain("Tab::make('Payroll')")
        ->toContain("Tab::make('Communication')")
        ->toContain("Tab::make('Integrations')")
        ->toContain('Section::make()')
        ->toContain('HasUnsavedDataChangesAlert')
        ->toContain('$this->rememberData();')
        ->not->toContain("Section::make('Tax & VAT Settings')")
        ->not->toContain("Section::make('Invoice Settings')")
        ->not->toContain("Section::make('Currency & Fiscal Year')");
});

test('settings tabs use the scoped underline style', function (): void {
    $source = file_get_contents(resource_path('css/filament/admin/theme.css'));
    $view = file_get_contents(resource_path('views/filament/pages/settings.blade.php'));

    expect($source)
        ->toContain('.settings-tabs > .fi-tabs')
        ->toContain('border-bottom: 1px solid var(--gray-200);')
        ->toContain('margin-inline: 0;')
        ->toContain('overflow-x: auto;')
        ->toContain('scrollbar-width: none;')
        ->toContain('.settings-tabs > .fi-tabs .fi-tabs-item.fi-active::after')
        ->toContain('background: var(--primary-600);')
        ->toContain('.settings-tabs > .fi-sc-tabs-tab.fi-active')
        ->toContain('margin-top: 2rem;')
        ->and($view)
        ->toContain('scrollActiveSettingsTabIntoView')
        ->toContain("scrollIntoView({ block: 'nearest', inline: 'center' })");
});
