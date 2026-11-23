<?php

namespace App\Core\Addons\Models;

use Illuminate\Database\Eloquent\Model;

class InstalledAddon extends Model
{
    protected $table = 'addons';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'version', 'path', 'enabled', 'config', 'lock_hash', 'enabled_at'];

    protected $casts = [
        'enabled'    => 'boolean',
        'config'     => 'encrypted:array', // secrets encrypted at rest with APP_KEY
        'enabled_at' => 'datetime',
    ];

    protected $hidden = ['config'];
}
