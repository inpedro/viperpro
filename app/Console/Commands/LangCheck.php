<?php

namespace App\Console\Commands;

use App\Core\Localization\Locales;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Translation completeness.
 *
 *   php artisan lang:check            report
 *   php artisan lang:check --strict   exit 1 if anything is missing (CI)
 *   php artisan lang:check --add-en   add keys used in code but absent from en.json (value = key)
 */
class LangCheck extends Command
{
    protected $signature = 'lang:check {--strict} {--add-en}';

    protected $description = 'Check that every translation key used in code exists in every installed locale';

    public function handle(): int
    {
        $en = $this->json('en');
        $used = $this->usedKeys();
        $missingInEn = array_values(array_diff($used, array_keys($en)));
        $problems = 0;

        if ($missingInEn !== []) {
            if ($this->option('add-en')) {
                foreach ($missingInEn as $key) {
                    $en[$key] = $key;
                }
                ksort($en);
                file_put_contents(lang_path('en.json'), json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $this->info('Added '.count($missingInEn).' keys to en.json');
            } else {
                $problems += count($missingInEn);
                $this->warn('Used in code but missing from en.json ('.count($missingInEn).'):');
                foreach ($missingInEn as $k) {
                    $this->line("  - {$k}");
                }
            }
        }

        foreach (array_keys(Locales::installed()) as $code) {
            if ($code === 'en') {
                continue;
            }

            $missing = array_values(array_diff(array_keys($en), array_keys($this->json($code))));
            $groups = $this->missingGroupFiles($code);
            $problems += count($missing) + count($groups);

            $this->line(sprintf('%-6s %d/%d strings%s', $code, count($en) - count($missing), count($en), $groups ? ' | missing files: '.implode(', ', $groups) : ''));
            foreach (array_slice($missing, 0, 20) as $k) {
                $this->line("    - {$k}");
            }
        }

        return ($this->option('strict') && $problems > 0) ? self::FAILURE : self::SUCCESS;
    }

    /** @return string[] */
    public function usedKeys(): array
    {
        $keys = [];
        $finder = (new Finder())->files()->in([app_path(), resource_path('views'), resource_path('js'), base_path('addons')])
            ->name(['*.php', '*.vue', '*.js'])->notPath('node_modules')
            ;

        foreach ($finder as $file) {
            $src = $file->getContents();
            // Escaped quotes first, so "affiliate\'s" is read as one key.
            preg_match_all('/(?<![\w>$])(?:trans|__|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)+)\1/u', $src, $m);
            $found = $m[2];
            if (in_array($file->getExtension(), ['vue', 'js'], true)) {
                preg_match_all('/\$t\(\s*([\'"])((?:(?!\1).)+)\1/u', $src, $m2);
                $found = array_merge($found, $m2[2]);
            }
            foreach ($found as $key) {
                $key = stripslashes($key);
                // Group keys (auth.failed, validation.required) live in PHP files, not JSON.
                // Package keys (filament-panels::pages/dashboard.title) are the package's own.
                if (! preg_match('/^[a-z0-9_-]+(::[a-z0-9_\/-]+)?\.[A-Za-z0-9_.-]+$/', $key) && ! str_contains($key, '$') && ! str_contains($key, '::')) {
                    $keys[$key] = true;
                }
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    private function json(string $code): array
    {
        $file = lang_path("{$code}.json");

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    /** @return string[] */
    private function missingGroupFiles(string $code): array
    {
        $missing = [];
        foreach (glob(lang_path('en/*.php')) ?: [] as $file) {
            if (! is_file(lang_path("{$code}/".basename($file)))) {
                $missing[] = basename($file);
            }
        }

        return $missing;
    }
}
