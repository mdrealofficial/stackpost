<?php

namespace Modules\AdminAI\Support;

class AiOptionCatalog
{
    public function toggleOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Enable'],
            ['value' => '0', 'label' => 'Disable'],
        ];
    }

    public function providerOptions(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'anthropic', 'label' => 'Anthropic'],
            ['value' => 'gemini', 'label' => 'Gemini'],
            ['value' => 'groq', 'label' => 'Groq'],
            ['value' => 'ollama', 'label' => 'Ollama'],
            ['value' => 'xai', 'label' => 'xAI'],
            ['value' => 'openrouter', 'label' => 'OpenRouter'],
        ];
    }

    public function imageProviderOptions(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'gemini', 'label' => 'Gemini'],
            ['value' => 'xai', 'label' => 'xAI'],
        ];
    }

    public function videoProviderOptions(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'gemini', 'label' => 'Gemini'],
        ];
    }

    public function voiceProviderOptions(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'eleven', 'label' => 'ElevenLabs'],
            ['value' => 'gemini', 'label' => 'Gemini'],
        ];
    }

    public function defaultLanguageOptions(): array
    {
        return collect(world_languages())
            ->map(fn ($language) => [
                'value' => (string) data_get($language, 'code'),
                'label' => (string) data_get($language, 'name'),
                'native_name' => (string) data_get($language, 'native_name', ''),
            ])
            ->values()
            ->all();
    }

    public function toneOfVoiceOptions(): array
    {
        return [
            ['value' => 'friendly', 'label' => 'Friendly'],
            ['value' => 'professional', 'label' => 'Professional'],
            ['value' => 'casual', 'label' => 'Casual'],
            ['value' => 'formal', 'label' => 'Formal'],
            ['value' => 'persuasive', 'label' => 'Persuasive'],
            ['value' => 'empathetic', 'label' => 'Empathetic'],
            ['value' => 'playful', 'label' => 'Playful'],
            ['value' => 'bold', 'label' => 'Bold'],
            ['value' => 'luxury', 'label' => 'Luxury'],
            ['value' => 'minimal', 'label' => 'Minimal'],
        ];
    }

    public function creativityOptions(): array
    {
        return [
            ['value' => 'strict', 'label' => 'Strict'],
            ['value' => 'conservative', 'label' => 'Conservative'],
            ['value' => 'economic', 'label' => 'Economic'],
            ['value' => 'focused', 'label' => 'Focused'],
            ['value' => 'balanced', 'label' => 'Balanced'],
            ['value' => 'imaginative', 'label' => 'Imaginative'],
            ['value' => 'creative', 'label' => 'Creative'],
            ['value' => 'experimental', 'label' => 'Experimental'],
        ];
    }

    public function embeddingModelOptions(): array
    {
        return [
            ['value' => 'text-embedding-3-small', 'label' => 'text-embedding-3-small'],
            ['value' => 'text-embedding-3-large', 'label' => 'text-embedding-3-large'],
            ['value' => 'voyage-3-large', 'label' => 'voyage-3-large'],
            ['value' => 'jina-embeddings-v3', 'label' => 'jina-embeddings-v3'],
        ];
    }

    public function values(string $group): array
    {
        return collect(match ($group) {
            'toggle' => $this->toggleOptions(),
            'provider' => $this->providerOptions(),
            'image_provider' => $this->imageProviderOptions(),
            'video_provider' => $this->videoProviderOptions(),
            'voice_provider' => $this->voiceProviderOptions(),
            'default_language' => $this->defaultLanguageOptions(),
            'tone_of_voice' => $this->toneOfVoiceOptions(),
            'creativity' => $this->creativityOptions(),
            'embedding_model' => $this->embeddingModelOptions(),
            default => [],
        })->pluck('value')->all();
    }
}
