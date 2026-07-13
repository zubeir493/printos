<?php

namespace App\Models;

use Database\Factories\AccountingIntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingIntegration extends Model
{
    /** @use HasFactory<AccountingIntegrationFactory> */
    use HasFactory;

    public const PROVIDER_PEACHTREE_DESKTOP = 'peachtree_desktop';

    public const PROVIDER_XERO = 'xero';

    public const PROVIDER_QUICKBOOKS = 'quickbooks';

    public const PROVIDER_SAGE_CLOUD = 'sage_cloud';

    public const PROVIDER_SQL = 'sql';

    protected $fillable = ['provider', 'name', 'enabled', 'timezone', 'daily_cutoff', 'configuration'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'configuration' => 'array'];
    }

    public static function peachtreeDesktop(): self
    {
        return self::integrationFor(self::PROVIDER_PEACHTREE_DESKTOP);
    }

    public static function integrationFor(string $provider): self
    {
        $definition = self::providerDefinitions()[$provider] ?? null;

        return self::firstOrCreate(
            ['provider' => $provider],
            [
                'name' => $definition['name'] ?? str($provider)->headline()->toString(),
                'timezone' => config('app.timezone', 'Africa/Addis_Ababa'),
                'daily_cutoff' => '23:55',
            ],
        );
    }

    /** @return array<string, array{name: string, summary: string, logo: string, domain: string, exportable: bool}> */
    public static function providerDefinitions(): array
    {
        return [
            self::PROVIDER_PEACHTREE_DESKTOP => [
                'name' => 'Peachtree Desktop',
                'summary' => 'Daily Excel journal export for legacy Sage 50.',
                'logo' => 'images/logo.svg',
                'domain' => 'sage50.local',
                'exportable' => true,
            ],
            self::PROVIDER_XERO => [
                'name' => 'Xero',
                'summary' => 'Coming Soon',
                'logo' => 'XE',
                'domain' => 'xero.com',
                'exportable' => false,
            ],
            self::PROVIDER_QUICKBOOKS => [
                'name' => 'QuickBooks',
                'summary' => 'Coming Soon',
                'logo' => 'QB',
                'domain' => 'quickbooks.intuit.com',
                'exportable' => false,
            ],
            self::PROVIDER_SAGE_CLOUD => [
                'name' => 'Sage 50 Cloud',
                'summary' => 'Coming Soon',
                'logo' => 'SC',
                'domain' => 'sage.com',
                'exportable' => false,
            ],
            self::PROVIDER_SQL => [
                'name' => 'SQL Accounting',
                'summary' => 'Coming Soon',
                'logo' => 'SQL',
                'domain' => 'database.local',
                'exportable' => false,
            ],
        ];
    }

    public static function ensureConfiguredProviders(): void
    {
        foreach (array_keys(self::providerDefinitions()) as $provider) {
            self::integrationFor($provider);
        }
    }

    /** @return list<string> */
    public static function exportableProviders(): array
    {
        return collect(self::providerDefinitions())
            ->filter(fn (array $definition): bool => $definition['exportable'])
            ->keys()
            ->values()
            ->all();
    }

    public function supportsExport(): bool
    {
        return (bool) (self::providerDefinitions()[$this->provider]['exportable'] ?? false);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AccountingAccountMapping::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AccountingExport::class);
    }
}
