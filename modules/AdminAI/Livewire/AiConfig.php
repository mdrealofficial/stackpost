<?php

namespace Modules\AdminAI\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminAI\Support\AiOptionCatalog;

#[Title('AI Settings')]
class AiConfig extends Component
{
    protected OptionStore $options;

    protected AiOptionCatalog $catalog;

    public string $ai_status = '0';

    public string $ai_default_provider = 'openai';

    public string $ai_default_language = 'en';

    public string $ai_default_tone_of_voice = 'friendly';

    public string $ai_default_creativity = 'economic';

    public string $ai_maximum_input_length = '100';

    public string $ai_maximum_output_length = '2000';

    public string $ai_text_model = 'gpt-5.4';

    public string $ai_embedding_model = 'text-embedding-3-small';

    public string $ai_openai_api_key = '';

    public string $ai_openai_url = 'https://api.openai.com/v1';

    public string $ai_anthropic_api_key = '';

    public string $ai_gemini_api_key = '';

    public string $ai_groq_api_key = '';

    public string $ai_ollama_api_key = '';

    public string $ai_ollama_url = 'http://localhost:11434';

    public string $ai_xai_api_key = '';

    public string $ai_openrouter_api_key = '';

    public string $ai_content_status = '1';

    public string $ai_content_provider = 'openai';

    public string $ai_content_model = 'gpt-5.4';

    public string $ai_text_status = '1';

    public string $ai_text_provider = 'openai';

    public string $ai_text_generation_model = 'gpt-5.4';

    public string $ai_image_status = '1';

    public string $ai_image_provider = 'gemini';

    public string $ai_image_model = 'gemini-3-pro-image-preview';

    public string $ai_video_status = '0';

    public string $ai_video_provider = 'openai';

    public string $ai_video_model = 'sora-2';

    public string $ai_chat_status = '1';

    public string $ai_chat_provider = 'openai';

    public string $ai_chat_model = 'gpt-5.4';

    public string $ai_voice_status = '0';

    public string $ai_voice_provider = 'openai';

    public string $ai_voice_model = 'gpt-realtime';

    public string $ai_code_status = '1';

    public string $ai_code_provider = 'openai';

    public string $ai_code_model = 'gpt-5.4';

    public function boot(OptionStore $options, AiOptionCatalog $catalog): void
    {
        $this->options = $options;
        $this->catalog = $catalog;
    }

    public function mount(): void
    {
        foreach ($this->stateKeys() as $key => $default) {
            $this->{$key} = (string) $this->options->get($key, $default);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'ai_status' => ['required', 'in:0,1'],
            'ai_default_provider' => ['required', Rule::in($this->catalog->values('provider'))],
            'ai_default_language' => ['required', Rule::in($this->catalog->values('default_language'))],
            'ai_default_tone_of_voice' => ['required', Rule::in($this->catalog->values('tone_of_voice'))],
            'ai_default_creativity' => ['required', Rule::in($this->catalog->values('creativity'))],
            'ai_maximum_input_length' => ['required', 'integer', 'min:1', 'max:1000000'],
            'ai_maximum_output_length' => ['required', 'integer', 'min:1', 'max:1000000'],
            'ai_text_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('text', $this->ai_default_provider))],
            'ai_embedding_model' => ['required', 'string', 'max:120'],
            'ai_openai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_openai_url' => ['nullable', 'url', 'max:255'],
            'ai_anthropic_api_key' => ['nullable', 'string', 'max:500'],
            'ai_gemini_api_key' => ['nullable', 'string', 'max:500'],
            'ai_groq_api_key' => ['nullable', 'string', 'max:500'],
            'ai_ollama_api_key' => ['nullable', 'string', 'max:500'],
            'ai_ollama_url' => ['nullable', 'url', 'max:255'],
            'ai_xai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_openrouter_api_key' => ['nullable', 'string', 'max:500'],
            'ai_content_status' => ['required', 'in:0,1'],
            'ai_content_provider' => ['required', Rule::in($this->catalog->values('provider'))],
            'ai_content_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('content', $this->ai_content_provider))],
            'ai_text_status' => ['required', 'in:0,1'],
            'ai_text_provider' => ['required', Rule::in($this->catalog->values('provider'))],
            'ai_text_generation_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('text', $this->ai_text_provider))],
            'ai_image_status' => ['required', 'in:0,1'],
            'ai_image_provider' => ['required', Rule::in($this->catalog->values('image_provider'))],
            'ai_image_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('image', $this->ai_image_provider))],
            'ai_video_status' => ['required', 'in:0,1'],
            'ai_video_provider' => ['required', Rule::in($this->catalog->values('video_provider'))],
            'ai_video_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('video', $this->ai_video_provider))],
            'ai_chat_status' => ['required', 'in:0,1'],
            'ai_chat_provider' => ['required', Rule::in($this->catalog->values('provider'))],
            'ai_chat_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('chat', $this->ai_chat_provider))],
            'ai_voice_status' => ['required', 'in:0,1'],
            'ai_voice_provider' => ['required', Rule::in($this->catalog->values('voice_provider'))],
            'ai_voice_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('voice', $this->ai_voice_provider))],
            'ai_code_status' => ['required', 'in:0,1'],
            'ai_code_provider' => ['required', Rule::in($this->catalog->values('provider'))],
            'ai_code_model' => ['required', 'string', 'max:120', Rule::in($this->modelValuesFor('code', $this->ai_code_provider))],
        ]);

