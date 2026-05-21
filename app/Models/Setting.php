<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'company_tax_id',
        'company_logo',
        'vat_rate',
        'vat_enabled',
        'workers_union_enabled',
        'workers_union_rate',
        'tax_configuration',
        'invoice_terms',
        'invoice_due_days',
        'invoice_prefix',
        'receipt_prefix',
        'currency_code',
        'currency_symbol',
        'email_from_name',
        'email_from_address',
        'email_footer',
    ];

    protected function casts(): array
    {
        return [
            'vat_enabled' => 'boolean',
            'vat_rate' => 'decimal:2',
            'workers_union_enabled' => 'boolean',
            'workers_union_rate' => 'decimal:2',
            'tax_configuration' => 'array',
            'invoice_due_days' => 'integer',
        ];
    }

    /**
     * Get the singleton settings instance
     */
    public static function getSettings(): self
    {
        if (config('cache.default') === 'array') {
            return static::first() ?? static::createDefault();
        }

        return Cache::remember('app_settings', 3600, function () {
            return static::first() ?? static::createDefault();
        });
    }

    /**
     * Create default settings
     */
    public static function createDefault(): self
    {
        return static::create([
            'company_name' => config('app.name', 'PrintOS'),
            'company_address' => '123 Business Street, City, Country',
            'company_phone' => '+1234678900',
            'company_email' => 'billing@yourcompany.com',
            'company_website' => 'https://www.yourcompany.com',
            'company_tax_id' => 'TAX-123456789',
            'vat_rate' => 15.00,
            'vat_enabled' => true,
            'workers_union_enabled' => true,
            'workers_union_rate' => 1,
            'tax_configuration' => [
                ['name' => 'VAT', 'rate' => 0.15],
            ],
            'invoice_terms' => "1. Payment is due within 30 days of invoice date.\n2. All prices are inclusive of applicable taxes unless otherwise stated.\n3. Goods remain the property of the company until paid in full.\n4. Please quote invoice number when making payment.",
            'invoice_due_days' => 30,
            'invoice_prefix' => 'INV',
            'receipt_prefix' => 'RCP',
            'currency_code' => 'Birr',
            'currency_symbol' => 'Birr',
        ]);
    }

    /**
     * Get company information as array for invoices
     */
    public function getCompanyInfo(): array
    {
        return [
            'name' => $this->company_name,
            'address' => $this->company_address,
            'phone' => $this->company_phone,
            'email' => $this->company_email,
            'website' => $this->company_website,
            'tax_id' => $this->company_tax_id,
            'logo' => $this->getCompanyLogoPath(),
            'logo_url' => $this->getCompanyLogoUrl(),
            'logo_data_uri' => $this->getCompanyLogoDataUri(),
        ];
    }

    public function getCompanyLogoPath(): ?string
    {
        if (! $this->company_logo || ! Storage::disk('public')->exists($this->company_logo)) {
            return null;
        }

        return Storage::disk('public')->path($this->company_logo);
    }

    public function getCompanyLogoUrl(): ?string
    {
        if (! $this->company_logo) {
            return null;
        }

        return Storage::disk('public')->url($this->company_logo);
    }

    public function getCompanyLogoDataUri(): ?string
    {
        if (! $this->company_logo || ! Storage::disk('public')->exists($this->company_logo)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($this->company_logo);
        $contents = Storage::disk('public')->get($this->company_logo);

        if (! $mimeType || $contents === false) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    /**
     * Get tax configuration for invoice generation
     */
    public function getTaxConfiguration(): array
    {
        $taxes = $this->tax_configuration ?? [];
        $config = [];

        foreach ($taxes as $tax) {
            $name = strtoupper($tax['name']);
            // Rates stored in tax_configuration may be either a decimal (0.15)
            // or a percentage (15). Normalise to a decimal multiplier.
            $rate = (float) $tax['rate'];
            $config[$name] = $rate > 1 ? $rate / 100 : $rate;
        }

        // VAT is managed via vat_rate (always a percentage). Override any
        // stale value that may exist in tax_configuration.
        if ($this->vat_enabled) {
            $config['VAT'] = (float) $this->vat_rate / 100;
        } else {
            unset($config['VAT']);
        }

        return $config;
    }

    /**
     * Clear settings cache on save
     */
    protected static function booted(): void
    {
        // saved fires on both create and update, so one listener is sufficient
        static::saved(function ($setting) {
            Cache::forget('app_settings');
        });
    }
}
