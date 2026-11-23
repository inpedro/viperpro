<?php

namespace App\Console\Commands\Addons;

use App\Core\Addons\AddonIntegrity;
use App\Core\Addons\AddonManager;
use Illuminate\Console\Command;

/**
 * Packaging tools for the release pipeline (never run on production servers with the secret key).
 *
 *   addon:package lock <id|--all>          write addon.lock
 *   addon:package keygen                   print a new Ed25519 key pair
 *   addon:package sign <id> --key-id=...   sign addon.lock with ADDON_SIGNING_KEY (base64 secret)
 *   addon:package verify [<id>]            full per-file verification of installed addons
 */
class AddonPackageCommand extends Command
{
    protected $signature = 'addon:package {action : lock|keygen|sign|verify} {id?} {--all} {--key-id=}';
    protected $description = 'Lock, sign and verify addon packages';

    public function handle(AddonManager $addons): int
    {
        return match ($this->argument('action')) {
            'lock'   => $this->lock($addons),
            'keygen' => $this->keygen(),
            'sign'   => $this->sign($addons),
            'verify' => $this->verify($addons),
            default  => self::INVALID,
        };
    }

    private function lock(AddonManager $addons): int
    {
        $ids = $this->option('all') ? array_keys($addons->discover(true)) : [(string) $this->argument('id')];
        foreach ($ids as $id) {
            AddonIntegrity::writeLock($addons->manifest($id));
            $this->info("locked {$id}");
        }

        return self::SUCCESS;
    }

    private function keygen(): int
    {
        $pair = AddonIntegrity::generateKeyPair();
        $this->line('Public (add to ADDONS_TRUSTED_KEYS as "<key-id>:<public>"): '.$pair['public']);
        $this->warn('Secret (store in your CI secret store as ADDON_SIGNING_KEY; never commit it): '.$pair['secret']);

        return self::SUCCESS;
    }

    private function sign(AddonManager $addons): int
    {
        $secret = (string) env('ADDON_SIGNING_KEY', '');
        $keyId = (string) $this->option('key-id');
        if ($secret === '' || $keyId === '') {
            $this->error('Set ADDON_SIGNING_KEY and pass --key-id');
            return self::FAILURE;
        }

        AddonIntegrity::sign($addons->manifest((string) $this->argument('id')), $keyId, $secret);
        $this->info('signed');

        return self::SUCCESS;
    }

    private function verify(AddonManager $addons): int
    {
        $ids = $this->argument('id') ? [(string) $this->argument('id')] : array_keys($addons->discover(true));
        $failed = false;

        foreach ($ids as $id) {
            $problems = $addons->check($id);
            if ($problems === []) {
                $this->info("{$id}: ok");
            } else {
                $failed = true;
                $this->error("{$id}: ".implode('; ', $problems));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
