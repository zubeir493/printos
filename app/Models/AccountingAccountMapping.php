<?php

namespace App\Models;

use Database\Factories\AccountingAccountMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAccountMapping extends Model
{
    /** @use HasFactory<AccountingAccountMappingFactory> */
    use HasFactory;

    protected $fillable = ['accounting_integration_id', 'account_id', 'external_account_id'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(AccountingIntegration::class, 'accounting_integration_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
