<?php

namespace App\Concerns;

trait HasLocalizedAttributes
{
    public function localized(string $attribute, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $translations = $this->getAttribute($attribute.'_translations');
        $translations = is_array($translations) ? $translations : [];
        $fallbackLocale = function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', config('app.locale', 'en'));

        $value = $translations[$locale]
            ?? $translations[$fallbackLocale]
            ?? $this->getAttribute($attribute)
            ?? '';

        return trim((string) $value);
    }

    protected static function sanitizeTranslations(array $translations, int $max = 65535): array
    {
        $sanitized = [];

        foreach ($translations as $locale => $value) {
            $locale = strtolower(trim((string) $locale));

            if ($locale === '') {
                continue;
            }

            $text = trim((string) $value);

            if ($text === '') {
                continue;
            }

            $sanitized[$locale] = mb_substr($text, 0, $max);
        }

        ksort($sanitized);

        return $sanitized;
    }
}
