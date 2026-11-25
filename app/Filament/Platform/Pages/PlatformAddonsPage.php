<?php

namespace App\Filament\Platform\Pages;

use App\Core\Tenancy\Tenancy;
use App\Filament\Admin\Pages\AddonManagerPage;

/** Platform-wide addon installation: install / enable / disable / update, with verification. */
class PlatformAddonsPage extends AddonManagerPage
{
    protected static ?string $slug = 'addons';

    protected function mode(): string
    {
        return 'platform';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->tenant_id === null && Tenancy::isPlatform() && $user->hasRole('super-admin');
    }
}
