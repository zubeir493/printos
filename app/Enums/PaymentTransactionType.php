<?php

namespace App\Enums;

enum PaymentTransactionType: string
{
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

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
            self::CASH_SALE_RECEIPT => 'Paid-now Sale Receipt',
            self::PAYROLL_PAYMENT => 'Payroll Payment',
            self::EMPLOYEE_LOAN_DISBURSEMENT => 'Employee Loan Disbursement',
            self::EMPLOYEE_LOAN_REPAYMENT => 'Employee Loan Repayment',
            self::BID_BOND_ISSUE => 'Bid Bond Issue',
            self::BID_BOND_RECOVERY => 'Bid Bond Recovery',
            self::PERFORMANCE_BOND_ISSUE => 'Performance Bond Issue',
            self::PERFORMANCE_BOND_RECOVERY => 'Performance Bond Recovery',
        };
    }

    public function paymentFormLabel(): string
    {
        return match ($this) {
            self::CUSTOMER_RECEIPT => 'Receive from customer',
            self::SUPPLIER_PAYMENT => 'Pay supplier / bill',
            self::DIRECT_EXPENSE => 'Pay expense',
            self::PETTY_CASH_FUNDING => 'Fund petty cash',
            self::PETTY_CASH_EXPENSE => 'Record petty cash expense',
            self::PAYROLL_PAYMENT => 'Pay payroll payable',
            self::EMPLOYEE_LOAN_DISBURSEMENT => 'Give employee loan',
            self::EMPLOYEE_LOAN_REPAYMENT => 'Receive employee loan repayment',
            self::BID_BOND_ISSUE => 'Issue bid bond',
            self::BID_BOND_RECOVERY => 'Recover bid bond',
            self::PERFORMANCE_BOND_ISSUE => 'Issue performance bond',
            self::PERFORMANCE_BOND_RECOVERY => 'Recover performance bond',
            self::CASH_SALE_RECEIPT => 'Paid-now sale receipt',
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
            self::CUSTOMER_RECEIPT, self::CASH_SALE_RECEIPT, self::EMPLOYEE_LOAN_REPAYMENT, self::BID_BOND_RECOVERY, self::PERFORMANCE_BOND_RECOVERY => self::DIRECTION_INBOUND,
            default => self::DIRECTION_OUTBOUND,
        };
    }

    public function isExpense(): bool
    {
        return in_array($this, [
            self::DIRECT_EXPENSE,
            self::PETTY_CASH_EXPENSE,
        ], true);
    }

    public function usesPettyCashAccount(): bool
    {
        return in_array($this, [
            self::PETTY_CASH_FUNDING,
            self::PETTY_CASH_EXPENSE,
        ], true);
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

    /**
     * @return array<string, array<string, string>>
     */
    public static function paymentFormOptions(): array
    {
        return [
            'Common' => [
                self::CUSTOMER_RECEIPT->value => self::CUSTOMER_RECEIPT->paymentFormLabel(),
                self::SUPPLIER_PAYMENT->value => self::SUPPLIER_PAYMENT->paymentFormLabel(),
                self::DIRECT_EXPENSE->value => self::DIRECT_EXPENSE->paymentFormLabel(),
            ],
            'Petty Cash' => [
                self::PETTY_CASH_FUNDING->value => self::PETTY_CASH_FUNDING->paymentFormLabel(),
                self::PETTY_CASH_EXPENSE->value => self::PETTY_CASH_EXPENSE->paymentFormLabel(),
            ],
            'Bonds' => [
                self::BID_BOND_ISSUE->value => self::BID_BOND_ISSUE->paymentFormLabel(),
                self::BID_BOND_RECOVERY->value => self::BID_BOND_RECOVERY->paymentFormLabel(),
                self::PERFORMANCE_BOND_ISSUE->value => self::PERFORMANCE_BOND_ISSUE->paymentFormLabel(),
                self::PERFORMANCE_BOND_RECOVERY->value => self::PERFORMANCE_BOND_RECOVERY->paymentFormLabel(),
            ],
        ];
    }
}
