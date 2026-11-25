<?php

namespace App\Http\Middleware;

use App\Core\Security\Mfa;
use App\Core\Tenancy\Tenancy;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persistent auth middleware for the staff panels (admin, platform, affiliate):
 *  - records each staff session (admin_sessions) so it can be listed and revoked;
 *  - logs out revoked sessions;
 *  - enforces TOTP MFA for required roles: enrollment, then a per-session challenge.
 */
class AdminSessionGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $panel = Filament::getCurrentPanel();
        if (! $user || ! $panel) {
            return $next($request);
        }

        $hash = hash('sha256', $request->session()->getId());
        $row = DB::table('admin_sessions')->where('session_hash', $hash)->first();

        if ($row && $row->revoked_at !== null) {
            Auth::guard($panel->getAuthGuard())->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($panel->getLoginUrl());
        }

        if (! $row) {
            DB::table('admin_sessions')->insert([
                'tenant_id' => Tenancy::id(), 'user_id' => $user->id, 'session_hash' => $hash, 'panel' => $panel->getId(),
                'ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'mfa_verified' => false, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('admin_sessions')->where('session_hash', $hash)->first();
        } elseif ($row->last_seen_at === null || abs(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($row->last_seen_at))) > 60) {
            DB::table('admin_sessions')->where('id', $row->id)->update(['last_seen_at' => now(), 'ip' => $request->ip()]);
        }

        if (Mfa::isRequiredFor($user) && ! $row->mfa_verified && ! $this->onMfaPage($request)) {
            return redirect()->to($panel->getUrl().'/mfa');
        }

        return $next($request);
    }

    private function onMfaPage(Request $request): bool
    {
        $path = Livewire::isLivewireRequest() ? (string) Livewire::originalPath() : $request->path();

        return (bool) preg_match('#(^|/)(mfa|logout)$#', trim($path, '/'));
    }
}
