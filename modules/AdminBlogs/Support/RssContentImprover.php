<?php

namespace Modules\AdminBlogs\Support;

use Illuminate\Support\Facades\Http;
use Modules\AdminSettings\Support\OptionStore;
use Throwable;

class RssContentImprover
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function improve(array $payload, ?string $extraPrompt = null): array
    {
        $provider = (string) $this->options->get('ai_content_provider', 'openai');
        $model = (string) $this->options->get('ai_content_model', 'gpt-5.4');
        $apiKey = trim((string) $this->options->get('ai_openai_api_key', ''));
        $baseUrl = rtrim((string) $this->options->get('ai_openai_url', 'https://api.openai.com/v1'), '/');

        if ($provider !== 'openai' || $apiKey === '') {
            return $payload;
        }

        $startedAt = microtime(true);

        try {
            $prompt = trim(implode("\n\n", array_filter([
                'Rewrite and improve this imported blog article so it is materially different in wording from the original source, while preserving factual meaning.',
                'Return strict JSON with keys: title, excerpt, content.',
                'Keep content as clean HTML body fragments only. Do not include markdown fences.',
                $extraPrompt ? 'Extra instruction: '.$extraPrompt : null,
                'Source title: '.$payload['title'],
                'Source excerpt: '.($payload['excerpt'] ?: ''),
                'Source content: '.($payload['content'] ?: ''),
            ])));

            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.8,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'You rewrite imported RSS articles into cleaner blog-ready content.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('AI rewrite request failed.');
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                throw new \RuntimeException('AI rewrite response was not valid JSON.');
            }

            $improved = [
                'title' => trim((string) ($decoded['title'] ?? $payload['title'] ?? '')),
                'excerpt' => trim((string) ($decoded['excerpt'] ?? $payload['excerpt'] ?? '')),
                'content' => trim((string) ($decoded['content'] ?? $payload['content'] ?? '')),
            ];

            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => 'openai',
                    'capability' => 'content',
                    'model' => $model,
                    'feature' => 'blogs.rss.improve',
                    'status' => 'success',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }

            return $improved;
        } catch (Throwable $exception) {
            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => 'openai',
                    'capability' => 'content',
                    'model' => $model,
                    'feature' => 'blogs.rss.improve',
                    'status' => 'error',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => $exception->getMessage(),
                ]);
            }

            return $payload;
        }
    }
}
