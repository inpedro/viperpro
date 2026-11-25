<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-casino SMTP passwords were stored in plain text; they are now encrypted with APP_KEY
 * (SettingMail cast). Values that already decrypt are left alone, so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_mails', fn ($t) => $t->text('software_smtp_mail_password')->nullable()->change());

        foreach (DB::table('setting_mails')->whereNotNull('software_smtp_mail_password')->get(['id', 'software_smtp_mail_password']) as $row) {
            if ($row->software_smtp_mail_password === '' || $this->isEncrypted($row->software_smtp_mail_password)) {
                continue;
            }
            DB::table('setting_mails')->where('id', $row->id)->update(['software_smtp_mail_password' => Crypt::encryptString($row->software_smtp_mail_password)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('setting_mails')->whereNotNull('software_smtp_mail_password')->get(['id', 'software_smtp_mail_password']) as $row) {
            if ($this->isEncrypted($row->software_smtp_mail_password)) {
                DB::table('setting_mails')->where('id', $row->id)->update(['software_smtp_mail_password' => Crypt::decryptString($row->software_smtp_mail_password)]);
            }
        }

        Schema::table('setting_mails', fn ($t) => $t->string('software_smtp_mail_password', 100)->nullable()->change());
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
