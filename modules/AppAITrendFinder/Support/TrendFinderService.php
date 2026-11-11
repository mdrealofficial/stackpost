<?php

namespace Modules\AppAITrendFinder\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use RuntimeException;
use Throwable;

class TrendFinderService
{
    public function __construct(protected OptionStore $options) {}

    public function discover(array $config): array
    {
        $industry = trim((string) ($config['industry'] ?? ''));
        $audience = trim((string) ($config['audience'] ?? ''));
        $goal = trim((string) ($config['goal'] ?? ''));
        $market = trim((string) ($config['market'] ?? ''));
        $season = trim((string) ($config['season'] ?? ''));
        $language = trim((string) ($config['language'] ?? 'en')) ?: 'en';
        $tone = trim((string) ($config['tone'] ?? 'professional')) ?: 'professional';
        $platforms = $this->normalizePlatforms((array) ($config['platforms'] ?? []));
        $total = max(3, min(12, (int) ($config['total'] ?? 8)));

        try {
            $payload = $this->requestJson(
                systemPrompt: 'You are a senior social media strategist. Return valid JSON only.',
                userPrompt: trim(implode("\n\n", [
                    'Find timely, practical trend opportunities for social media content planning.',
                    'This is not live internet browsing unless a live trend API is configured. Treat the output as trend-informed opportunities based on known seasonal behavior, market context, platform behavior, and the user brief.',
                    'Return JSON with keys: summary, signals, calendar.',
                    'signals must contain exactly '.$total.' items. Each item must include: topic, why_now, audience_fit, confidence, urgency, angle, suggested_platforms, hooks, cta, visual_brief, calendar_hint, tags.',
                    'calendar must contain 5 concise scheduling suggestions with keys: timing, theme, reason.',
                    'Use short, specific, business-ready wording.',
                    'Language: '.$language.'.',
                    'Tone: '.$tone.'.',
                    'Industry/niche: '.$industry.'.',
                    'Audience: '.$audience.'.',
                    'Market/country: '.$market.'.',
                    'Goal: '.$goal.'.',
                    'Platforms: '.implode(', ', $platforms).'.',
                    $season !== '' ? 'Season/event/context: '.$season.'.' : null,
                    ...$this->promptPreferenceLines($config),
                ])),
            );

            $signals = collect((array) ($payload['signals'] ?? []))
                ->map(fn ($item) => $this->normalizeSignal((array) $item, $platforms))
                ->filter(fn ($item) => $item['topic'] !== '')
                ->take($total)
                ->values();

            if ($signals->isNotEmpty()) {
                return [
                    'summary' => trim((string) ($payload['summary'] ?? __('Trend opportunities generated from your brief.'))),
                    'signals' => $signals->all(),
                    'calendar' => $this->normalizeCalendar((array) ($payload['calendar'] ?? [])),
                    'source' => 'ai',
                ];
            }
        } catch (Throwable) {
        }

        return $this->fallback($config, $platforms, $total);
    }

