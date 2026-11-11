<?php

namespace Modules\AppAICompetitorWatch\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAICompetitorWatch\Support\CompetitorWatchService;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Throwable;

#[Title('AI Competitor Watch')]
class CompetitorWatchIndex extends Component
{
    use AIStudioAccess;

    public string $brand = '';
    public string $industry = '';
    public string $audience = '';
    public string $market = '';
    public string $goal = '';
    public string $competitors = '';
    public string $language = 'en';
    public string $tone = 'professional';
    public array $platforms = ['facebook', 'instagram', 'linkedin'];
    public array $result = [];

    public function mount(): void
    {
        abort_unless($this->competitorWatchAvailable(), 404);

        $this->language = (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en');
        $this->tone = (string) $this->aiStudioSetting('default_tone', $this->tone);
        $this->platforms = collect((array) $this->aiStudioSetting('default_caption_platforms', $this->platforms))
            ->map(fn ($platform) => strtolower((string) $platform))
            ->filter()
            ->take(5)
            ->values()
            ->all() ?: $this->platforms;

        $workspacePromptConfig = $this->aiStudioWorkspacePromptConfig();
        $this->brand = $this->stringValue(data_get($workspacePromptConfig, 'brand_name', ''));
        $this->audience = $this->stringValue(data_get($workspacePromptConfig, 'target_audience', ''));
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

    public function analyze(CompetitorWatchService $watch): void
    {
        abort_unless($this->competitorWatchAvailable(), 404);

        $validated = $this->validate($this->rules());

        try {
            $planOwner = $this->aiStudioPlanOwner();

            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_competitor_watch');
            }

            $payload = [
                ...$validated,
                ...$this->aiStudioWorkspacePromptConfig(),
            ];

            $this->result = $watch->analyze($payload);

            app(AiContentStudioService::class)->recordPromptHistory(
                auth()->user(),
                'competitor_watch',
                $this->briefText($validated),
                $validated,
                $this->result,
                [
                    'title' => __('Competitor Watch - :brand', ['brand' => $validated['brand'] ?: $validated['industry']]),
                    'language' => $validated['language'],
                    'tone' => $validated['tone'],
                ],
            );

            if (($this->result['source'] ?? null) === 'ai' && function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_competitor_watch', [
                    'feature' => 'ai.competitor-watch',
                    'metadata' => [
                        'brand' => $validated['brand'],
                        'industry' => $validated['industry'],
                        'platforms' => $validated['platforms'],
                        'source' => $this->result['source'] ?? null,
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('competitors', $exception->getMessage());
        }
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->brand = (string) data_get($history->input_payload, 'brand', $this->brand);
        $this->industry = (string) data_get($history->input_payload, 'industry', $this->industry);
        $this->audience = (string) data_get($history->input_payload, 'audience', $this->audience);
        $this->market = (string) data_get($history->input_payload, 'market', $this->market);
        $this->goal = (string) data_get($history->input_payload, 'goal', $this->goal);
        $this->competitors = (string) data_get($history->input_payload, 'competitors', $this->competitors);
        $this->language = (string) data_get($history->input_payload, 'language', $this->language);
        $this->tone = (string) data_get($history->input_payload, 'tone', $this->tone);
        $this->platforms = collect((array) data_get($history->input_payload, 'platforms', $this->platforms))->map(fn ($platform) => (string) $platform)->all();
        $this->result = is_array($history->output_payload) ? $history->output_payload : [];
    }

    public function render(): View
    {
        abort_unless($this->competitorWatchAvailable(), 404);

        return view('appaicompetitorwatch::index', [
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_competitor_watch'),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
            'audienceOptions' => $this->audienceOptions(),
            'countryOptions' => $this->countryOptions(),
            'platformOptions' => $this->platformOptions(),
            'toneOptions' => $this->toneOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Competitor Watch'),
            'showLoadingBackdrop' => false,
        ]);
    }

    protected function rules(): array
    {
        $languageCodes = collect(world_languages())->pluck('code')->map(fn ($code) => (string) $code)->all();
        $maxPlatforms = max(1, count($this->platformOptions()));

        return [
            'brand' => ['nullable', 'string', 'max:160'],
            'industry' => ['required', 'string', 'max:160'],
            'audience' => ['required', 'string', 'max:500'],
            'market' => ['nullable', 'string', 'max:120'],
            'goal' => ['required', 'string', 'max:700'],
            'competitors' => ['required', 'string', 'max:1500'],
            'language' => ['required', 'string', 'max:12', 'in:'.implode(',', $languageCodes)],
            'tone' => ['required', 'string', 'max:40'],
            'platforms' => ['required', 'array', 'min:1', 'max:'.$maxPlatforms],
            'platforms.*' => ['string', 'max:40'],
        ];
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'competitor_watch');
    }

    protected function competitorWatchAvailable(): bool
    {
        return TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'ai_competitor_watch')
            && $this->aiStudioFeatureEnabled('ai_studio_competitor_watch');
    }

    protected function briefText(array $payload): string
    {
        return trim(implode("\n\n", array_filter([
            'Brand: '.($payload['brand'] ?? ''),
            'Industry: '.$payload['industry'],
            'Audience: '.$payload['audience'],
            'Goal: '.$payload['goal'],
            'Market: '.($payload['market'] ?? ''),
            'Competitors: '.$payload['competitors'],
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
            foreach (array_keys($this->fallbackCountries()) as $code) {
                $name = (string) list_countries($code);
                $countries[$code] = $name !== '' ? $name : $this->fallbackCountries()[$code];
            }
        }

        if ($countries === []) {
            $countries = $this->fallbackCountries();
        }

        $preferredCodes = ['US', 'BR', 'VN', 'GB', 'CA', 'AU', 'SG'];
        $preferred = collect($preferredCodes)
            ->filter(fn ($code) => isset($countries[$code]))
            ->map(fn ($code) => [
                'value' => (string) $countries[$code],
                'label' => (string) $countries[$code],
                'description' => strtoupper((string) $code),
                'icon' => 'fa-light fa-earth-americas',
            ]);

        $remaining = collect($countries)
            ->except($preferredCodes)
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
            'AU' => 'Australia',
            'BR' => 'Brazil',
            'CA' => 'Canada',
            'CN' => 'China',
            'FR' => 'France',
            'DE' => 'Germany',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'MY' => 'Malaysia',
            'MX' => 'Mexico',
            'NL' => 'Netherlands',
            'PH' => 'Philippines',
            'SG' => 'Singapore',
            'KR' => 'South Korea',
            'ES' => 'Spain',
            'TH' => 'Thailand',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
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

    protected function stringValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            return collect($value)
                ->flatten()
                ->map(fn ($item) => is_scalar($item) ? trim((string) $item) : '')
                ->filter()
                ->implode(', ');
        }

        return '';
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
