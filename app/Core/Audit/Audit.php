<?php

namespace App\Core\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class Audit
{
    public static function record(string $action, ?string $subjectType = null, string|int|null $subjectId = null, array $meta = []): void
    {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id'    => \App\Core\Tenancy\Tenancy::id(),
                'actor_id'     => auth()->check() ? auth()->id() : null,
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId === null ? null : (string) $subjectId,
                'meta'         => $meta === [] ? null : json_encode($meta),
                'ip'           => app()->runningInConsole() ? null : request()->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('audit.write_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
