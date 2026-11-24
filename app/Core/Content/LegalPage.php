<?php

namespace App\Core\Content;

use App\Core\Localization\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Operator-managed legal content per casino and language (Markdown, rendered safely).
 * The platform ships no legal text: terms must come from the operator for its jurisdiction.
 */
class LegalPage extends Model
{
    use \App\Core\Tenancy\BelongsToTenant;

    public const SLUGS = [
        'terms'              => 'Terms and conditions',
        'privacy'            => 'Privacy policy',
        'responsible-gaming' => 'Responsible gaming',
        'bonus'              => 'Bonus terms',
        'welcome-bonus'      => 'Welcome bonus',
        'referral'           => 'Referral terms',
        'cookies'            => 'Cookie policy',
        'aml-kyc'            => 'AML and KYC policy',
    ];

    protected $table = 'legal_pages';

    protected $fillable = ['slug', 'locale', 'title', 'body_markdown', 'published'];

    protected $casts = ['published' => 'boolean'];

    /** Raw HTML in the Markdown is stripped and javascript:/data: links are refused. */
    public function html(): string
    {
        return (string) Str::markdown($this->body_markdown, [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 20,
        ]);
    }

    /** Published page in the requested locale, else the tenant default, else any published locale. */
    public static function resolve(string $slug, string $locale): ?self
    {
        $candidates = static::query()->where('slug', $slug)->where('published', true)->get()->keyBy('locale');

        return $candidates[$locale] ?? $candidates[Locales::tenantDefault()] ?? $candidates->first();
    }
}
