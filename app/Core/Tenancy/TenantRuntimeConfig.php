<?php

namespace App\Core\Tenancy;

use App\Models\Setting;
use App\Models\SettingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Applies the current casino's own settings to the request's runtime config.
 *
 * Replaces the old admin pages that wrote APP_NAME and MAIL_* into the shared .env file:
 * with several casinos on one installation that let any casino admin change the name and
 * SMTP account of every casino (and a newline in a value could inject other variables).
 * The values already live per tenant in `settings` / `setting_mails`.
 */
final class TenantRuntimeConfig
{
    public static function apply(): void
    {
        if (! Tenancy::id()) {
            return;
        }

        try {
            $setting = Setting::query()->first(['software_name']);
            if ($setting && filled($setting->software_name)) {
                config(['app.name' => $setting->software_name]);
            }

            $mail = SettingMail::query()->first();
            if ($mail && filled($mail->software_smtp_mail_host)) {
                $type = in_array($mail->software_smtp_type, ['smtp', 'sendmail', 'log'], true) ? $mail->software_smtp_type : 'smtp';
                config([
                    'mail.default' => $type,
                    'mail.mailers.smtp.host' => $mail->software_smtp_mail_host,
                    'mail.mailers.smtp.port' => (int) $mail->software_smtp_mail_port ?: 587,
                    'mail.mailers.smtp.username' => $mail->software_smtp_mail_username,
                    'mail.mailers.smtp.password' => $mail->software_smtp_mail_password,
                    'mail.mailers.smtp.encryption' => $mail->software_smtp_mail_encryption ?: null,
                    'mail.from.address' => $mail->software_smtp_mail_from_address ?: config('mail.from.address'),
                    'mail.from.name' => $mail->software_smtp_mail_from_name ?: config('app.name'),
                ]);
                // Drop mailers resolved for a previous tenant in this process (queue workers, tests).
                Mail::purge($type);
            }
        } catch (\Throwable $e) {
            // Missing tables during install/migration must not take the site down.
            Log::debug('tenant runtime config not applied: '.$e->getMessage());
        }
    }
}
