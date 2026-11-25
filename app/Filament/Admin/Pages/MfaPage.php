<?php

namespace App\Filament\Admin\Pages;

use App\Core\Security\Mfa;
use App\Core\Security\Totp;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Two-factor authentication: enrollment, per-session challenge and status.
 * Reached automatically through AdminSessionGuard; subclassed for the other staff panels.
 */
class MfaPage extends Page
{
    protected static string $view = 'filament.pages.mfa-page';

    protected static ?string $slug = 'mfa';

    protected static bool $shouldRegisterNavigation = false;

    public string $code = '';

    /** @var string[] recovery codes, shown once right after enrollment */
    public array $recoveryCodes = [];

    public function getTitle(): string
    {
        return __('Two-factor authentication');
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        if (! Mfa::isEnabled(auth()->user()) && ! session()->has('mfa_pending_secret')) {
            session()->put('mfa_pending_secret', Totp::generateSecret());
        }
    }

    public function getState(): string
    {
        if ($this->recoveryCodes !== []) {
            return 'codes';
        }
        if (! Mfa::isEnabled(auth()->user())) {
            return 'enroll';
        }

        return $this->sessionVerified() ? 'verified' : 'challenge';
    }

    public function getPendingSecret(): ?string
    {
        return session('mfa_pending_secret');
    }

    public function getProvisioningUri(): ?string
    {
        $secret = $this->getPendingSecret();
        $issuer = (string) (\App\Core\Tenancy\Tenancy::current()?->name ?? config('app.name'));

        return $secret ? Totp::provisioningUri($secret, (string) auth()->user()->email, $issuer) : null;
    }

    public function enroll(): void
    {
        $secret = (string) session('mfa_pending_secret');
        $codes = $secret !== '' ? Mfa::enable(auth()->user(), $secret, $this->code) : null;
        $this->code = '';

        if ($codes === null) {
            Notification::make()->title(__('Invalid code'))->danger()->send();
            return;
        }

        session()->forget('mfa_pending_secret');
        $this->markVerified();
        $this->recoveryCodes = $codes;
    }

    public function challenge()
    {
        $ok = Mfa::verify(auth()->user(), $this->code);
        $this->code = '';

        if (! $ok) {
            Notification::make()->title(__('Invalid code'))->danger()->send();
            return null;
        }

        $this->markVerified();

        return redirect()->to(Filament::getCurrentPanel()->getUrl());
    }

    public function continueToPanel()
    {
        $this->recoveryCodes = [];

        return redirect()->to(Filament::getCurrentPanel()->getUrl());
    }

    private function sessionVerified(): bool
    {
        return (bool) DB::table('admin_sessions')->where('session_hash', hash('sha256', session()->getId()))->value('mfa_verified');
    }

    private function markVerified(): void
    {
        DB::table('admin_sessions')->where('session_hash', hash('sha256', session()->getId()))->update(['mfa_verified' => true, 'updated_at' => now()]);
    }
}
