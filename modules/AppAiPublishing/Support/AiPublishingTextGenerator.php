<?php

namespace Modules\AppAiPublishing\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use RuntimeException;
use Throwable;

class AiPublishingTextGenerator
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function generate(string $prompt, array $config = []): array
    {
        $provider = (string) $this->options->get('ai_content_provider', 'openai');
        $model = (string) $this->options->get('ai_content_model', 'gpt-5.4');
        $startedAt = microtime(true);

        try {
            $result = match ($provider) {
                'openai' => $this->generateWithOpenAi($prompt, $model, $config),
                'gemini' => $this->generateWithGemini($prompt, $model, $config),
                default => throw new RuntimeException(__('The selected AI content provider is not supported for AI publishing yet.')),
            };

            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => $provider,
                    'capability' => 'content',
                    'model' => $model,
                    'feature' => 'ai-publishing.generate',
                    'status' => 'success',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'metadata' => ['mode' => $config['mode'] ?? 'caption_only'],
                ]);
            }

            return $result;
        } catch (Throwable $exception) {
            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => $provider,
                    'capability' => 'content',
                    'model' => $model,
                    'feature' => 'ai-publishing.generate',
                    'status' => 'error',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    protected function generateWithOpenAi(string $prompt, string $model, array $config): array
    {
        $apiKey = trim((string) $this->options->get('ai_openai_api_key', ''));
        $baseUrl = rtrim((string) $this->options->get('ai_openai_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            throw new RuntimeException(__('OpenAI API key is missing.'));
        }

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => 0.9,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You create social publishing assets. Return valid JSON only.'],
                    ['role' => 'user', 'content' => $this->generationPrompt($prompt, $config)],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('AI publishing generation request failed.'));
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(__('AI publishing generation returned invalid JSON.'));
        }

        return $this->normalizePayload($decoded);
    }

    protected function generateWithGemini(string $prompt, string $model, array $config): array
    {
        $apiKey = trim((string) $this->options->get('ai_gemini_api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException(__('Gemini API key is missing.'));
        }

        $response = Http::timeout(120)
            ->acceptJson()
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [[
                    'parts' => [[
                        'text' => $this->generationPrompt($prompt, $config),
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.9,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('AI publishing generation request failed.'));
        }

        $text = collect((array) data_get($response->json(), 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter()
            ->implode("\n");

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(__('AI publishing generation returned invalid JSON.'));
        }

        return $this->normalizePayload($decoded);
    }

    protected function generationPrompt(string $prompt, array $config): string
    {
        $language = (string) ($config['language'] ?? 'en');
        $tone = (string) ($config['tone'] ?? 'professional');
        $mode = (string) ($config['mode'] ?? 'caption_only');
        $hashtags = ! empty($config['hashtags']) ? 'Include a concise hashtag set.' : 'Do not include hashtags.';
        $cta = ! empty($config['cta']) ? 'Include a clear call to action.' : 'Do not force a call to action.';
        $emoji = ! empty($config['emoji']) ? 'You may use a light amount of emoji.' : 'Do not use emoji.';

        return trim(implode("\n\n", array_filter([
            'Create social publishing assets from the following prompt.',
            'Return strict JSON with keys: caption, content, image_prompt.',
            'caption must be short and ready to publish.',
            'content must be a longer post body suitable for social publishing.',
            'image_prompt must describe a visual that matches the post.',
            'Write in language: '.$language.'.',
            'Tone: '.$tone.'.',
            'Generation mode: '.$mode.'.',
            $hashtags,
            $cta,
            $emoji,
            'User prompt: '.$prompt,
        ])));
    }

    protected function normalizePayload(array $decoded): array
    {
        $caption = trim((string) ($decoded['caption'] ?? ''));
        $content = trim((string) ($decoded['content'] ?? ''));
        $imagePrompt = trim((string) ($decoded['image_prompt'] ?? ''));
        $variants = collect((array) ($decoded['variants'] ?? []))
            ->map(fn ($variant) => [
                'caption' => trim((string) ($variant['caption'] ?? '')),
                'content' => trim((string) ($variant['content'] ?? '')),
            ])
            ->filter(fn ($variant) => $variant['caption'] !== '' || $variant['content'] !== '')
            ->take(3)
            ->values()
            ->all();

        if ($caption === '' && $content === '') {
            throw new RuntimeException(__('AI publishing generation returned incomplete content.'));
        }

        return [
            'caption' => $caption !== '' ? $caption : Str::limit($content, 160, '...'),
            'content' => $content !== '' ? $content : $caption,
            'image_prompt' => $imagePrompt !== '' ? $imagePrompt : $caption,
            'variants' => $variants,
        ];
    }
}
