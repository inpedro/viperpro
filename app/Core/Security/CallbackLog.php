<?php

namespace App\Core\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Append-only audit trail of inbound payment and game-provider callbacks.
 * Never throws: logging failure must not change the outcome of a callback.
 */
class CallbackLog
{
    private const REDACT = ['password', 'secret', 'agent_secret', 'token', 'access_token', 'cs', 'ci', 'key', 'card', 'cvv'];

    public static function record(string $channel, string $source, Request $request, string $outcome, ?string $reason = null, ?string $reference = null): void
    {
        try {
            $raw = (string) $request->getContent();

            DB::table('callback_logs')->insert([
                'tenant_id'      => \App\Core\Tenancy\Tenancy::id(),
                'channel'        => $channel,
                'source'         => substr($source, 0, 50),
                'ip'             => $request->ip(),
                'outcome'        => $outcome,
                'reason'         => $reason ? substr($reason, 0, 191) : null,
                'reference'      => $reference ? substr($reference, 0, 191) : null,
                'payload_sha256' => hash('sha256', $raw),
                'payload'        => json_encode(self::redact($request->all())),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('callback_log.write_failed', ['source' => $source, 'error' => $e->getMessage()]);
        }
    }

    private static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::redact($v);
            } elseif (in_array(strtolower((string) $k), self::REDACT, true)) {
                $data[$k] = '[redacted]';
            }
        }
        return $data;
    }
}
