<?php

namespace App\Core\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';

    protected $table = 'tenants';

    protected $fillable = ['slug', 'name', 'status', 'plan', 'settings'];

    protected $casts = ['settings' => 'array'];

    protected static function booted(): void
    {
        static::created(fn (Tenant $tenant) => \App\Core\Tenancy\TenantProvisioner::provision($tenant));
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
