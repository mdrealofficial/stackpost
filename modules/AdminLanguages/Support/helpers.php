<?php

use Illuminate\Support\Str;
use Modules\AdminLanguages\Support\LocaleManager;

if (! function_exists('locale_manager')) {
    function locale_manager(): LocaleManager
    {
        return app(LocaleManager::class);
    }
}

if (! function_exists('available_languages')) {
    function available_languages(bool $onlyActive = true)
    {
        return locale_manager()->supportedLanguages($onlyActive);
    }
}

if (! function_exists('current_language')) {
    function current_language()
    {
        return locale_manager()->currentLanguage();
    }
}

if (! function_exists('current_locale_direction')) {
    function current_locale_direction(): string
    {
        $direction = strtolower((string) data_get(current_language(), 'direction', config('localization.default_direction', 'ltr')));

        return in_array($direction, ['ltr', 'rtl'], true) ? $direction : 'ltr';
    }
}

if (! function_exists('language_flag_class')) {
    function language_flag_class($languageOrCode): string
    {
        $code = '';
        $icon = '';

        if (is_object($languageOrCode)) {
            $code = strtolower((string) data_get($languageOrCode, 'code', ''));
            $icon = strtolower((string) data_get($languageOrCode, 'icon', ''));
        } else {
            $code = strtolower((string) $languageOrCode);
        }

        if ($icon !== '') {
            $icon = Str::replace(['flag-icon-', 'fi fi-', 'fi-'], '', $icon);
            $icon = trim($icon);

            if ($icon !== '') {
                return 'flag-icon flag-icon-'.Str::lower($icon);
            }
        }

        $flag = match ($code) {
            'en' => 'us',
            'vi' => 'vn',
            'zh', 'zh-cn' => 'cn',
            'zh-tw' => 'tw',
            'ja' => 'jp',
            'ko' => 'kr',
            'ar' => 'ae',
            'pt-br' => 'br',
            'pt' => 'pt',
            'hi' => 'in',
            'uk' => 'ua',
            default => Str::of($code)->before('-')->lower()->value(),
        };

        return 'flag-icon flag-icon-'.$flag;
    }
}
