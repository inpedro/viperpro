<?php

namespace App\Core\Ledger\Models;

use App\Core\Ledger\ImmutableLedgerRecord;
use Illuminate\Database\Eloquent\Model;

class LedgerJournal extends Model
{
    use ImmutableLedgerRecord;

    public const UPDATED_AT = null;

    protected $table = 'ledger_journals';

    protected $fillable = [
        'uuid', 'type', 'idempotency_key', 'reference_type', 'reference_id',
        'actor_id', 'source', 'description', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class, 'journal_id');
    }
}
