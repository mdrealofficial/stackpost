<?php

namespace Modules\AdminBlogs\Support;

use Modules\AdminLanguages\Models\Language;
use Modules\AdminLanguages\Support\GoogleTranslationService;
use Throwable;

class MultilingualContentGenerator
{
    public function __construct(
        protected GoogleTranslationService $translator,
    ) {}

    public function completeFields(array $fieldTranslations, array $fieldLimits = []): array
    {
        $defaultLocale = function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', 'en');

        $languages = Language::query()
            ->active()
            ->where('auto_translate', true)
            ->where('code', '!=', $defaultLocale)
            ->ordered()
            ->get(['code']);

        foreach ($languages as $language) {
            $targetLocale = strtolower((string) $language->code);
            $missing = [];

            foreach ($fieldTranslations as $field => $translations) {
                $source = trim((string) ($translations[$defaultLocale] ?? ''));
                $current = trim((string) ($translations[$targetLocale] ?? ''));

                if ($source === '' || $current !== '') {
                    continue;
                }

                $missing[$field] = $source;
            }

            if ($missing === []) {
                continue;
            }

            $startedAt = microtime(true);

            try {
                $translated = $this->translator->translateArray($missing, $targetLocale);

                foreach ($translated as $field => $value) {
                    $limit = $fieldLimits[$field] ?? 65535;
                    $value = trim((string) $value);

                    if ($value === '') {
                        continue;
                    }

                    $fieldTranslations[$field][$targetLocale] = mb_substr($value, 0, $limit);
                }

                if (function_exists('log_ai_usage')) {
                    log_ai_usage([
                        'provider' => 'google-translate',
                        'capability' => 'translation',
                        'model' => 'google-translate-web',
                        'feature' => 'blogs.multilang.autofill',
                        'status' => 'success',
                        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'metadata' => [
                            'target_locale' => $targetLocale,
                            'fields' => array_keys($missing),
                        ],
                    ]);
                }
            } catch (Throwable $exception) {
                if (function_exists('log_ai_usage')) {
                    log_ai_usage([
                        'provider' => 'google-translate',
                        'capability' => 'translation',
                        'model' => 'google-translate-web',
                        'feature' => 'blogs.multilang.autofill',
                        'status' => 'error',
                        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'error_message' => $exception->getMessage(),
                        'metadata' => [
                            'target_locale' => $targetLocale,
                            'fields' => array_keys($missing),
                        ],
                    ]);
                }
            }
        }

        foreach ($fieldTranslations as $field => $translations) {
            ksort($translations);
            $fieldTranslations[$field] = $translations;
        }

        return $fieldTranslations;
    }
}
