<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ASSETS (1000 - 1999)
            ['code' => '1000', 'name' => 'Cash in Hand', 'type' => 'Asset'],
            ['code' => '1010', 'name' => 'Bank Current Account', 'type' => 'Asset'],
            ['code' => '1090', 'name' => 'Petty Cash', 'type' => 'Asset'],
            ['code' => '1200', 'name' => 'Accounts Receivable (Debtors)', 'type' => 'Asset'],
            ['code' => '1230', 'name' => 'Employee Loans Receivable', 'type' => 'Asset'],
            ['code' => '1260', 'name' => 'Withholding Receivable', 'type' => 'Asset'],
            ['code' => '1240', 'name' => 'Bid Bonds Receivable', 'type' => 'Asset'],
            ['code' => '1250', 'name' => 'Performance Bonds Receivable', 'type' => 'Asset'],
            ['code' => '1500', 'name' => 'Inventory', 'type' => 'Asset'],
            ['code' => '1800', 'name' => 'Office Equipment', 'type' => 'Asset'],

            // LIABILITIES (2000 - 2999)
            ['code' => '2000', 'name' => 'Accounts Payable (Creditors)', 'type' => 'Liability'],
            ['code' => '2100', 'name' => 'VAT Payable', 'type' => 'Liability'],
            ['code' => '2150', 'name' => 'Payroll Payable', 'type' => 'Liability'],
            ['code' => '2160', 'name' => 'PAYE Tax Payable', 'type' => 'Liability'],
            ['code' => '2165', 'name' => 'Payroll Penalty Clearing', 'type' => 'Liability'],
            ['code' => '2170', 'name' => 'Pension Payable', 'type' => 'Liability'],
            ['code' => '2180', 'name' => 'Withholding Payable', 'type' => 'Liability'],
            ['code' => '2190', 'name' => 'Workers Union Payable', 'type' => 'Liability'],
            ['code' => '2200', 'name' => 'Accrued Salaries', 'type' => 'Liability'],
            ['code' => '2500', 'name' => 'Bank Loan', 'type' => 'Liability'],

            // EQUITY (3000 - 3999)
            ['code' => '3000', 'name' => 'Owners Capital', 'type' => 'Equity'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'Equity'],

            // REVENUE (4000 - 4999)
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'Revenue'],
            ['code' => '4100', 'name' => 'Service Income', 'type' => 'Revenue'],
            ['code' => '4200', 'name' => 'Interest Income', 'type' => 'Revenue'],

            // EXPENSES (5000 - 5999)
            ['code' => '5000', 'name' => 'Cost of Goods Sold (COGS)', 'type' => 'Expense'],
            ['code' => '5100', 'name' => 'Salaries & Wages', 'type' => 'Expense'],
            ['code' => '5110', 'name' => 'Overtime Expense', 'type' => 'Expense'],
            ['code' => '5120', 'name' => 'Pension Expense', 'type' => 'Expense'],
            ['code' => '5200', 'name' => 'Rent Expense', 'type' => 'Expense'],
            ['code' => '5300', 'name' => 'Electricity & Water', 'type' => 'Expense'],
            ['code' => '5400', 'name' => 'Marketing & Advertising', 'type' => 'Expense'],
            ['code' => '5800', 'name' => 'Bank Charges', 'type' => 'Expense'],
            ['code' => '5990', 'name' => 'Miscellaneous Expense', 'type' => 'Expense'],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
