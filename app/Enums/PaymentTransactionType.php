<?php

namespace App\Enums;

enum PaymentTransactionType: string
{
    case CUSTOMER_RECEIPT = 'customer_receipt';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case DIRECT_EXPENSE = 'direct_expense';
    case PETTY_CASH_FUNDING = 'petty_cash_funding';
    case PETTY_CASH_EXPENSE = 'petty_cash_expense';
    case CASH_SALE_RECEIPT = 'cash_sale_receipt';
    case PAYROLL_PAYMENT = 'payroll_payment';
    case EMPLOYEE_LOAN_DISBURSEMENT = 'employee_loan_disbursement';
    case EMPLOYEE_LOAN_REPAYMENT = 'employee_loan_repayment';
    case BID_BOND_ISSUE = 'bid_bond_issue';
    case BID_BOND_RECOVERY = 'bid_bond_recovery';
    case PERFORMANCE_BOND_ISSUE = 'performance_bond_issue';
    case PERFORMANCE_BOND_RECOVERY = 'performance_bond_recovery';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_RECEIPT => 'Customer Receipt',
            self::SUPPLIER_PAYMENT => 'Supplier / Bill Payment',
            self::DIRECT_EXPENSE => 'Direct Expense',
            self::PETTY_CASH_FUNDING => 'Petty Cash Funding',
            self::PETTY_CASH_EXPENSE => 'Petty Cash Expense',
            self::CASH_SALE_RECEIPT => 'Cash Sale Receipt',
            self::PAYROLL_PAYMENT => 'Payroll Payment',
            self::EMPLOYEE_LOAN_DISBURSEMENT => 'Employee Loan Disbursement',
            self::EMPLOYEE_LOAN_REPAYMENT => 'Employee Loan Repayment',
            self::BID_BOND_ISSUE => 'Bid Bond Issue',
            self::BID_BOND_RECOVERY => 'Bid Bond Recovery',
            self::PERFORMANCE_BOND_ISSUE => 'Performance Bond Issue',
            self::PERFORMANCE_BOND_RECOVERY => 'Performance Bond Recovery',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CUSTOMER_RECEIPT => 'Money received from a customer. Debits cash/bank and credits AR.',
            self::SUPPLIER_PAYMENT => 'Payment to settle a supplier or bill. Debits AP and credits cash/bank.',
            self::DIRECT_EXPENSE => 'Normal operating expense paid from cash/bank. Debits an expense account.',
            self::PETTY_CASH_FUNDING => 'Moves money into petty cash. Debits petty cash and credits cash/bank.',
            self::PETTY_CASH_EXPENSE => 'Expense paid out of petty cash. Debits expense and credits petty cash.',
            self::CASH_SALE_RECEIPT => 'Receipt record for a cash sale already posted by the sales journal.',
            self::PAYROLL_PAYMENT => 'Salary payment. Debits payroll payable and credits cash/bank.',
            self::EMPLOYEE_LOAN_DISBURSEMENT => 'Employee loan paid out. Debits employee loan receivable and credits cash/bank.',
            self::EMPLOYEE_LOAN_REPAYMENT => 'Employee loan repayment. Debits cash/bank and credits employee loan receivable.',
            self::BID_BOND_ISSUE => 'Refundable bid bond issued. Debits bid bond receivable and credits cash/bank.',
            self::BID_BOND_RECOVERY => 'Refundable bid bond recovered. Debits cash/bank and credits bid bond receivable.',
            self::PERFORMANCE_BOND_ISSUE => 'Refundable performance bond issued. Debits performance bond receivable and credits cash/bank.',
            self::PERFORMANCE_BOND_RECOVERY => 'Refundable performance bond recovered. Debits cash/bank and credits performance bond receivable.',
        };
    }

    public function requiresPartner(): bool
    {
        return in_array($this, [
            self::CUSTOMER_RECEIPT,
            self::SUPPLIER_PAYMENT,
            self::BID_BOND_ISSUE,
            self::BID_BOND_RECOVERY,
            self::PERFORMANCE_BOND_ISSUE,
            self::PERFORMANCE_BOND_RECOVERY,
        ], true);
    }

    public function partnerLabel(): string
    {
        return match ($this) {
            self::CUSTOMER_RECEIPT => 'Customer',
            self::SUPPLIER_PAYMENT => 'Supplier / Vendor',
            self::BID_BOND_ISSUE, self::BID_BOND_RECOVERY, self::PERFORMANCE_BOND_ISSUE, self::PERFORMANCE_BOND_RECOVERY => 'Procuring Entity',
            default => 'Counterparty',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::CUSTOMER_RECEIPT, self::CASH_SALE_RECEIPT, self::EMPLOYEE_LOAN_REPAYMENT, self::BID_BOND_RECOVERY, self::PERFORMANCE_BOND_RECOVERY => 'inbound',
            default => 'outbound',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            if ($case === self::CASH_SALE_RECEIPT) {
                continue;
            }

            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
