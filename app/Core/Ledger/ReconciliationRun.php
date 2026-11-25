<?php

namespace App\Core\Ledger;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = ['details' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function passed(): bool
    {
        return $this->mismatches === 0 && $this->unbalanced_journals === 0;
    }
}
