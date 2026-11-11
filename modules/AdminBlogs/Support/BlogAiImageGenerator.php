<?php

namespace Modules\AdminBlogs\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;
use RuntimeException;
use Throwable;

class BlogAiImageGenerator
{
    public function __construct(
        protected OptionStore $options,
        protected FileManager $files,
    ) {}

    public function generateForUser(User $user, string $prompt, ?AppFile $parent = null): AppFile
    {
        $provider = (string) $this->options->get('ai_image_provider', 'openai');
        $model = (string) $this->options->get('ai_image_model', 'gpt-image-1');
        $startedAt = microtime(true);

        try {
            $image = match ($provider) {
                'openai' => $this->generateWithOpenAi($prompt, $model),
                'gemini' => $this->generateWithGemini($prompt, $model),
                default => throw new RuntimeException(__('The selected AI image provider is not supported for blog thumbnails yet.')),
            };

            $file = $this->files->storeBinaryFile(
                owner: $user,
                originalName: 'ai-blog-thumbnail-'.now()->format('YmdHis').'.'.$image['extension'],
                mimeType: $image['mime_type'],
                contents: $image['contents'],
                parent: $parent,
                note: $prompt
            );

            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => $provider,
                    'capability' => 'image',
                    'model' => $model,
                    'feature' => 'blogs.ai.image',
                    'status' => 'success',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }

            return $file;
        } catch (Throwable $exception) {
            if (function_exists('log_ai_usage')) {
                log_ai_usage([
                    'provider' => $provider,
                    'capability' => 'image',
                    'model' => $model,
                    'feature' => 'blogs.ai.image',
                    'status' => 'error',
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    protected function generateWithOpenAi(string $prompt, string $model): array
    {
        $apiKey = trim((string) $this->options->get('ai_openai_api_key', ''));
        $baseUrl = rtrim((string) $this->options->get('ai_openai_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            throw new RuntimeException(__('OpenAI API key is missing.'));
        }

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/images/generations', [
                'model' => $model,
                'prompt' => $this->imagePrompt($prompt),
                'size' => '1536x1024',
                'response_format' => 'b64_json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseErrorMessage($response, __('AI image generation request failed.')));
        }

        $b64 = (string) data_get($response->json(), 'data.0.b64_json', '');

        if ($b64 === '') {
            throw new RuntimeException(__('AI image generation returned no image data.'));
        }

        $contents = base64_decode($b64, true);

        if ($contents === false) {
            throw new RuntimeException(__('AI image generation returned invalid image data.'));
        }

        return [
            'contents' => $contents,
            'mime_type' => 'image/png',
            'extension' => 'png',
        ];
    }

    protected function generateWithGemini(string $prompt, string $model): array
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
                        'text' => $this->imagePrompt($prompt),
                    ]],
                ]],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseErrorMessage($response, __('AI image generation request failed.')));
        }

        $parts = (array) data_get($response->json(), 'candidates.0.content.parts', []);
        $inlineData = collect($parts)->pluck('inlineData')->filter()->first();
        $b64 = (string) data_get($inlineData, 'data', '');
        $mimeType = (string) data_get($inlineData, 'mimeType', 'image/png');

        if ($b64 === '') {
            throw new RuntimeException(__('AI image generation returned no image data.'));
        }

        $contents = base64_decode($b64, true);

        if ($contents === false) {
            throw new RuntimeException(__('AI image generation returned invalid image data.'));
        }

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'extension' => str_contains($mimeType, 'webp') ? 'webp' : 'png',
        ];
    }

    protected function imagePrompt(string $prompt): string
    {
        return trim(implode("\n\n", [
            'Create a professional horizontal editorial thumbnail image for a blog post.',
            'Avoid text overlays, watermarks, logos, UI chrome, or collage layouts.',
            'Use a clean photographic or cinematic illustration style suitable for article covers.',
            'User brief: '.$prompt,
        ]));
    }

    protected function responseErrorMessage(Response $response, string $fallback): string
    {
        $message = trim((string) data_get($response->json(), 'error.message', ''));

        if ($message === '') {
            $message = trim((string) data_get($response->json(), 'message', ''));
        }

        if ($message === '') {
            $message = trim((string) $response->body());
        }

        if ($message === '') {
            return $fallback;
        }

        return Str::limit($message, 280);
    }
}
