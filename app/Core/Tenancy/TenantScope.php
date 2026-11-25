<?php

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Tenancy::isUnscoped()) {
            return;
        }

        $column = $model->qualifyColumn('tenant_id');

        if (Tenancy::isPlatform()) {
            $builder->whereNull($column);
            return;
        }

        $id = Tenancy::id();
        // No context resolved: match nothing rather than everything (fail closed).
        $id === null ? $builder->whereRaw('1 = 0') : $builder->where($column, $id);
    }
}
