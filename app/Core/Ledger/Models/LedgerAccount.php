<?php

namespace App\Core\Ledger\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'ledger_accounts';

    protected $fillable = ['code', 'kind', 'wallet_id', 'bucket', 'currency'];

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }
}
