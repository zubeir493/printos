<?php

namespace App\Services\Accounting;

use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;

class CreatePaymentJournalEntry
{
    public function handle(Payment $payment): void
    {
        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return;
        }

        if ($this->hasPostedJournalEntry($payment)) {
            return;
        }

        $transactionType = $payment->transaction_type
            ? PaymentTransactionType::tryFrom($payment->transaction_type) ?? $this->legacyTransactionType($payment->payment_type, $payment->direction)
            : $this->legacyTransactionType($payment->payment_type, $payment->direction);

        if ($transactionType === PaymentTransactionType::CASH_SALE_RECEIPT) {
            return;
        }

        $cashAccount = $this->resolveCashAccount($payment);
        $bankAccount = $this->resolveBankAccount();
        $arAccount = Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset');
        $apAccount = Account::getSystemAccount(Account::CODE_AP, 'Accounts Payable', 'Liability');
        $withholdingAccount = Account::getSystemAccount(Account::CODE_WITHHOLDING_RECEIVABLE, 'Withholding Receivable', 'Asset');
        $withholdingPayableAccount = Account::getSystemAccount(Account::CODE_WITHHOLDING_PAYABLE, 'Withholding Payable', 'Liability');
        $sourceAccountId = $this->resolveSourceAccountId($payment, $cashAccount, $bankAccount);
        $expenseAccountId = $this->resolveExpenseAccountId($payment);
        $pettyCashAccountId = $this->resolvePettyCashAccountId($payment);

