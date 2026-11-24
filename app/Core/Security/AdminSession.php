<?php

namespace App\Core\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSession extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    protected $table = 'admin_sessions';

    protected $casts = ['mfa_verified' => 'boolean', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
