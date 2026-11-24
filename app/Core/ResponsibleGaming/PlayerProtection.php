<?php

namespace App\Core\ResponsibleGaming;

use Illuminate\Database\Eloquent\Model;

class PlayerProtection extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    protected $table = 'responsible_gaming_settings';

    protected $fillable = ['user_id'];

    protected $casts = [
        'pending_limits_effective_at' => 'datetime',
        'excluded_until'              => 'datetime',
        'excluded_permanently'        => 'boolean',
    ];
}
