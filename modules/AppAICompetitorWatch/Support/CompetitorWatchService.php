<?php

namespace Modules\AppAICompetitorWatch\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use RuntimeException;
use Throwable;

class CompetitorWatchService
{
    public function __construct(protected OptionStore $options) {}

    public function analyze(array $config): array
    {
        $competitors = $this->normalizeCompetitors((string) ($config['competitors'] ?? ''));
        $industry = $this->stringValue($config['industry'] ?? '');
        $brand = $this->stringValue($config['brand'] ?? '');
        $audience = $this->stringValue($config['audience'] ?? '');
        $goal = $this->stringValue($config['goal'] ?? '');
        $market = $this->stringValue($config['market'] ?? '');
        $language = $this->stringValue($config['language'] ?? 'en') ?: 'en';
        $tone = $this->stringValue($config['tone'] ?? 'professional') ?: 'professional';
        $platforms = $this->normalizePlatforms((array) ($config['platforms'] ?? []));
        $provider = (string) $this->options->get('ai_content_provider', 'openai');
        $model = (string) $this->options->get('ai_content_model', $this->options->get('ai_text_generation_model', 'gpt-5.4-mini'));
        $startedAt = microtime(true);

        try {
            $payload = $this->requestJson(
                systemPrompt: 'You are a senior competitive social media strategist. Return valid JSON only.',
                userPrompt: trim(implode("\n\n", [
                    'Analyze these competitors and produce a practical competitor watch report for social media strategy.',
                    'Do not claim live browsing. Infer from the provided competitor names, URLs, social handles, market, industry, platform behavior, and known competitive positioning patterns.',
                    'Return JSON with keys: summary, competitor_profiles, pattern_map, gaps, opportunities, action_plan.',
                    'competitor_profiles must include one item per competitor with keys: name, likely_positioning, content_themes, strengths, weaknesses, watch_items.',
                    'pattern_map must include keys: common_hooks, common_formats, offer_angles, visual_patterns.',
                    'gaps must contain 4-6 content or positioning gaps.',
                    'opportunities must contain 6-10 items with keys: title, why_it_matters, recommended_platforms, hook, content_brief, cta, confidence.',
                    'action_plan must contain 5 concise next actions with keys: step, action, expected_outcome.',
                    'Language: '.$language.'.',
                    'Tone: '.$tone.'.',
                    'Brand/product: '.$brand.'.',
                    'Industry/niche: '.$industry.'.',
                    'Audience: '.$audience.'.',
                    'Market/country: '.$market.'.',
                    'Goal: '.$goal.'.',
                    'Platforms: '.implode(', ', $platforms).'.',
                    'Competitors: '.implode('; ', $competitors).'.',
                    ...$this->promptPreferenceLines($config),
                ])),
            );

            $profiles = collect((array) ($payload['competitor_profiles'] ?? []))
                ->map(fn ($item) => $this->normalizeProfile((array) $item))
                ->filter(fn ($item) => $item['name'] !== '')
                ->values();

            $opportunities = collect((array) ($payload['opportunities'] ?? []))
                ->map(fn ($item) => $this->normalizeOpportunity((array) $item, $platforms))
                ->filter(fn ($item) => $item['title'] !== '')
                ->take(10)
                ->values();

            if ($profiles->isNotEmpty() || $opportunities->isNotEmpty()) {
                $result = [
                    'summary' => $this->stringValue($payload['summary'] ?? __('Competitor watch report generated from your brief.')),
                    'competitor_profiles' => $profiles->all(),
                    'pattern_map' => $this->normalizePatternMap((array) ($payload['pattern_map'] ?? [])),
                    'gaps' => collect((array) ($payload['gaps'] ?? []))->map(fn ($item) => $this->stringValue($item))->filter()->take(6)->values()->all(),
                    'opportunities' => $opportunities->all(),
                    'action_plan' => $this->normalizeActionPlan((array) ($payload['action_plan'] ?? [])),
                    'source' => 'ai',
                ];

                $this->logUsage($provider, $model, 'success', $startedAt);

                return $result;
            }
        } catch (Throwable $exception) {
            logger()->warning('AI Competitor Watch fell back to sample output.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            $this->logUsage($provider, $model, 'error', $startedAt, $exception->getMessage());

            return $this->fallback($config, $competitors, $platforms, $exception->getMessage());
        }

        return $this->fallback($config, $competitors, $platforms, 'AI response did not contain enough usable competitor data.');
    }

    protected function requestJson(string $systemPrompt, string $userPrompt): array
    {
        if ((string) $this->options->get('ai_text_status', '1') !== '1') {
            throw new RuntimeException('AI text generation is disabled.');
        }

        $provider = (string) $this->options->get('ai_content_provider', 'openai');

        if ($provider !== 'openai') {
            throw new RuntimeException('Competitor Watch currently supports OpenAI-compatible content generation.');
        }

        $apiKey = trim((string) $this->options->get('ai_openai_api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is missing.');
        }

        $baseUrl = rtrim((string) $this->options->get('ai_openai_url', 'https://api.openai.com/v1'), '/');
        $model = (string) $this->options->get('ai_content_model', $this->options->get('ai_text_generation_model', 'gpt-5.4-mini'));

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(150)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_completion_tokens' => 6000,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->responseErrorMessage($response));
        }

        $payload = $response->json();
        $content = trim((string) data_get($payload, 'choices.0.message.content', ''));
        $decoded = $this->decodeJsonContent($content);

        if (! is_array($decoded)) {
            logger()->warning('AI Competitor Watch received invalid JSON.', [
                'finish_reason' => data_get($payload, 'choices.0.finish_reason'),
                'content_preview' => Str::limit($content, 700),
            ]);

            $finishReason = (string) data_get($payload, 'choices.0.finish_reason', '');

            if ($finishReason === 'length') {
                throw new RuntimeException('AI response was truncated before valid JSON could be completed.');
            }

            throw new RuntimeException('AI response was not valid JSON.');
        }

        return $decoded;
    }

