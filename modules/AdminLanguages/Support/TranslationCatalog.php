<?php

namespace Modules\AdminLanguages\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AdminLanguages\Models\LanguageTranslation;
use Throwable;

class TranslationCatalog
{
    public function fileTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function overridesForLocale(string $locale): array
    {
        try {
            return Cache::rememberForever("translations.overrides.{$locale}", function () use ($locale) {
                return $this->loadOverridesForLocale($locale);
            });
        } catch (Throwable) {
            return $this->loadOverridesForLocale($locale);
        }
    }

    public function mergedForLocale(string $locale): array
    {
        $fallback = $this->fileTranslations((string) config('app.fallback_locale', 'en'));
        $localeLines = $this->fileTranslations($locale);
        $overrides = $this->overridesForLocale($locale);

        $merged = array_replace($fallback, $localeLines, $overrides);
        ksort($merged);

        return $merged;
    }

    public function paginatedForLocale(string $locale, ?string $search = null, int $perPage = 50): LengthAwarePaginator
    {
        $fallback = $this->fileTranslations((string) config('app.fallback_locale', 'en'));
        $localeLines = $this->fileTranslations($locale);
        $overrides = $this->overridesForLocale($locale);

        $keys = array_unique([
            ...array_keys($fallback),
            ...array_keys($localeLines),
            ...array_keys($overrides),
        ]);

        sort($keys);

        $items = collect($keys)->map(function (string $key) use ($fallback, $localeLines, $overrides) {
            return [
                'key' => $key,
                'fallback' => $fallback[$key] ?? $key,
                'value' => $overrides[$key] ?? $localeLines[$key] ?? $fallback[$key] ?? $key,
                'override' => $overrides[$key] ?? null,
                'has_override' => array_key_exists($key, $overrides),
            ];
        });

        if ($search) {
            $needle = mb_strtolower($search);

            $items = $items->filter(function (array $item) use ($needle) {
                return str_contains(mb_strtolower($item['key']), $needle)
                    || str_contains(mb_strtolower($item['fallback']), $needle)
                    || str_contains(mb_strtolower($item['value']), $needle);
            })->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );
    }

    public function saveOverrides(string $locale, array $translations): void
    {
        if (! Schema::hasTable('language_translations')) {
            return;
        }

        $localeFile = $this->fileTranslations($locale);
        $fallback = $this->fileTranslations((string) config('app.fallback_locale', 'en'));

        DB::transaction(function () use ($locale, $translations, $localeFile, $fallback) {
            foreach ($translations as $key => $value) {
                $key = trim((string) $key);

                if ($key === '') {
                    continue;
                }

                $baseValue = $localeFile[$key] ?? $fallback[$key] ?? $key;
                $value = $this->normalizePlaceholders($baseValue, trim((string) $value));

                if ($value === '' || $value === $baseValue) {
                    LanguageTranslation::query()
                        ->where('language_code', $locale)
                        ->where('key', $key)
                        ->delete();

                    continue;
                }

                LanguageTranslation::query()->updateOrCreate(
                    ['language_code' => $locale, 'key' => $key],
                    ['value' => $value, 'is_custom' => true]
                );
            }
        });

        $this->flushLocale($locale);
    }

    public function import(string $locale, array $translations): void
    {
        $this->saveOverrides($locale, $translations);
    }

    public function export(string $locale): array
    {
        return $this->mergedForLocale($locale);
    }

    public function flushLocale(string $locale): void
    {
        try {
            Cache::forget("translations.overrides.{$locale}");
        } catch (Throwable) {
            // Ignore cache store failures so translation edits still work.
        }
    }

    protected function loadOverridesForLocale(string $locale): array
    {
        try {
            if (! Schema::hasTable('language_translations')) {
                return [];
            }

            return LanguageTranslation::query()
                ->where('language_code', $locale)
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function normalizePlaceholders(string $baseValue, string $value): string
    {
        $expected = $this->extractExpectedPlaceholders($baseValue);

        if ($expected === []) {
            return $value;
        }

        $current = [];

        preg_match_all('/(?<!\w):[\p{L}_][\p{L}\p{N}_]*|%(?:\d+\$)?[A-Za-z]/u', $value, $current);

        if (($current[0] ?? []) === []) {
            return $value;
        }

        $index = 0;

        return preg_replace_callback('/(?<!\w):[\p{L}_][\p{L}\p{N}_]*|%(?:\d+\$)?[A-Za-z]/u', function () use ($expected, &$index) {
            $replacement = $expected[$index] ?? null;
            $index++;

            return $replacement ?? '';
        }, $value) ?? $value;
    }

    protected function extractExpectedPlaceholders(string $baseValue): array
    {
        $matches = [];

        preg_match_all('/(?<!\w):[A-Za-z_][A-Za-z0-9_]*|%(?:\d+\$)?[A-Za-z]/u', $baseValue, $matches);

        return $matches[0] ?? [];
    }
}
