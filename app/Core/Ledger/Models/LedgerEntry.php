<?php

namespace App\Core\Ledger\Models;

use App\Core\Ledger\ImmutableLedgerRecord;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use ImmutableLedgerRecord;

    public const UPDATED_AT = null;

    protected $table = 'ledger_entries';

    protected $fillable = ['journal_id', 'account_id', 'amount_minor', 'currency', 'balance_after_minor'];

    protected $casts = ['amount_minor' => 'integer', 'balance_after_minor' => 'integer'];

    public function account()
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function journal()
    {
        return $this->belongsTo(LedgerJournal::class, 'journal_id');
    }
}
