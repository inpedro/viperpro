<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingMail extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'setting_mails';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [

        // smtp
        'software_smtp_type',
        'software_smtp_mail_host',
        'software_smtp_mail_port',
        'software_smtp_mail_username',
        'software_smtp_mail_password',
        'software_smtp_mail_encryption',
        'software_smtp_mail_from_address',
        'software_smtp_mail_from_name',
    ];

    protected $hidden = array('updated_at', 'software_smtp_mail_password');

    /**
     * SMTP password, encrypted at rest with APP_KEY (migration 2026_11_30_000001 encrypted
     * existing rows). Masked in demo mode.
     */
    protected function softwareSmtpMailPassword(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => env('APP_DEMO') ? '*********************'
                : ($value === null || $value === '' ? $value : \Illuminate\Support\Facades\Crypt::decryptString($value)),
            set: fn (?string $value) => $value === null || $value === '' ? $value : \Illuminate\Support\Facades\Crypt::encryptString($value),
        );
    }
}
