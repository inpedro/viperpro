<?php

namespace App\Core\Addons\Models;

use Illuminate\Database\Eloquent\Model;

class TenantAddon extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    protected $table = 'tenant_addons';

    protected $fillable = ['addon_id', 'enabled', 'config'];

    protected $casts = [
        'enabled' => 'boolean',
        'config'  => 'encrypted:array',
    ];

    protected $hidden = ['config'];
}