    protected function requestJson(string $systemPrompt, string $userPrompt): array
    {
        if ((string) $this->options->get('ai_text_status', '1') !== '1') {
            throw new RuntimeException('AI text generation is disabled.');
        }

        $provider = (string) $this->options->get('ai_content_provider', 'openai');

        if ($provider !== 'openai') {
            throw new RuntimeException('Trend Finder currently supports OpenAI-compatible content generation.');
        }

        $apiKey = trim((string) $this->options->get('ai_openai_api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is missing.');
        }

        $baseUrl = rtrim((string) $this->options->get('ai_openai_url', 'https://api.openai.com/v1'), '/');
        $model = (string) $this->options->get('ai_content_model', $this->options->get('ai_text_generation_model', 'gpt-5.4-mini'));

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(75)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->responseErrorMessage($response));
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI response was not valid JSON.');
        }

        return $decoded;
    }

    protected function fallback(array $config, array $platforms, int $total): array
    {
        $industry = trim((string) ($config['industry'] ?? __('your niche'))) ?: __('your niche');
        $audience = trim((string) ($config['audience'] ?? __('your audience'))) ?: __('your audience');
        $goal = trim((string) ($config['goal'] ?? __('increase engagement'))) ?: __('increase engagement');
        $season = trim((string) ($config['season'] ?? ''));

        $themes = collect([
            __('What changed this week in :industry', ['industry' => $industry]),
            __('Common mistake :audience still makes', ['audience' => $audience]),
            __('Before and after transformation'),
            __('Small workflow upgrade'),
            __('Myth versus reality'),
            __('Behind the scenes proof'),
            __('Seasonal buying trigger'),
            __('Customer objection spotlight'),
            __('Quick checklist'),
            __('Prediction for the next 30 days'),
            __('Tool or template teardown'),
            __('Founder/operator point of view'),
        ]);

        if ($season !== '') {
            $themes->prepend(__('Timely angle for :season', ['season' => $season]));
        }

        $signals = $themes
            ->take($total)
            ->values()
            ->map(fn ($topic, $index) => [
                'topic' => (string) $topic,
                'why_now' => __('This angle connects a current audience tension with a useful, easy-to-act-on takeaway.'),
                'audience_fit' => __('Useful for :audience because it gives them a practical reason to pay attention now.', ['audience' => $audience]),
                'confidence' => max(62, 92 - ($index * 4)),
                'urgency' => $index < 3 ? __('High') : ($index < 7 ? __('Medium') : __('Low')),
                'angle' => __('Turn this into a concise post that helps :audience move toward: :goal.', ['audience' => $audience, 'goal' => $goal]),
                'suggested_platforms' => collect($platforms)->take(3)->values()->all(),
                'hooks' => [
                    __('Most people miss this when working on :goal.', ['goal' => $goal]),
                    __('If you are in :industry, watch this shift.', ['industry' => $industry]),
                    __('A simple way to make this easier this week.'),
                ],
                'cta' => __('Save this idea and turn it into your next post.'),
                'visual_brief' => __('Create a clean, direct visual showing the main problem, the shift, and one practical takeaway.'),
                'calendar_hint' => $index < 3 ? __('Post early this week while the topic feels fresh.') : __('Use this as a supporting post later in the campaign.'),
                'tags' => $this->tagsFor($industry, (string) $topic),
            ])
            ->all();

        return [
            'summary' => __('Trend-informed opportunities generated from your niche, audience, goal, and platform mix.'),
            'signals' => $signals,
            'calendar' => [
                ['timing' => __('Today'), 'theme' => __('Fast reaction'), 'reason' => __('Use the strongest trend while attention is highest.')],
                ['timing' => __('This week'), 'theme' => __('Educational angle'), 'reason' => __('Build trust with a useful explanation.')],
                ['timing' => __('Next 3 days'), 'theme' => __('Engagement prompt'), 'reason' => __('Ask the audience to share context or objections.')],
                ['timing' => __('Next week'), 'theme' => __('Proof post'), 'reason' => __('Follow the trend with evidence, result, or example.')],
                ['timing' => __('Later'), 'theme' => __('Repurpose'), 'reason' => __('Turn the best-performing idea into a campaign or carousel.')],
            ],
            'source' => 'fallback',
        ];
    }

    protected function normalizeSignal(array $item, array $platforms): array
    {
        $topic = trim((string) ($item['topic'] ?? ''));

        return [
            'topic' => $topic,
            'why_now' => trim((string) ($item['why_now'] ?? '')),
            'audience_fit' => trim((string) ($item['audience_fit'] ?? '')),
            'confidence' => max(1, min(100, (int) ($item['confidence'] ?? 70))),
            'urgency' => trim((string) ($item['urgency'] ?? __('Medium'))),
            'angle' => trim((string) ($item['angle'] ?? '')),
            'suggested_platforms' => collect((array) ($item['suggested_platforms'] ?? $platforms))->map(fn ($value) => strtolower(trim((string) $value)))->filter()->unique()->values()->all(),
            'hooks' => collect((array) ($item['hooks'] ?? []))->map(fn ($value) => trim((string) $value))->filter()->take(4)->values()->all(),
            'cta' => trim((string) ($item['cta'] ?? '')),
            'visual_brief' => trim((string) ($item['visual_brief'] ?? '')),
            'calendar_hint' => trim((string) ($item['calendar_hint'] ?? '')),
            'tags' => collect((array) ($item['tags'] ?? []))->map(fn ($value) => trim((string) $value))->filter()->take(8)->values()->all(),
        ];
    }

    protected function normalizeCalendar(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => [
                'timing' => trim((string) data_get($item, 'timing', '')),
                'theme' => trim((string) data_get($item, 'theme', '')),
                'reason' => trim((string) data_get($item, 'reason', '')),
            ])
            ->filter(fn ($item) => $item['timing'] !== '' || $item['theme'] !== '')
            ->take(5)
            ->values()
            ->all();
    }

    protected function normalizePlatforms(array $platforms): array
    {
        $normalized = collect($platforms)
            ->map(fn ($platform) => strtolower(trim((string) $platform)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : ['facebook', 'instagram', 'linkedin'];
    }

    protected function tagsFor(string $industry, string $topic): array
    {
        $raw = strtolower($industry.' '.$topic);
        $raw = preg_replace('/[^a-z0-9\s\-]+/i', ' ', $raw) ?? '';

        return collect(preg_split('/\s+/', $raw) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => Str::length($token) >= 4)
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    protected function promptPreferenceLines(array $config): array
    {
        $lines = [];

        foreach ([
            'brand_name' => 'Brand name',
            'brand_description' => 'Brand context',
            'brand_voice' => 'Brand voice',
            'target_audience' => 'Target audience',
            'brand_keywords' => 'Preferred terms',
            'preferred_cta_style' => 'Preferred CTA style',
            'banned_words' => 'Avoid these words',
            'writing_examples' => 'Writing examples',
        ] as $key => $label) {
            if (filled($config[$key] ?? null)) {
                $lines[] = $label.': '.trim((string) $config[$key]).'.';
            }
        }

        return $lines;
    }

    protected function responseErrorMessage(Response $response): string
    {
        return trim((string) data_get($response->json(), 'error.message', ''))
            ?: trim((string) data_get($response->json(), 'message', ''))
            ?: Str::limit((string) $response->body(), 280)
            ?: 'AI request failed.';
    }
}
