<?php

namespace Modules\AdminBlogs\Support;

use Illuminate\Support\Facades\Http;
use Modules\AdminSettings\Support\OptionStore;
use RuntimeException;
use Throwable;

class BlogAiContentGenerator
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function generate(string $prompt, string $locale = 'en'): array
    {
        $provider = (string) $this->options->get('ai_content_provider', 'openai');
        $model = (string) $this->options->get('ai_content_model', 'gpt-5.4');
        $startedAt = microtime(true);

        try {
            $result = match ($provider) {
                'openai' => $this->generateWithOpenAi($prompt, $model, $locale),
                'gemini' => $this->generateWithGemini($prompt, $model, $locale),
                default => throw new RuntimeException(__('The selected AI content provider is not supported for blog generation yet.')),
            };

            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => $provider,
                    'capability' => 'content',
                    'model' => $model,
                    'feature' => 'blogs.ai.generate',
                    'status' => 'success',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }

            return $result;
        } catch (Throwable $exception) {
            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => $provider,
                    'capability' => 'content',
                    'model' => $model,
                    'feature' => 'blogs.ai.generate',
                    'status' => 'error',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    protected function generateWithOpenAi(string $prompt, string $model, string $locale): array
    {
        $apiKey = trim((string) $this->options->get('ai_openai_api_key', ''));
        $baseUrl = rtrim((string) $this->options->get('ai_openai_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            throw new RuntimeException(__('OpenAI API key is missing.'));
        }

        $response = Http::timeout(90)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'temperature' => 0.8,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You create polished multilingual blog drafts. Return valid JSON only.'],
                    ['role' => 'user', 'content' => $this->contentPrompt($prompt, $locale)],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('AI content generation request failed.'));
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(__('AI content generation returned invalid JSON.'));
        }

        return $this->normalizePayload($decoded);
    }

    protected function generateWithGemini(string $prompt, string $model, string $locale): array
    {
        $apiKey = trim((string) $this->options->get('ai_gemini_api_key', ''));

        if ($apiKey === '') {
            throw new RuntimeException(__('Gemini API key is missing.'));
        }

        $response = Http::timeout(90)
            ->acceptJson()
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [[
                    'parts' => [[
                        'text' => $this->contentPrompt($prompt, $locale),
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.8,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('AI content generation request failed.'));
        }

        $text = collect((array) data_get($response->json(), 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter()
            ->implode("\n");

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(__('AI content generation returned invalid JSON.'));
        }

        return $this->normalizePayload($decoded);
    }

    protected function contentPrompt(string $prompt, string $locale): string
    {
        return trim(implode("\n\n", array_filter([
            'Create a complete blog draft from the following brief.',
            'Return strict JSON with keys: title, excerpt, content.',
            'Write the content in locale: '.$locale.'.',
            'The content field must be clean HTML body fragments only.',
            'Use semantic HTML such as h2, h3, p, ul, ol, blockquote when useful.',
            'Do not include markdown fences or <html>/<body> tags.',
            'User brief: '.$prompt,
        ])));
    }

    protected function normalizePayload(array $decoded): array
    {
        $title = trim((string) ($decoded['title'] ?? ''));
        $excerpt = trim((string) ($decoded['excerpt'] ?? ''));
        $content = trim((string) ($decoded['content'] ?? ''));

        if ($title === '' || $content === '') {
            throw new RuntimeException(__('AI content generation returned incomplete content.'));
        }

        return compact('title', 'excerpt', 'content');
    }
}
