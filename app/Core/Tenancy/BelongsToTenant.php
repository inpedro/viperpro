<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tenant ownership for an Eloquent model: global scope on reads, tenant_id stamped on
 * create, and a hard refusal to create/move a row into another tenant.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (Tenancy::isUnscoped() && $model->tenant_id !== null) {
                return; // explicit maintenance code chose the tenant
            }

            $current = Tenancy::isPlatform() ? null : Tenancy::id();

            if (! Tenancy::isResolved()) {
                throw new LogicException('Cannot create '.static::class.' without a tenant context');
            }

            if ($model->tenant_id !== null && (int) $model->tenant_id !== (int) $current) {
                throw new LogicException('Refusing to create '.static::class.' in another tenant');
            }

            $model->tenant_id = $current;
        });

        static::updating(function ($model) {
            if ($model->isDirty('tenant_id') && ! Tenancy::isUnscoped()) {
                throw new LogicException('tenant_id is immutable');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
