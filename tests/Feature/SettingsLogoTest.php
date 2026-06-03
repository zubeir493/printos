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
        'company_name' => 'PrintOS',
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
                'name' => 'PrintOS',
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
                'name' => 'PrintOS',
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
