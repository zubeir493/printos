<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
            'tax_configuration' => 'array',
            'invoice_due_days' => 'integer',
        ];
    }

    /**
     * Get the singleton settings instance
     */
    public static function getSettings(): self
    {
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
            'company_phone' => '+1 234 567 8900',
            'company_email' => 'billing@yourcompany.com',
            'company_website' => 'www.yourcompany.com',
            'company_tax_id' => 'TAX-123456789',
            'vat_rate' => 15.00,
            'vat_enabled' => true,
            'tax_configuration' => [
                ['name' => 'VAT', 'rate' => 0.15],
            ],
            'invoice_terms' => "1. Payment is due within 30 days of invoice date.\n2. Late payments are subject to a 1.5% monthly interest charge.\n3. All prices are inclusive of applicable taxes unless otherwise stated.\n4. Goods remain the property of the company until paid in full.\n5. Please quote invoice number when making payment.",
            'invoice_due_days' => 30,
            'invoice_prefix' => 'INV',
            'receipt_prefix' => 'RCP',
            'currency_code' => 'ETB',
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
            'logo' => $this->company_logo,
        ];
    }

    /**
     * Get tax configuration for invoice generation
     */
    public function getTaxConfiguration(): array
    {
        $taxes = $this->tax_configuration ?? [];
        $config = [];

        foreach ($taxes as $tax) {
            $config[strtoupper($tax['name'])] = $tax['rate'];
        }

        // Add VAT if enabled and not already in config
        if ($this->vat_enabled && ! isset($config['VAT'])) {
            $config['VAT'] = $this->vat_rate / 100;
        }

        return $config;
    }

    /**
     * Clear settings cache on save
     */
    protected static function booted(): void
    {
        static::saved(function ($setting) {
            Cache::forget('app_settings');
        });

        static::updated(function ($setting) {
            Cache::forget('app_settings');
        });
    }
}