        $journalEntry = JournalEntry::create([
            'date' => $payment->payment_date ?? now(),
            'reference' => 'Payment #'.$payment->payment_number,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'narration' => $payment->reference ?? 'Payment #'.$payment->payment_number.' ('.$transactionType->label().')',
            'total_debit' => $amount,
            'total_credit' => $amount,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        match ($transactionType) {
            PaymentTransactionType::CUSTOMER_RECEIPT => $this->createCustomerReceiptItems($journalEntry->id, $sourceAccountId, $withholdingAccount->id, $arAccount->id, $amount, (float) $payment->withholding_amount),
            PaymentTransactionType::SUPPLIER_PAYMENT => $this->createSupplierPaymentItems($journalEntry->id, $apAccount->id, $sourceAccountId, $withholdingPayableAccount->id, $amount, (float) $payment->withholding_amount),
            PaymentTransactionType::DIRECT_EXPENSE => $this->createItems($journalEntry->id, $expenseAccountId, $sourceAccountId, $amount),
            PaymentTransactionType::PETTY_CASH_FUNDING => $this->createItems($journalEntry->id, $pettyCashAccountId, $sourceAccountId, $amount),
            PaymentTransactionType::PETTY_CASH_EXPENSE => $this->createItems($journalEntry->id, $expenseAccountId, $pettyCashAccountId, $amount),
            PaymentTransactionType::CASH_SALE_RECEIPT => null,
            PaymentTransactionType::PAYROLL_PAYMENT => $this->createItems($journalEntry->id, Account::getSystemAccount('2150', 'Payroll Payable', 'Liability')->id, $sourceAccountId, $amount),
            PaymentTransactionType::EMPLOYEE_LOAN_DISBURSEMENT => $this->createItems($journalEntry->id, Account::getSystemAccount('1230', 'Employee Loans Receivable', 'Asset')->id, $sourceAccountId, $amount),
            PaymentTransactionType::EMPLOYEE_LOAN_REPAYMENT => $this->createItems($journalEntry->id, $sourceAccountId, Account::getSystemAccount('1230', 'Employee Loans Receivable', 'Asset')->id, $amount),
            PaymentTransactionType::BID_BOND_ISSUE => $this->createItems($journalEntry->id, $this->resolveBidBondReceivableAccountId(), $sourceAccountId, $amount),
            PaymentTransactionType::BID_BOND_RECOVERY => $this->createItems($journalEntry->id, $sourceAccountId, $this->resolveBidBondReceivableAccountId(), $amount),
            PaymentTransactionType::PERFORMANCE_BOND_ISSUE => $this->createItems($journalEntry->id, $this->resolvePerformanceBondReceivableAccountId(), $sourceAccountId, $amount),
            PaymentTransactionType::PERFORMANCE_BOND_RECOVERY => $this->createItems($journalEntry->id, $sourceAccountId, $this->resolvePerformanceBondReceivableAccountId(), $amount),
        };
    }

    protected function legacyTransactionType(?string $paymentType, ?string $direction): PaymentTransactionType
    {
        return match ($paymentType) {
            'expense' => PaymentTransactionType::DIRECT_EXPENSE,
            'petty_cash' => $direction === 'inbound'
                ? PaymentTransactionType::PETTY_CASH_FUNDING
                : PaymentTransactionType::PETTY_CASH_EXPENSE,
            default => $direction === 'outbound'
                ? PaymentTransactionType::SUPPLIER_PAYMENT
                : PaymentTransactionType::CUSTOMER_RECEIPT,
        };
    }

    private function hasPostedJournalEntry(Payment $payment): bool
    {
        return JournalEntry::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->whereNull('reversal_of_journal_entry_id')
            ->exists();
    }

    private function resolveSourceAccountId(Payment $payment, Account $cashAccount, Account $bankAccount): int
    {
        if ($payment->account_id) {
            return $payment->account_id;
        }

        if ($payment->method === 'petty_cash') {
            return $this->resolvePettyCashAccountId($payment);
        }

        return in_array($payment->method, ['bank', 'bank_transfer', 'cheque', 'check'], true)
            ? $bankAccount->id
            : $cashAccount->id;
    }

    private function resolveExpenseAccountId(Payment $payment): int
    {
        if ($payment->expense_account_id) {
            return $payment->expense_account_id;
        }

        return Account::getSystemAccount('5990', 'Miscellaneous Expense', 'Expense')->id;
    }

    private function resolvePettyCashAccountId(Payment $payment): int
    {
        if ($payment->petty_cash_account_id) {
            return $payment->petty_cash_account_id;
        }

        return Account::getSystemAccount('1090', 'Petty Cash', 'Asset')->id;
    }

    private function resolveBidBondReceivableAccountId(): int
    {
        return Account::getSystemAccount(Account::CODE_BID_BONDS_RECEIVABLE, 'Bid Bonds Receivable', 'Asset')->id;
    }

    private function resolvePerformanceBondReceivableAccountId(): int
    {
        return Account::getSystemAccount(Account::CODE_PERFORMANCE_BONDS_RECEIVABLE, 'Performance Bonds Receivable', 'Asset')->id;
    }

    private function resolveCashAccount(Payment $payment): Account
    {
        if ($payment->account_id) {
            return Account::findOrFail($payment->account_id);
        }

        return Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');
    }

    private function resolveBankAccount(): Account
    {
        return Account::getSystemAccount('1010', 'Bank Current Account', 'Asset');
    }

    private function createItems(int $entryId, int $debitAccountId, int $creditAccountId, float $amount): void
    {
        JournalItem::create([
            'journal_entry_id' => $entryId,
            'account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
        ]);
        JournalItem::create([
            'journal_entry_id' => $entryId,
            'account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    private function createCustomerReceiptItems(int $entryId, int $sourceAccountId, int $withholdingAccountId, int $receivableAccountId, float $amount, float $withholdingAmount): void
    {
        $withholdingAmount = min($amount, max(0, round($withholdingAmount, 2)));
        $cashAmount = round($amount - $withholdingAmount, 2);

        if ($cashAmount > 0) {
            JournalItem::create([
                'journal_entry_id' => $entryId,
                'account_id' => $sourceAccountId,
                'debit' => $cashAmount,
                'credit' => 0,
            ]);
        }

        if ($withholdingAmount > 0) {
            JournalItem::create([
                'journal_entry_id' => $entryId,
                'account_id' => $withholdingAccountId,
                'debit' => $withholdingAmount,
                'credit' => 0,
            ]);
        }

        JournalItem::create([
            'journal_entry_id' => $entryId,
            'account_id' => $receivableAccountId,
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    private function createSupplierPaymentItems(int $entryId, int $payableAccountId, int $sourceAccountId, int $withholdingPayableAccountId, float $amount, float $withholdingAmount): void
    {
        $withholdingAmount = min($amount, max(0, round($withholdingAmount, 2)));
        $cashAmount = round($amount - $withholdingAmount, 2);

        JournalItem::create([
            'journal_entry_id' => $entryId,
            'account_id' => $payableAccountId,
            'debit' => $amount,
            'credit' => 0,
        ]);

        if ($cashAmount > 0) {
            JournalItem::create([
                'journal_entry_id' => $entryId,
                'account_id' => $sourceAccountId,
                'debit' => 0,
                'credit' => $cashAmount,
            ]);
        }

        if ($withholdingAmount > 0) {
            JournalItem::create([
                'journal_entry_id' => $entryId,
                'account_id' => $withholdingPayableAccountId,
                'debit' => 0,
                'credit' => $withholdingAmount,
            ]);
        }
    }
}
