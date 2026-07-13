<?php

namespace Database\Factories;

use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingExport>
 */
class AccountingExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accounting_integration_id' => AccountingIntegration::factory(),
            'cutoff_at' => now(),
            'status' => AccountingExport::STATUS_COMPLETED,
            'file_path' => 'accounting-exports/test.xlsx',
            'file_name' => 'test.xlsx',
            'journal_count' => 1,
            'row_count' => 2,
            'total_debit' => 100,
            'total_credit' => 100,
            'checksum' => hash('sha256', 'test'),
            'generated_at' => now(),
        ];
    }
}
