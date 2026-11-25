<?php

namespace App\Core\Security;

use Illuminate\Database\Eloquent\Model;

/** Read model over callback_logs (written only by CallbackLog::record). Tenant-scoped. */
class CallbackLogEntry extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'callback_logs';

    protected $casts = ['payload' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Callback log is append-only'));
        static::deleting(fn () => throw new \LogicException('Callback log is append-only'));
    }
}
