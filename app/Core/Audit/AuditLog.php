<?php

namespace App\Core\Audit;

use Illuminate\Database\Eloquent\Model;

/** Read model for audit_logs (written only through Audit::record). Not tenant-scoped: platform view. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $casts = ['meta' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log is append-only'));
        static::deleting(fn () => throw new \LogicException('Audit log is append-only'));
    }
}
