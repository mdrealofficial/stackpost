<?php

namespace Modules\AdminLanguages\Support;

use Modules\AdminLanguages\Models\Language;

class LanguageMaintenanceService
{
    public function __construct(
        protected TranslationCatalog $catalog,
        protected TranslationScanner $scanner,
        protected GoogleTranslationService $translator,
    ) {}

    public function createLanguage(Language $language): void
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $source = $this->scanner->syncFallbackFile($fallback);

        if ($language->code === $fallback) {
            return;
        }

        $translated = $language->auto_translate
            ? $this->translator->translateArray($source, $language->code)
            : $source;

        $this->scanner->writeJson(lang_path("{$language->code}.json"), $translated);
    }

    public function updateMissing(Language $language): int
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $source = $this->scanner->syncFallbackFile($fallback);

        if ($language->code === $fallback) {
            return 0;
        }

        $localePath = lang_path("{$language->code}.json");
        $existing = $this->scanner->readJson($localePath);
        $overrides = $this->catalog->overridesForLocale($language->code);

        $missing = [];

        foreach ($source as $key => $value) {
            if (array_key_exists($key, $existing) || array_key_exists($key, $overrides)) {
                continue;
            }

            $missing[$key] = $value;
        }

        if ($missing === []) {
            return 0;
        }

        $translated = $language->auto_translate
            ? $this->translator->translateArray($missing, $language->code)
            : $missing;

        $merged = array_replace($existing, $translated);
        ksort($merged);

        $this->scanner->writeJson($localePath, $merged);

        return count($missing);
    }
}
