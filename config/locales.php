<?php

/*
|--------------------------------------------------------------------------
| Installed locales (docs/LOCALIZATION.md)
|--------------------------------------------------------------------------
| A locale is "installed" when it is listed here AND lang/{code}.json exists.
| Each tenant chooses which installed locales it offers (settings.enabled_locales)
| and its default (settings.default_locale). English is the platform default.
|
| intl: ICU locale used for number/currency/date formatting.
| dir:  text direction; rtl locales get <html dir="rtl">.
*/

return [
    'default' => 'en',

    'installed' => [
        'en'    => ['name' => 'English',              'native' => 'English',              'intl' => 'en',    'dir' => 'ltr'],
        'sq'    => ['name' => 'Albanian',             'native' => 'Shqip',                'intl' => 'sq_AL', 'dir' => 'ltr'],
        'es'    => ['name' => 'Spanish',              'native' => 'Español',              'intl' => 'es',    'dir' => 'ltr'],
        'pt_BR' => ['name' => 'Portuguese (Brazil)',  'native' => 'Português (Brasil)',   'intl' => 'pt_BR', 'dir' => 'ltr'],
    ],
];
