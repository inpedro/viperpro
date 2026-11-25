<?php

namespace App\Core\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $table = 'tenant_domains';

    protected $fillable = ['tenant_id', 'domain', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function normalize(string $host): string
    {
        return strtolower(rtrim(trim($host), '.'));
    }
}
