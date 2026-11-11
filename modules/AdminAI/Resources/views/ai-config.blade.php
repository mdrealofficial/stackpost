<section class="w-full">
    @php
        $providerCredentialCards = [
            [
                'title' => __('OpenAI'),
                'tag' => __('Flagship'),
                'description' => __('Best fit for text, chat, coding, embeddings, image, and realtime audio.'),
                'fields' => [
                    ['label' => __('API key'), 'model' => 'ai_openai_api_key', 'type' => 'password', 'error' => 'ai_openai_api_key'],
                    ['label' => __('Base URL'), 'model' => 'ai_openai_url', 'type' => 'url', 'error' => 'ai_openai_url'],
                ],
            ],
            [
                'title' => __('Gemini'),
                'tag' => __('Multimodal'),
                'description' => __('Strong stack for image, voice, video, and multimodal reasoning.'),
                'fields' => [
                    ['label' => __('API key'), 'model' => 'ai_gemini_api_key', 'type' => 'password', 'error' => 'ai_gemini_api_key'],
                ],
            ],
            [
                'title' => __('Anthropic'),
                'tag' => __('Reasoning'),
                'description' => __('Claude-based long-context and structured reasoning workflows.'),
                'fields' => [
                    ['label' => __('API key'), 'model' => 'ai_anthropic_api_key', 'type' => 'password', 'error' => 'ai_anthropic_api_key'],
                ],
            ],
            [
                'title' => __('Groq'),
                'tag' => __('Speed'),
                'description' => __('Fast hosted inference for supported open-weight and routed models.'),
                'fields' => [
                    ['label' => __('API key'), 'model' => 'ai_groq_api_key', 'type' => 'password', 'error' => 'ai_groq_api_key'],
                ],
            ],
            [
                'title' => __('Ollama'),
                'tag' => __('Local'),
                'description' => __('Self-hosted or private local inference endpoints.'),
                'fields' => [
                    ['label' => __('API key'), 'model' => 'ai_ollama_api_key', 'type' => 'password', 'error' => 'ai_ollama_api_key'],
                    ['label' => __('Base URL'), 'model' => 'ai_ollama_url', 'type' => 'url', 'error' => 'ai_ollama_url'],
                ],
            ],
            [
                'title' => __('xAI / OpenRouter'),
                'tag' => __('Optional'),
                'description' => __('Alternative routing for Grok and multi-provider access.'),
                'fields' => [
                    ['label' => __('xAI API key'), 'model' => 'ai_xai_api_key', 'type' => 'password', 'error' => 'ai_xai_api_key'],
                    ['label' => __('OpenRouter API key'), 'model' => 'ai_openrouter_api_key', 'type' => 'password', 'error' => 'ai_openrouter_api_key'],
                ],
            ],
        ];

        $writingProfiles = [
            [
                'title' => __('Content'),
                'note' => __('Articles, blogs, long-form marketing copy'),
                'status' => 'ai_content_status',
                'provider' => 'ai_content_provider',
                'model' => 'ai_content_model',
                'providers' => $providerOptions,
                'models' => $contentModelOptions,
            ],
            [
                'title' => __('Text'),
                'note' => __('Rewrite, summarize, classify, enrich'),
                'status' => 'ai_text_status',
                'provider' => 'ai_text_provider',
                'model' => 'ai_text_generation_model',
                'providers' => $providerOptions,
                'models' => $textGenerationModelOptions,
            ],
            [
                'title' => __('Chat'),
                'note' => __('Assistants, copilots, threaded conversations'),
                'status' => 'ai_chat_status',
                'provider' => 'ai_chat_provider',
                'model' => 'ai_chat_model',
                'providers' => $providerOptions,
                'models' => $chatModelOptions,
            ],
            [
                'title' => __('Code'),
                'note' => __('Generation, debugging, refactoring'),
                'status' => 'ai_code_status',
                'provider' => 'ai_code_provider',
                'model' => 'ai_code_model',
                'providers' => $providerOptions,
                'models' => $codeModelOptions,
            ],
        ];

        $mediaProfiles = [
            [
                'title' => __('Image'),
                'note' => __('Generation, editing, thumbnails, product visuals'),
                'status' => 'ai_image_status',
                'provider' => 'ai_image_provider',
                'model' => 'ai_image_model',
                'providers' => $imageProviderOptions,
                'models' => $imageModelOptions,
            ],
            [
                'title' => __('Video'),
                'note' => __('Motion, cinematic previews, video generation'),
                'status' => 'ai_video_status',
                'provider' => 'ai_video_provider',
                'model' => 'ai_video_model',
                'providers' => $videoProviderOptions,
                'models' => $videoModelOptions,
            ],
            [
                'title' => __('Voice'),
                'note' => __('Realtime voice, TTS, transcription'),
                'status' => 'ai_voice_status',
                'provider' => 'ai_voice_provider',
                'model' => 'ai_voice_model',
                'providers' => $voiceProviderOptions,
                'models' => $voiceModelOptions,
            ],
        ];
    @endphp

    <x-settings.layout :heading="__('AI Settings')" :subheading="__('Configure providers, credentials, and workload routing without forcing one model stack to do everything.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="space-y-6">
                <x-theme.section-card
                    :title="__('AI Control Center')"
                    :description="__('Choose one strong default stack, keep provider keys organized, and route each workload only when it benefits from a different model family.')"
                    body-class="space-y-6 p-6"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                            {{ $ai_status === '1' ? __('AI infrastructure is live') : __('AI infrastructure is paused') }}
                        </h2>

                        <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background-color: rgba(var(--theme-primary-rgb), 0.12); color: var(--theme-primary-color);">
                            {{ $ai_status === '1' ? __('Live') : __('Paused') }}
                        </span>
                    </div>

                    <div class="rounded-[1.1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <x-ui.radio-group name="ai_status" wire:model.live="ai_status" :label="__('Global AI status')" :options="$toggleOptions" :value="$ai_status" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-[1.1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Default provider') }}</p>
                            <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ collect($providerOptions)->firstWhere('value', $ai_default_provider)['label'] ?? ucfirst($ai_default_provider) }}</p>
                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Primary stack for uncategorized tasks.') }}</p>
                        </div>

                        <div class="rounded-[1.1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Text baseline') }}</p>
                            <p class="mt-2 text-lg font-semibold break-all" style="color: var(--theme-header-text-color);">{{ $ai_text_model }}</p>
                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('General reasoning, writing, and fallback generation.') }}</p>
                        </div>

                        <div class="rounded-[1.1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Embeddings') }}</p>
                            <p class="mt-2 text-lg font-semibold break-all" style="color: var(--theme-header-text-color);">{{ $ai_embedding_model }}</p>
                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Search, similarity, retrieval, and indexing.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[0.72rem] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('General configuration') }}</p>
                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Set the default language, writing style, creativity level, and safe working limits for AI requests across the admin workspace.') }}</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <x-ai.language-field
                                name="ai_default_language"
                                wire:model.live="ai_default_language"
                                :label="__('Default Language')"
                                :error="$errors->first('ai_default_language')"
                                :preferred="['vi', 'en']"
                            />

                            <x-ai.tone-field
                                name="ai_default_tone_of_voice"
                                wire:model.live="ai_default_tone_of_voice"
                                :label="__('Default Tone Of Voice')"
                                :error="$errors->first('ai_default_tone_of_voice')"
                                :value="$ai_default_tone_of_voice"
                                :options="$toneOfVoiceOptions"
                            />

                            <x-ui.select wire:model.live="ai_default_creativity" :label="__('Default Creativity')" :error="$errors->first('ai_default_creativity')">
                                @foreach ($creativityOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                @endforeach
                            </x-ui.select>

                            <x-ui.input wire:model.live="ai_maximum_input_length" type="number" min="1" :label="__('Maximum Input Length')" :error="$errors->first('ai_maximum_input_length')" />

                            <x-ui.input wire:model.live="ai_maximum_output_length" type="number" min="1" :label="__('Maximum Output Length')" :error="$errors->first('ai_maximum_output_length')" />
                        </div>
                    </div>
                </x-theme.section-card>

            </div>

            <x-theme.section-card
                :title="__('Provider credentials')"
                :description="__('Only fill the providers you actually plan to route traffic to. Empty credentials can remain unused.')"
                body-class="space-y-6 p-6"
            >
                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach ($providerCredentialCards as $card)
                        <div class="rounded-[1.1rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $card['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $card['description'] }}</p>
                                </div>

                                <span class="rounded-full px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.18em]" style="background-color: rgba(var(--theme-primary-rgb), 0.12); color: var(--theme-primary-color);">
                                    {{ $card['tag'] }}
                                </span>
                            </div>

                            <div class="mt-5 grid gap-4">
                                @foreach ($card['fields'] as $field)
                                    <div class="space-y-2">
                                        <x-ui.label>{{ $field['label'] }}</x-ui.label>
                                        <x-ui.input
                                            wire:model.defer="{{ $field['model'] }}"
                                            :type="$field['type']"
                                            :error="$errors->first($field['error'])"
                                            autocomplete="new-password"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Capability routing')"
                :description="__('Route each workload to the provider and model that actually fits it, instead of overloading one model for every task.')"
                body-class="space-y-6 p-6"
            >
                <div class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-[0.72rem] font-semibold uppercase tracking-[0.28em]" style="color: var(--theme-muted-text-color);">{{ __('Writing and reasoning') }}</p>
                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Text-heavy workflows such as generation, summaries, chat assistants, and code tasks.') }}</p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[1.15rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <div class="grid grid-cols-[1.1fr_0.72fr_0.95fr_1.15fr] gap-4 border-b px-5 py-3.5 text-[0.72rem] font-semibold uppercase tracking-[0.24em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color); background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                            <span>{{ __('Workload') }}</span>
                            <span>{{ __('Status') }}</span>
                            <span>{{ __('Provider') }}</span>
                            <span>{{ __('Model') }}</span>
                        </div>

                        @foreach ($writingProfiles as $profile)
                            <div class="grid grid-cols-[1.1fr_0.72fr_0.95fr_1.15fr] gap-4 border-b px-5 py-4 last:border-b-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $profile['title'] }}</p>
                                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $profile['note'] }}</p>
                                </div>

                                <x-ui.select wire:model.live="{{ $profile['status'] }}" :label="false">
                                    @foreach ($toggleOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select wire:model.live="{{ $profile['provider'] }}" :label="false">
                                    @foreach ($profile['providers'] as $option)
                                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select wire:model.live="{{ $profile['model'] }}" :label="false">
                                    @foreach ($profile['models'] as $option)
                                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-4 pt-2">
                    <div>
                        <p class="text-[0.72rem] font-semibold uppercase tracking-[0.28em]" style="color: var(--theme-muted-text-color);">{{ __('Media generation') }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Image, video, and voice usually deserve their own provider path because the capability gap is wider across vendors.') }}</p>
                    </div>

                    <div class="overflow-hidden rounded-[1.15rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <div class="grid grid-cols-[1.1fr_0.72fr_0.95fr_1.15fr] gap-4 border-b px-5 py-3.5 text-[0.72rem] font-semibold uppercase tracking-[0.24em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color); background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                            <span>{{ __('Workload') }}</span>
                            <span>{{ __('Status') }}</span>
                            <span>{{ __('Provider') }}</span>
                            <span>{{ __('Model') }}</span>
                        </div>

                        @foreach ($mediaProfiles as $profile)
                            <div class="grid grid-cols-[1.1fr_0.72fr_0.95fr_1.15fr] gap-4 border-b px-5 py-4 last:border-b-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $profile['title'] }}</p>
                                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $profile['note'] }}</p>
                                </div>

                                <x-ui.select wire:model.live="{{ $profile['status'] }}" :label="false">
                                    @foreach ($toggleOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select wire:model.live="{{ $profile['provider'] }}" :label="false">
                                    @foreach ($profile['providers'] as $option)
                                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select wire:model.live="{{ $profile['model'] }}" :label="false">
                                    @foreach ($profile['models'] as $option)
                                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-theme.section-card>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
