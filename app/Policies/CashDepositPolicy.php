<?php

namespace App\Policies;

use App\Models\CashDeposit;
use App\Models\User;
use App\UserRole;

class CashDepositPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinanceUser($user);
    }

    public function view(User $user, CashDeposit $cashDeposit): bool
    {
        return $this->isFinanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinanceUser($user);
    }

    public function update(User $user, CashDeposit $cashDeposit): bool
    {
        return $this->isFinanceUser($user)
            && $cashDeposit->status === CashDeposit::STATUS_PENDING;
    }

    public function delete(User $user, CashDeposit $cashDeposit): bool
    {
        return $this->update($user, $cashDeposit);
    }

    public function post(User $user, CashDeposit $cashDeposit): bool
    {
        return $this->isFinanceUser($user)
            && $cashDeposit->status === CashDeposit::STATUS_PENDING;
    }

    public function reverse(User $user, CashDeposit $cashDeposit): bool
    {
        return $this->isFinanceUser($user)
            && $cashDeposit->status === CashDeposit::STATUS_POSTED;
    }

    public function reconcile(User $user, CashDeposit $cashDeposit): bool
    {
        return $user->role === UserRole::Admin
            && CashDeposit::cashOnHandBalance() < 0;
    }

    public function restore(User $user, CashDeposit $cashDeposit): bool
    {
        return false;
    }

    public function forceDelete(User $user, CashDeposit $cashDeposit): bool
    {
        return false;
    }

    private function isFinanceUser(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }
}
