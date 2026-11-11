<?php

namespace Modules\AppAITrendFinder\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAITrendFinder\Support\TrendFinderService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Throwable;

#[Title('AI Trend Finder')]
class TrendFinderIndex extends Component
{
    use AIStudioAccess;

    public string $industry = '';
    public string $audience = '';
    public string $goal = '';
    public string $market = '';
    public string $season = '';
    public string $language = 'en';
    public string $tone = 'professional';
    public int $total = 8;
    public array $platforms = ['facebook', 'instagram', 'linkedin'];
    public array $result = [];

    public function mount(): void
    {
        abort_unless($this->trendFinderAvailable(), 404);

        $this->language = (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en');
        $this->tone = (string) $this->aiStudioSetting('default_tone', $this->tone);
        $this->platforms = collect((array) $this->aiStudioSetting('default_caption_platforms', $this->platforms))
            ->map(fn ($platform) => strtolower((string) $platform))
            ->filter()
            ->take(5)
            ->values()
            ->all() ?: $this->platforms;
        $this->market = $this->normalizeCountryMarket((string) (auth()->user()?->country ?: ''));
        $this->restoreLatestHistory();
    }

    public function selectMarket(string $market): void
    {
        $this->market = $this->normalizeCountryMarket($market);
    }

    public function selectAudience(string $audience): void
    {
        $this->audience = trim($audience);
    }

    public function togglePlatform(string $platform): void
    {
        $platform = strtolower(trim($platform));
        $allowed = collect($this->platformOptions())->pluck('value')->map(fn ($value) => (string) $value)->all();

        if ($platform === '' || ! in_array($platform, $allowed, true)) {
            return;
        }

        $selected = collect($this->platforms)
            ->map(fn ($value) => strtolower((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($selected->contains($platform)) {
            $this->platforms = $selected->reject(fn ($value) => $value === $platform)->values()->all();

            return;
        }

        $maxPlatforms = count($allowed);

        if ($selected->count() >= $maxPlatforms) {
            $this->addError('platforms', __('You can select up to :count platforms.', ['count' => $maxPlatforms]));

            return;
        }

        $this->resetErrorBag('platforms');
        $this->platforms = $selected->push($platform)->values()->all();
    }

    public function discover(TrendFinderService $finder): void
    {
        abort_unless($this->trendFinderAvailable(), 404);

        $validated = $this->validate($this->rules());

        try {
            $planOwner = $this->aiStudioPlanOwner();

            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_find_trends');
            }

            $payload = [
                ...$validated,
                ...$this->aiStudioWorkspacePromptConfig(),
            ];

            $this->result = $finder->discover($payload);

            app(\Modules\AppAIStudio\Support\AiContentStudioService::class)->recordPromptHistory(
                auth()->user(),
                'trend_finder',
                $this->briefText($validated),
                $validated,
                $this->result,
                [
                    'title' => __('Trend Finder - :industry', ['industry' => $validated['industry']]),
                    'language' => $validated['language'],
                    'tone' => $validated['tone'],
                ],
            );

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_find_trends', [
                    'feature' => 'ai-studio.trend-finder',
                    'metadata' => [
                        'industry' => $validated['industry'],
                        'market' => $validated['market'] ?? '',
                        'platforms' => $validated['platforms'],
                        'source' => $this->result['source'] ?? null,
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('industry', $exception->getMessage());
        }
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->industry = (string) data_get($history->input_payload, 'industry', $this->industry);
        $this->audience = (string) data_get($history->input_payload, 'audience', $this->audience);
        $this->goal = (string) data_get($history->input_payload, 'goal', $this->goal);
        $this->market = (string) data_get($history->input_payload, 'market', $this->market);
        $this->season = (string) data_get($history->input_payload, 'season', $this->season);
        $this->language = (string) data_get($history->input_payload, 'language', $this->language);
        $this->tone = (string) data_get($history->input_payload, 'tone', $this->tone);
        $this->total = (int) data_get($history->input_payload, 'total', $this->total);
        $this->platforms = collect((array) data_get($history->input_payload, 'platforms', $this->platforms))->map(fn ($platform) => (string) $platform)->all();
        $this->result = is_array($history->output_payload) ? $history->output_payload : [];
    }

    public function render(): View
    {
        abort_unless($this->trendFinderAvailable(), 404);

        return view('appaitrendfinder::index', [
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_find_trends'),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
            'audienceOptions' => $this->audienceOptions(),
            'countryOptions' => $this->countryOptions(),
            'platformOptions' => $this->platformOptions(),
            'toneOptions' => $this->toneOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Trend Finder'),
            'showLoadingBackdrop' => false,
        ]);
    }

    protected function rules(): array
    {
        $languageCodes = collect(world_languages())->pluck('code')->map(fn ($code) => (string) $code)->all();
        $maxPlatforms = max(1, count($this->platformOptions()));

        return [
            'industry' => ['required', 'string', 'max:160'],
            'audience' => ['required', 'string', 'max:500'],
            'goal' => ['required', 'string', 'max:700'],
            'market' => ['nullable', 'string', 'max:120'],
            'season' => ['nullable', 'string', 'max:240'],
            'language' => ['required', 'string', 'max:12', 'in:'.implode(',', $languageCodes)],
            'tone' => ['required', 'string', 'max:40'],
            'total' => ['required', 'integer', 'min:3', 'max:12'],
            'platforms' => ['required', 'array', 'min:1', 'max:'.$maxPlatforms],
            'platforms.*' => ['string', 'max:40'],
        ];
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'trend_finder');
    }

    protected function trendFinderAvailable(): bool
    {
        return TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'ai_trend_finder')
            && $this->aiStudioFeatureEnabled('ai_studio_trend_finder');
    }

    protected function briefText(array $payload): string
    {
        return trim(implode("\n\n", array_filter([
            'Industry: '.$payload['industry'],
            'Audience: '.$payload['audience'],
            'Goal: '.$payload['goal'],
            'Market: '.($payload['market'] ?? ''),
            'Context: '.($payload['season'] ?? ''),
            'Platforms: '.implode(', ', (array) ($payload['platforms'] ?? [])),
        ])));
    }

    protected function restoreLatestHistory(): void
    {
        $history = $this->promptHistoryQuery()->latest('id')->first();

        if ($history) {
            $this->loadPromptHistory((int) $history->id);
        }
    }

    protected function platformOptions(): array
    {
        return [
            ['value' => 'facebook', 'label' => 'Facebook', 'icon' => 'fa-brands fa-facebook-f'],
            ['value' => 'instagram', 'label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
            ['value' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin-in'],
            ['value' => 'x', 'label' => 'X', 'icon' => 'fa-brands fa-x-twitter'],
            ['value' => 'tiktok', 'label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok'],
            ['value' => 'threads', 'label' => 'Threads', 'icon' => 'fa-brands fa-threads'],
            ['value' => 'youtube', 'label' => 'YouTube', 'icon' => 'fa-brands fa-youtube'],
        ];
    }

    protected function audienceOptions(): array
    {
        return [
            ['value' => 'Agencies and social media managers', 'label' => __('Agencies and social media managers'), 'description' => __('Teams managing content for clients'), 'icon' => 'fa-light fa-users-gear'],
            ['value' => 'Creators and influencers', 'label' => __('Creators and influencers'), 'description' => __('Personal brands, creators, and publishers'), 'icon' => 'fa-light fa-user'],
            ['value' => 'Local businesses', 'label' => __('Local businesses'), 'description' => __('Shops, restaurants, salons, clinics, and services'), 'icon' => 'fa-light fa-store'],
            ['value' => 'Ecommerce brands', 'label' => __('Ecommerce brands'), 'description' => __('Online stores, DTC brands, and product sellers'), 'icon' => 'fa-light fa-cart-shopping'],
            ['value' => 'SaaS companies', 'label' => __('SaaS companies'), 'description' => __('Software teams, startups, and product-led businesses'), 'icon' => 'fa-light fa-browser'],
            ['value' => 'Coaches and consultants', 'label' => __('Coaches and consultants'), 'description' => __('Experts selling services, courses, or programs'), 'icon' => 'fa-light fa-user-tie'],
            ['value' => 'Real estate professionals', 'label' => __('Real estate professionals'), 'description' => __('Agents, brokers, property marketers'), 'icon' => 'fa-light fa-building'],
            ['value' => 'Fitness and wellness brands', 'label' => __('Fitness and wellness brands'), 'description' => __('Gyms, trainers, spas, and health services'), 'icon' => 'fa-light fa-heart'],
            ['value' => 'Education and training providers', 'label' => __('Education and training providers'), 'description' => __('Schools, academies, bootcamps, and course sellers'), 'icon' => 'fa-light fa-user-graduate'],
            ['value' => 'B2B decision makers', 'label' => __('B2B decision makers'), 'description' => __('Founders, managers, buyers, and operators'), 'icon' => 'fa-light fa-briefcase'],
        ];
    }

    protected function countryOptions(): array
    {
        $countries = [];

        if (class_exists(\Symfony\Component\Intl\Countries::class)) {
            $countries = \Symfony\Component\Intl\Countries::getNames(app()->getLocale() ?: 'en');
        }

        if ($countries === [] && function_exists('list_countries')) {
            $codes = [
                'US', 'GB', 'CA', 'AU', 'BR', 'VN', 'TH', 'ID', 'MY', 'SG', 'PH', 'IN',
                'JP', 'KR', 'CN', 'DE', 'FR', 'ES', 'IT', 'NL', 'AE', 'SA', 'MX',
            ];

            foreach ($codes as $code) {
                $name = (string) list_countries($code);
                $countries[$code] = $name !== '' ? $name : $code;
            }
        }

        if ($countries === []) {
            $countries = $this->fallbackCountries();
        }

        $preferred = collect(['US', 'BR', 'VN', 'GB', 'CA', 'AU', 'SG'])
            ->filter(fn ($code) => isset($countries[$code]))
            ->map(fn ($code) => [
                'value' => (string) $countries[$code],
                'label' => (string) $countries[$code],
                'description' => strtoupper((string) $code),
                'icon' => 'fa-light fa-earth-americas',
            ]);

        $remaining = collect($countries)
            ->except($preferred->pluck('description')->all())
            ->map(fn ($name, $code) => [
                'value' => (string) $name,
                'label' => (string) $name,
                'description' => strtoupper((string) $code),
                'icon' => 'fa-light fa-globe',
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $preferred->merge($remaining)->values()->all();
    }

    protected function fallbackCountries(): array
    {
        return [
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BO' => 'Bolivia',
            'BR' => 'Brazil',
            'BG' => 'Bulgaria',
            'KH' => 'Cambodia',
            'CA' => 'Canada',
            'CL' => 'Chile',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'HR' => 'Croatia',
            'CZ' => 'Czechia',
            'DK' => 'Denmark',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'FI' => 'Finland',
            'FR' => 'France',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GR' => 'Greece',
            'GT' => 'Guatemala',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KR' => 'South Korea',
            'KW' => 'Kuwait',
            'LA' => 'Laos',
            'MY' => 'Malaysia',
            'MX' => 'Mexico',
            'MA' => 'Morocco',
            'MM' => 'Myanmar',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NG' => 'Nigeria',
            'NO' => 'Norway',
            'PK' => 'Pakistan',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'QA' => 'Qatar',
            'RO' => 'Romania',
            'SA' => 'Saudi Arabia',
            'RS' => 'Serbia',
            'SG' => 'Singapore',
            'ZA' => 'South Africa',
            'ES' => 'Spain',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'TW' => 'Taiwan',
            'TH' => 'Thailand',
            'TR' => 'Turkey',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
        ];
    }

    protected function normalizeCountryMarket(string $market): string
    {
        $market = trim($market);

        if ($market === '') {
            return '';
        }

        $upper = strtoupper($market);

        if (class_exists(\Symfony\Component\Intl\Countries::class) && \Symfony\Component\Intl\Countries::exists($upper)) {
            return \Symfony\Component\Intl\Countries::getName($upper, app()->getLocale() ?: 'en');
        }

        if (function_exists('list_countries') && strlen($upper) === 2) {
            $name = (string) list_countries($upper);

            if ($name !== '') {
                return $name;
            }
        }

        return $market;
    }

    protected function toneOptions(): array
    {
        return [
            ['value' => 'professional', 'label' => __('Professional')],
            ['value' => 'friendly', 'label' => __('Friendly')],
            ['value' => 'bold', 'label' => __('Bold')],
            ['value' => 'educational', 'label' => __('Educational')],
            ['value' => 'sales', 'label' => __('Sales')],
            ['value' => 'casual', 'label' => __('Casual')],
        ];
    }
}