        foreach ($validated as $key => $value) {
            $this->options->set($key, $value);
        }

        $this->dispatch('settings-saved');
        session()->flash('status', __('AI settings saved successfully.'));
    }

    protected function stateKeys(): array
    {
        return [
            'ai_status' => '0',
            'ai_default_provider' => 'openai',
            'ai_default_language' => 'en',
            'ai_default_tone_of_voice' => 'friendly',
            'ai_default_creativity' => 'economic',
            'ai_maximum_input_length' => '100',
            'ai_maximum_output_length' => '2000',
            'ai_text_model' => 'gpt-5.4',
            'ai_embedding_model' => 'text-embedding-3-small',
            'ai_openai_api_key' => '',
            'ai_openai_url' => 'https://api.openai.com/v1',
            'ai_anthropic_api_key' => '',
            'ai_gemini_api_key' => '',
            'ai_groq_api_key' => '',
            'ai_ollama_api_key' => '',
            'ai_ollama_url' => 'http://localhost:11434',
            'ai_xai_api_key' => '',
            'ai_openrouter_api_key' => '',
            'ai_content_status' => '1',
            'ai_content_provider' => 'openai',
            'ai_content_model' => 'gpt-5.4',
            'ai_text_status' => '1',
            'ai_text_provider' => 'openai',
            'ai_text_generation_model' => 'gpt-5.4',
            'ai_image_status' => '1',
            'ai_image_provider' => 'gemini',
            'ai_image_model' => 'gemini-3-pro-image-preview',
            'ai_video_status' => '0',
            'ai_video_provider' => 'openai',
            'ai_video_model' => 'sora-2',
            'ai_chat_status' => '1',
            'ai_chat_provider' => 'openai',
            'ai_chat_model' => 'gpt-5.4',
            'ai_voice_status' => '0',
            'ai_voice_provider' => 'openai',
            'ai_voice_model' => 'gpt-realtime',
            'ai_code_status' => '1',
            'ai_code_provider' => 'openai',
            'ai_code_model' => 'gpt-5.4',
        ];
    }

    protected function modelOptionsFor(string $capability, string $provider): array
    {
        return match ($capability) {
            'chat' => match ($provider) {
                'openai' => [
                    ['value' => 'gpt-5.4', 'label' => 'GPT-5.4'],
                    ['value' => 'gpt-5.4-mini', 'label' => 'GPT-5.4 Mini'],
                    ['value' => 'gpt-5.4-nano', 'label' => 'GPT-5.4 Nano'],
                    ['value' => 'gpt-5.4-pro', 'label' => 'GPT-5.4 Pro'],
                    ['value' => 'gpt-5-chat-latest', 'label' => 'GPT-5 Chat'],
                    ['value' => 'gpt-5.3-chat-latest', 'label' => 'GPT-5.3 Chat'],
                    ['value' => 'gpt-5.2-chat-latest', 'label' => 'GPT-5.2 Chat'],
                    ['value' => 'gpt-5.1-chat-latest', 'label' => 'GPT-5.1 Chat'],
                    ['value' => 'chatgpt-4o-latest', 'label' => 'ChatGPT-4o'],
                    ['value' => 'gpt-4o', 'label' => 'GPT-4o'],
                    ['value' => 'gpt-4o-mini', 'label' => 'GPT-4o Mini'],
                    ['value' => 'gpt-4.1', 'label' => 'GPT-4.1'],
                    ['value' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 Mini'],
                    ['value' => 'o3', 'label' => 'o3'],
                    ['value' => 'o4-mini', 'label' => 'o4-mini'],
                ],
                default => $this->modelOptionsFor('text', $provider),
            },
            'code' => match ($provider) {
                'openai' => [
                    ['value' => 'gpt-5.4', 'label' => 'GPT-5.4'],
                    ['value' => 'gpt-5.4-mini', 'label' => 'GPT-5.4 Mini'],
                    ['value' => 'gpt-5.4-pro', 'label' => 'GPT-5.4 Pro'],
                    ['value' => 'gpt-5-codex', 'label' => 'GPT-5 Codex'],
                    ['value' => 'gpt-5.3-codex', 'label' => 'GPT-5.3 Codex'],
                    ['value' => 'gpt-5.2-codex', 'label' => 'GPT-5.2 Codex'],
                    ['value' => 'gpt-5.1-codex', 'label' => 'GPT-5.1 Codex'],
                    ['value' => 'gpt-5.1-codex-mini', 'label' => 'GPT-5.1 Codex Mini'],
                    ['value' => 'gpt-5.1-codex-max', 'label' => 'GPT-5.1 Codex Max'],
                    ['value' => 'codex-mini-latest', 'label' => 'Codex Mini Latest'],
                    ['value' => 'o3', 'label' => 'o3'],
                    ['value' => 'o3-pro', 'label' => 'o3-pro'],
                    ['value' => 'o4-mini', 'label' => 'o4-mini'],
                    ['value' => 'gpt-4.1', 'label' => 'GPT-4.1'],
                    ['value' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 Mini'],
                ],
                default => match ($provider) {
                    'anthropic', 'gemini', 'groq', 'ollama', 'xai', 'openrouter' => $this->modelOptionsFor('text', $provider),
                    default => [],
                },
            },
            'image' => match ($provider) {
                'openai' => [
                    ['value' => 'gpt-image-1.5', 'label' => 'GPT Image 1.5'],
                    ['value' => 'gpt-image-1', 'label' => 'GPT Image 1'],
                    ['value' => 'gpt-image-1-mini', 'label' => 'GPT Image 1 Mini'],
                    ['value' => 'chatgpt-image-latest', 'label' => 'ChatGPT Image Latest'],
                    ['value' => 'dall-e-3', 'label' => 'DALL-E 3'],
                    ['value' => 'dall-e-2', 'label' => 'DALL-E 2'],
                ],
                'gemini' => [
                    ['value' => 'gemini-3-pro-image-preview', 'label' => 'Gemini 3 Pro Image Preview'],
                    ['value' => 'gemini-2.5-flash-image', 'label' => 'Gemini 2.5 Flash Image'],
                ],
                'xai' => [
                    ['value' => 'grok-imagine-image', 'label' => 'Grok Imagine Image'],
                ],
                default => [],
            },
            'video' => match ($provider) {
                'openai' => [
                    ['value' => 'sora-2', 'label' => 'Sora 2'],
                    ['value' => 'sora-2-pro', 'label' => 'Sora 2 Pro'],
                ],
                'gemini' => [
                    ['value' => 'veo-3.1-generate-preview', 'label' => 'Veo 3.1 Generate Preview'],
                    ['value' => 'veo-3.1-fast-generate-preview', 'label' => 'Veo 3.1 Fast Generate Preview'],
                ],
                default => [],
            },
            'voice' => match ($provider) {
                'openai' => [
                    ['value' => 'gpt-realtime-1.5', 'label' => 'GPT Realtime 1.5'],
                    ['value' => 'gpt-realtime', 'label' => 'GPT Realtime'],
                    ['value' => 'gpt-realtime-mini', 'label' => 'GPT Realtime Mini'],
                    ['value' => 'gpt-4o-realtime-preview', 'label' => 'GPT-4o Realtime'],
                    ['value' => 'gpt-4o-mini-realtime-preview', 'label' => 'GPT-4o Mini Realtime'],
                    ['value' => 'gpt-audio-1.5', 'label' => 'GPT Audio 1.5'],
                    ['value' => 'gpt-audio', 'label' => 'GPT Audio'],
                    ['value' => 'gpt-audio-mini', 'label' => 'GPT Audio Mini'],
                    ['value' => 'gpt-4o-audio-preview', 'label' => 'GPT-4o Audio'],
                    ['value' => 'gpt-4o-mini-audio-preview', 'label' => 'GPT-4o Mini Audio'],
                    ['value' => 'gpt-4o-transcribe', 'label' => 'GPT-4o Transcribe'],
                    ['value' => 'gpt-4o-transcribe-diarize', 'label' => 'GPT-4o Transcribe Diarize'],
                    ['value' => 'gpt-4o-mini-transcribe', 'label' => 'GPT-4o Mini Transcribe'],
                    ['value' => 'tts-1', 'label' => 'tts-1'],
                    ['value' => 'tts-1-hd', 'label' => 'tts-1-hd'],
                    ['value' => 'whisper-1', 'label' => 'Whisper'],
                ],
                'eleven' => [
                    ['value' => 'eleven_v3', 'label' => 'Eleven v3'],
                    ['value' => 'eleven_multilingual_v2', 'label' => 'Eleven Multilingual v2'],
                    ['value' => 'eleven_flash_v2_5', 'label' => 'Eleven Flash v2.5'],
                ],
                'gemini' => [
                    ['value' => 'gemini-2.5-flash-native-audio-preview-12-2025', 'label' => 'Gemini 2.5 Flash Native Audio Preview'],
                    ['value' => 'gemini-2.5-flash-preview-tts', 'label' => 'Gemini 2.5 Flash Preview TTS'],
                    ['value' => 'gemini-2.5-pro-preview-tts', 'label' => 'Gemini 2.5 Pro Preview TTS'],
                    ['value' => 'gemini-3.1-flash-live-preview', 'label' => 'Gemini 3.1 Flash Live Preview'],
                ],
                default => [],
            },
            default => match ($provider) {
                'openai' => [
                    ['value' => 'gpt-5.4', 'label' => 'GPT-5.4'],
                    ['value' => 'gpt-5.4-mini', 'label' => 'GPT-5.4 Mini'],
                    ['value' => 'gpt-5.4-nano', 'label' => 'GPT-5.4 Nano'],
                    ['value' => 'gpt-5.4-pro', 'label' => 'GPT-5.4 Pro'],
                    ['value' => 'gpt-5.3-instant', 'label' => 'GPT-5.3 Instant'],
                    ['value' => 'gpt-5.2', 'label' => 'GPT-5.2'],
                    ['value' => 'gpt-5.2-pro', 'label' => 'GPT-5.2 Pro'],
                    ['value' => 'gpt-5.1', 'label' => 'GPT-5.1'],
                    ['value' => 'gpt-5-pro', 'label' => 'GPT-5 Pro'],
                    ['value' => 'gpt-5', 'label' => 'GPT-5'],
                    ['value' => 'gpt-5-mini', 'label' => 'GPT-5 Mini'],
                    ['value' => 'gpt-5-nano', 'label' => 'GPT-5 Nano'],
                    ['value' => 'o3', 'label' => 'o3'],
                    ['value' => 'o3-mini', 'label' => 'o3-mini'],
                    ['value' => 'o3-pro', 'label' => 'o3-pro'],
                    ['value' => 'o4-mini', 'label' => 'o4-mini'],
                    ['value' => 'o1', 'label' => 'o1'],
                    ['value' => 'o1-mini', 'label' => 'o1-mini'],
                    ['value' => 'o1-pro', 'label' => 'o1-pro'],
                    ['value' => 'gpt-4o', 'label' => 'GPT-4o'],
                    ['value' => 'gpt-4o-mini', 'label' => 'GPT-4o Mini'],
                    ['value' => 'gpt-4.1', 'label' => 'GPT-4.1'],
                    ['value' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 Mini'],
                    ['value' => 'gpt-4.1-nano', 'label' => 'GPT-4.1 Nano'],
                ],
                'anthropic' => [
                    ['value' => 'claude-opus-4-0', 'label' => 'Claude Opus 4'],
                    ['value' => 'claude-sonnet-4-0', 'label' => 'Claude Sonnet 4'],
                    ['value' => 'claude-3-7-sonnet-latest', 'label' => 'Claude Sonnet 3.7'],
                    ['value' => 'claude-3-5-haiku-latest', 'label' => 'Claude Haiku 3.5'],
                ],
                'gemini' => [
                    ['value' => 'gemini-3-pro-preview', 'label' => 'Gemini 3 Pro Preview'],
                    ['value' => 'gemini-3-flash-preview', 'label' => 'Gemini 3 Flash Preview'],
                    ['value' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro'],
                    ['value' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash'],
                    ['value' => 'gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash Lite'],
                ],
                'groq' => [
                    ['value' => 'openai/gpt-oss-120b', 'label' => 'OpenAI GPT-OSS 120B'],
                    ['value' => 'openai/gpt-oss-20b', 'label' => 'OpenAI GPT-OSS 20B'],
                    ['value' => 'meta-llama/llama-4-scout-17b-16e-instruct', 'label' => 'Llama 4 Scout 17B'],
                    ['value' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B Versatile'],
                    ['value' => 'llama-3.1-8b-instant', 'label' => 'Llama 3.1 8B Instant'],
                ],
                'ollama' => [
                    ['value' => 'llama3.2', 'label' => 'llama3.2'],
                    ['value' => 'qwen2.5-coder', 'label' => 'qwen2.5-coder'],
                    ['value' => 'deepseek-r1', 'label' => 'deepseek-r1'],
                ],
                'xai' => [
                    ['value' => 'grok-4', 'label' => 'Grok 4'],
                    ['value' => 'grok-4-latest', 'label' => 'Grok 4 Latest'],
                ],
                'openrouter' => [
                    ['value' => 'openai/gpt-5-mini', 'label' => 'OpenAI GPT-5 Mini'],
                    ['value' => 'anthropic/claude-sonnet-4', 'label' => 'Anthropic Claude Sonnet 4'],
                    ['value' => 'google/gemini-2.5-pro', 'label' => 'Google Gemini 2.5 Pro'],
                ],
                default => [],
            },
        };
    }

    protected function modelValuesFor(string $capability, string $provider): array
    {
        return collect($this->modelOptionsFor($capability, $provider))
            ->pluck('value')
            ->all();
    }

    protected function syncModelSelection(string $modelProperty, string $capability, string $provider): void
    {
        $allowedValues = $this->modelValuesFor($capability, $provider);

        if ($allowedValues === []) {
            $this->{$modelProperty} = '';

            return;
        }

        if (! in_array($this->{$modelProperty}, $allowedValues, true)) {
            $this->{$modelProperty} = $allowedValues[0];
        }
    }

    public function updatedAiDefaultProvider(string $value): void
    {
        $this->syncModelSelection('ai_text_model', 'text', $value);
    }

    public function updatedAiContentProvider(string $value): void
    {
        $this->syncModelSelection('ai_content_model', 'content', $value);
    }

    public function updatedAiTextProvider(string $value): void
    {
        $this->syncModelSelection('ai_text_generation_model', 'text', $value);
    }

    public function updatedAiImageProvider(string $value): void
    {
        $this->syncModelSelection('ai_image_model', 'image', $value);
    }

    public function updatedAiVideoProvider(string $value): void
    {
        $this->syncModelSelection('ai_video_model', 'video', $value);
    }

    public function updatedAiChatProvider(string $value): void
    {
        $this->syncModelSelection('ai_chat_model', 'chat', $value);
    }

    public function updatedAiVoiceProvider(string $value): void
    {
        $this->syncModelSelection('ai_voice_model', 'voice', $value);
    }

    public function updatedAiCodeProvider(string $value): void
    {
        $this->syncModelSelection('ai_code_model', 'code', $value);
    }

    public function render(): View
    {
        return view('adminai::ai-config', [
            'toggleOptions' => $this->catalog->toggleOptions(),
            'providerOptions' => $this->catalog->providerOptions(),
            'defaultLanguageOptions' => $this->catalog->defaultLanguageOptions(),
            'toneOfVoiceOptions' => $this->catalog->toneOfVoiceOptions(),
            'creativityOptions' => $this->catalog->creativityOptions(),
            'imageProviderOptions' => $this->catalog->imageProviderOptions(),
            'videoProviderOptions' => $this->catalog->videoProviderOptions(),
            'voiceProviderOptions' => $this->catalog->voiceProviderOptions(),
            'defaultTextModelOptions' => $this->modelOptionsFor('text', $this->ai_default_provider),
            'embeddingModelOptions' => $this->catalog->embeddingModelOptions(),
            'contentModelOptions' => $this->modelOptionsFor('content', $this->ai_content_provider),
            'textGenerationModelOptions' => $this->modelOptionsFor('text', $this->ai_text_provider),
            'imageModelOptions' => $this->modelOptionsFor('image', $this->ai_image_provider),
            'videoModelOptions' => $this->modelOptionsFor('video', $this->ai_video_provider),
            'chatModelOptions' => $this->modelOptionsFor('chat', $this->ai_chat_provider),
            'voiceModelOptions' => $this->modelOptionsFor('voice', $this->ai_voice_provider),
            'codeModelOptions' => $this->modelOptionsFor('code', $this->ai_code_provider),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Settings'),
        ]);
    }
}
