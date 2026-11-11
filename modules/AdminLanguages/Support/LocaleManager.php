<?php

namespace Modules\AdminLanguages\Support;

use Modules\AdminSettings\Support\OptionStore;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Modules\AdminLanguages\Models\Language;
use Modules\AdminUser\Models\User;
use Throwable;

class LocaleManager
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function resolve(?Request $request = null): string
    {
        $supported = $this->supportedCodes();

        $candidates = array_filter([
            $request?->route('locale'),
            $request?->session()->get(config('localization.session_key')),
            $request?->cookie(config('localization.cookie_key')),
            $request?->user()?->locale,
            $this->options->get(config('localization.option_key')),
            $this->defaultCode(),
            config('app.locale'),
            config('app.fallback_locale'),
        ]);

        foreach ($candidates as $candidate) {
            $locale = strtolower((string) $candidate);

            if (in_array($locale, $supported, true)) {
                return $locale;
            }
        }

        return strtolower((string) config('app.fallback_locale', 'en'));
    }

    public function apply(string $locale): void
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    public function supportedLanguages(bool $onlyActive = true): Collection
    {
        try {
            if (! Schema::hasTable('languages')) {
                return collect();
            }

            $query = Language::query()->ordered();

            if ($onlyActive) {
                $query->active();
            }

            return $query->get();
        } catch (Throwable) {
            return collect();
        }
    }

    public function supportedCodes(bool $onlyActive = true): array
    {
        $codes = $this->supportedLanguages($onlyActive)
            ->pluck('code')
            ->map(fn ($code) => strtolower((string) $code))
            ->values()
            ->all();

        if ($codes !== []) {
            return $codes;
        }

        return [strtolower((string) config('app.locale', 'en'))];
    }

    public function defaultCode(): string
    {
        try {
            if (Schema::hasTable('languages')) {
                $code = Language::query()
                    ->where('is_default', true)
                    ->value('code');

                if ($code) {
                    return strtolower((string) $code);
                }
            }
        } catch (Throwable) {
            //
        }

        return strtolower((string) config('app.locale', 'en'));
    }

    public function currentLanguage(): ?Language
    {
        try {
            if (! Schema::hasTable('languages')) {
                return null;
            }

            return Language::query()
                ->where('code', app()->getLocale())
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function persist(Request $request, string $locale): void
    {
        $locale = strtolower($locale);

        $request->session()->put(config('localization.session_key'), $locale);
        Cookie::queue(
            config('localization.cookie_key'),
            $locale,
            (int) config('localization.cookie_minutes', 5256000)
        );

        if ($request->user() instanceof User) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }
    }
}