    protected function decodeJsonContent(string $content): mixed
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($content, $start, $end - $start + 1), true);
    }

    protected function fallback(array $config, array $competitors, array $platforms, string $reason = ''): array
    {
        $industry = $this->stringValue($config['industry'] ?? __('your niche')) ?: __('your niche');
        $brand = $this->stringValue($config['brand'] ?? __('your brand')) ?: __('your brand');
        $audience = $this->stringValue($config['audience'] ?? __('your audience')) ?: __('your audience');
        $goal = $this->stringValue($config['goal'] ?? __('grow attention and conversions')) ?: __('grow attention and conversions');
        $competitors = $competitors !== [] ? $competitors : [__('Competitor A')];
        $competitorNames = collect($competitors)->map(fn ($competitor) => $this->competitorName($competitor))->values();

        $profileThemes = [
            [
                'positioning' => __('Category leader style: broad promise, simple value claim, and mass-market proof.'),
                'themes' => [__('Brand trust'), __('Lifestyle association'), __('Campaign-led announcements')],
                'strengths' => [__('High recognition'), __('Simple message recall'), __('Strong visual consistency')],
                'weaknesses' => [__('Generic for a niche buyer'), __('Hard to copy without similar awareness'), __('Less useful for direct-response learning')],
                'watch' => [__('Watch campaign mechanics'), __('Watch offer framing'), __('Watch social proof format')],
            ],
            [
                'positioning' => __('Specialist style: narrower audience, clearer pain point, and more educational depth.'),
                'themes' => [__('Problem education'), __('Feature comparison'), __('Customer objection handling')],
                'strengths' => [__('Specific buyer language'), __('Clear product connection'), __('Repeatable content series')],
                'weaknesses' => [__('Can become repetitive'), __('May underuse emotional hooks'), __('May need more proof')],
                'watch' => [__('Watch repeated hooks'), __('Watch comments and objections'), __('Watch high-save formats')],
            ],
            [
                'positioning' => __('Community style: conversation-first content, relatable angles, and lightweight CTAs.'),
                'themes' => [__('Behind the scenes'), __('Audience questions'), __('Opinion-led posts')],
                'strengths' => [__('Feels human'), __('Easy to engage with'), __('Good for discovery channels')],
                'weaknesses' => [__('May not convert directly'), __('May lack offer clarity'), __('Can dilute positioning')],
                'watch' => [__('Watch engagement prompts'), __('Watch sentiment in comments'), __('Watch recurring community topics')],
            ],
        ];

        $profiles = $competitorNames->map(function ($name, $index) use ($profileThemes, $industry) {
            $theme = $profileThemes[$index % count($profileThemes)];

            return [
                'name' => $name,
                'likely_positioning' => __(':positioning In :industry, compare this against your own proof, offer clarity, and audience specificity.', [
                    'positioning' => $theme['positioning'],
                    'industry' => $industry,
                ]),
                'content_themes' => $theme['themes'],
                'strengths' => $theme['strengths'],
                'weaknesses' => $theme['weaknesses'],
                'watch_items' => $theme['watch'],
            ];
        })->values()->all();

        return [
            'summary' => __('Sample competitor watch generated from your brief because live AI analysis was not available. Use it as a starting point, not as verified competitor intelligence.'),
            'notice' => $this->fallbackNotice($reason),
            'competitor_profiles' => $profiles,
            'pattern_map' => [
                'common_hooks' => [
                    __('The mistake most :audience make', ['audience' => $audience]),
                    __('A faster way to get the result'),
                    __('What changed in :industry', ['industry' => $industry]),
                ],
                'common_formats' => [
                    __('Checklist post'),
                    __('Before/after breakdown'),
                    __('Mini case study'),
                    __('Comparison post'),
                ],
                'offer_angles' => [
                    __('Save time'),
                    __('Reduce risk'),
                    __('Make execution easier'),
                    __('Improve consistency'),
                ],
                'visual_patterns' => [
                    __('Clean headline-led graphic'),
                    __('Screenshot with annotation'),
                    __('Carousel-style sequence'),
                ],
            ],
            'gaps' => [
                __('Show more concrete proof than competitors, not just claims.'),
                __('Own a narrower audience segment so the message feels more specific.'),
                __('Turn competitor education topics into stronger point-of-view posts.'),
                __('Use objection-led content before pushing offers.'),
                __('Build repeatable series around one recognizable content promise.'),
            ],
            'opportunities' => $this->fallbackOpportunities($brand, $industry, $audience, $goal, $competitorNames->all(), $platforms),
            'action_plan' => [
                ['step' => '1', 'action' => __('Pick 3 competitor hooks to monitor weekly.'), 'expected_outcome' => __('Clearer view of repeated positioning.')],
                ['step' => '2', 'action' => __('Create one proof-first post this week.'), 'expected_outcome' => __('Differentiate with evidence, not generic claims.')],
                ['step' => '3', 'action' => __('Build a comparison checklist.'), 'expected_outcome' => __('Help buyers evaluate options in your favor.')],
                ['step' => '4', 'action' => __('Turn objections into short posts.'), 'expected_outcome' => __('Capture demand before competitors pitch.')],
                ['step' => '5', 'action' => __('Review comments and saves after posting.'), 'expected_outcome' => __('Identify which gap is worth repeating.')],
            ],
            'source' => 'fallback',
        ];
    }

    protected function fallbackOpportunities(string $brand, string $industry, string $audience, string $goal, array $competitors, array $platforms): array
    {
        $primaryCompetitor = $competitors[0] ?? __('a competitor');
        $secondaryCompetitor = $competitors[1] ?? $primaryCompetitor;

        $items = [
            [
                'title' => __('Positioning teardown'),
                'why' => __('Use this when competitors feel broad or generic. It helps :brand claim a clearer reason to choose you.', ['brand' => $brand]),
                'hook' => __(':competitor is visible, but visibility is not the same as fit for :audience.', ['competitor' => $primaryCompetitor, 'audience' => $audience]),
                'brief' => __('Compare the competitor promise with one specific buyer problem in :industry, then show how :brand solves it with less friction.', ['industry' => $industry, 'brand' => $brand]),
                'cta' => __('Save this checklist before comparing options.'),
            ],
            [
                'title' => __('Objection spotlight'),
                'why' => __('Competitor content often skips buyer hesitation. Addressing it can create trust before the pitch.'),
                'hook' => __('The real blocker for :audience is not choosing a tool. It is knowing what will actually change.', ['audience' => $audience]),
                'brief' => __('Turn one common objection into a short post: cost, setup time, proof, support, or switching risk. Connect it to the goal: :goal.', ['goal' => $goal]),
                'cta' => __('Comment with the objection you hear most often.'),
            ],
            [
                'title' => __('Proof-first case post'),
                'why' => __('Proof separates your message from competitor claims without needing to attack them directly.'),
                'hook' => __('Before you believe another :industry promise, look for this proof.', ['industry' => $industry]),
                'brief' => __('Create a mini case format with before state, action taken, measurable change, and the practical lesson for :audience.', ['audience' => $audience]),
                'cta' => __('Use this proof test on your next vendor shortlist.'),
            ],
            [
                'title' => __('Comparison checklist'),
                'why' => __('A checklist lets :brand shape the buying criteria instead of reacting to competitor messaging.', ['brand' => $brand]),
                'hook' => __('Comparing :brand and :competitor? Start with these five questions.', ['brand' => $brand, 'competitor' => $secondaryCompetitor]),
                'brief' => __('List 5 evaluation points where :brand can be specific: workflow fit, speed, output quality, support, and long-term consistency.', ['brand' => $brand]),
                'cta' => __('Download or save this before your next review.'),
            ],
            [
                'title' => __('Contrarian point of view'),
                'why' => __('Strong opinions create distinction when competitors all teach the same basic tips.'),
                'hook' => __('The popular advice in :industry is incomplete.', ['industry' => $industry]),
                'brief' => __('Name one common recommendation competitors repeat, explain where it breaks, then offer a sharper operating principle from :brand.', ['brand' => $brand]),
                'cta' => __('Share this with a team that is still following the old playbook.'),
            ],
            [
                'title' => __('Hidden cost angle'),
                'why' => __('This supports conversion by making inaction or weak alternatives feel more expensive.'),
                'hook' => __('The expensive part is not the tool. It is the workflow you keep repeating every week.'),
                'brief' => __('Frame the hidden costs :audience experiences when competitor solutions are too generic: time loss, inconsistent output, missed approvals, or weak reporting.', ['audience' => $audience]),
                'cta' => __('Run this audit before your next campaign.'),
            ],
        ];

        return collect($items)->map(fn ($item, $index) => [
            'title' => $item['title'],
            'why_it_matters' => $item['why'],
            'recommended_platforms' => collect($platforms)->take(3)->values()->all(),
            'hook' => $item['hook'],
            'content_brief' => $item['brief'],
            'cta' => $item['cta'],
            'confidence' => max(64, 88 - ($index * 4)),
        ])->all();
    }

    protected function normalizeCompetitors(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn ($item) => $this->stringValue($item))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    protected function normalizePlatforms(array $platforms): array
    {
        $normalized = collect($platforms)
            ->map(fn ($platform) => strtolower($this->stringValue($platform)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : ['facebook', 'instagram', 'linkedin'];
    }

    protected function normalizeProfile(array $item): array
    {
        return [
            'name' => $this->stringValue($item['name'] ?? ''),
            'likely_positioning' => $this->stringValue($item['likely_positioning'] ?? ''),
            'content_themes' => $this->stringList($item['content_themes'] ?? []),
            'strengths' => $this->stringList($item['strengths'] ?? []),
            'weaknesses' => $this->stringList($item['weaknesses'] ?? []),
            'watch_items' => $this->stringList($item['watch_items'] ?? []),
        ];
    }

    protected function normalizeOpportunity(array $item, array $platforms): array
    {
        return [
            'title' => $this->stringValue($item['title'] ?? ''),
            'why_it_matters' => $this->stringValue($item['why_it_matters'] ?? ''),
            'recommended_platforms' => collect((array) ($item['recommended_platforms'] ?? $platforms))->map(fn ($value) => strtolower($this->stringValue($value)))->filter()->unique()->values()->all(),
            'hook' => $this->stringValue($item['hook'] ?? ''),
            'content_brief' => $this->stringValue($item['content_brief'] ?? ''),
            'cta' => $this->stringValue($item['cta'] ?? ''),
            'confidence' => $this->normalizeConfidence($item['confidence'] ?? 70),
        ];
    }

    protected function normalizePatternMap(array $map): array
    {
        return [
            'common_hooks' => $this->stringList($map['common_hooks'] ?? []),
            'common_formats' => $this->stringList($map['common_formats'] ?? []),
            'offer_angles' => $this->stringList($map['offer_angles'] ?? []),
            'visual_patterns' => $this->stringList($map['visual_patterns'] ?? []),
        ];
    }

    protected function normalizeActionPlan(array $items): array
    {
        return collect($items)
            ->map(fn ($item, $index) => [
                'step' => $this->stringValue(data_get($item, 'step', (string) ($index + 1))),
                'action' => $this->stringValue(data_get($item, 'action', '')),
                'expected_outcome' => $this->stringValue(data_get($item, 'expected_outcome', '')),
            ])
            ->filter(fn ($item) => $item['action'] !== '')
            ->take(5)
            ->values()
            ->all();
    }

    protected function stringList(mixed $value, int $limit = 6): array
    {
        return collect((array) $value)
            ->map(fn ($item) => $this->stringValue($item))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    protected function competitorName(string $value): string
    {
        $value = trim($value);
        $host = parse_url($value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return preg_replace('/^www\./', '', $host) ?: $host;
        }

        return Str::limit($value, 70, '');
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
            $value = $this->stringValue($config[$key] ?? null);

            if ($value !== '') {
                $lines[] = $label.': '.$value.'.';
            }
        }

        return $lines;
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

    protected function normalizeConfidence(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace('%', '', trim($value));
        }

        if (! is_numeric($value)) {
            return 70;
        }

        $confidence = (float) $value;

        if ($confidence > 0 && $confidence <= 1) {
            $confidence *= 100;
        }

        return max(1, min(100, (int) round($confidence)));
    }

    protected function fallbackNotice(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            return __('AI provider did not return a usable report.');
        }

        if (str_contains($reason, 'Array to string conversion')) {
            return __('Brand Voice Kit data was normalized. Run again to request a live AI report.');
        }

        return Str::limit($reason, 180);
    }

    protected function logUsage(string $provider, string $model, string $status, float $startedAt, ?string $errorMessage = null): void
    {
        if (! function_exists('log_ai_usage')) {
            return;
        }

        log_ai_usage([
            'provider' => $provider,
            'capability' => 'content',
            'model' => $model,
            'feature' => 'ai-content-studio.competitor-watch',
            'status' => $status,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error_message' => $errorMessage,
        ]);
    }

    protected function responseErrorMessage(Response $response): string
    {
        return $this->stringValue(data_get($response->json(), 'error.message', ''))
            ?: $this->stringValue(data_get($response->json(), 'message', ''))
            ?: Str::limit((string) $response->body(), 280)
            ?: 'AI request failed.';
    }
}
