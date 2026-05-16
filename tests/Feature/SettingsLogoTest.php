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
        ->and($companyInfo['logo_url'])->toBe(Storage::disk('public')->url('logos/company.png'));
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
