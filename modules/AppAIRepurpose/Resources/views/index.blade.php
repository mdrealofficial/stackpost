<x-ui.ai-workspace :title="__('Repurpose')" :description="__('Adapt one source into captions for your selected platforms.')" :input-title="__('Source content')" :input-description="__('Paste a draft, article excerpt or campaign message.')" :output-title="__('Adapted captions')" :output-description="__('Review each version before saving it to Captions.')" action="repurpose">
<x-slot:input><div>
                <x-ui.textarea
                    wire:model.defer="sourceContent"
                    :label="__('Source')"
                    :error="$errors->first('sourceContent')"
                    rows="10"
                    placeholder="{{ __('Paste the original content you want to adapt...') }}"
                >{{ $sourceContent }}</x-ui.textarea>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <x-ui.select wire:model.live="tone" :label="__('Tone')">
                    @foreach ($toneOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.language-select
                    wire:model.live="language"
                    :label="__('Language')"
                    :preferred="['vi', 'en']"
                />
            </div>

            <div class="mt-4">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Target platforms') }}</p>
                @error('selectedPlatforms')<p class="mt-2 text-sm" style="color:var(--theme-danger-color)">{{ $message }}</p>@enderror
                @if (!empty($platformOptions))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($platformOptions as $platform)
                            <x-ui.checkbox wire:model.live="selectedPlatforms" :value="$platform['value']" :label="$platform['label']" :id="'repurpose-platform-'.$platform['value']" :minimal="true" class="rounded-lg border p-3" />
                        @endforeach
                    </div>
                @else
                    <div class="mt-3 rounded-[0.85rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%); color: var(--theme-muted-text-color);">
                        {{ __('Connect social channels first to choose output destinations.') }}
                    </div>
                @endif
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.5);">
                <x-ui.button type="button" wire:click="repurpose" wire:loading.attr="disabled" wire:target="repurpose" :disabled="!($creditPreview['enough'] ?? true)">
                    <i class="fa-light fa-code-branch"></i>
                    <span wire:loading.remove wire:target="repurpose">{{ __('Repurpose content') }}</span>
                    <span wire:loading wire:target="repurpose">{{ __('Repurposing...') }}</span>
                </x-ui.button>

                <x-ui.ai-credit-summary :preview="$creditPreview" />
            </div>

            @if (!($creditPreview['enough'] ?? true))
                <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ __('Not enough credits remaining for this action.') }}</p>
                @include(theme_view('partials.credit-topup-cta', 'app'))
            @endif</x-slot:input>
<x-slot:resultActions>@if ($result)
                    <x-ui.button type="button" size="sm" variant="outline" wire:click="saveAllResults" wire:loading.attr="disabled" wire:target="saveAllResults" :disabled="empty($result['items'] ?? [])">
                        <i class="fa-light fa-books"></i>
                        <span wire:loading.remove wire:target="saveAllResults">{{ __('Save all') }}</span>
                        <span wire:loading wire:target="saveAllResults">{{ __('Saving...') }}</span>
                    </x-ui.button>
                @endif</x-slot:resultActions>
<x-slot:results>@if ($result)
                <div class="grid gap-3">
                    @foreach (($result['items'] ?? []) as $item)
                        <x-ui.card padding="sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['title'] ?: strtoupper((string) $item['target']) }}</p>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ strtoupper((string) $item['target']) }} &middot; {{ $item['format'] }}</p>
                                </div>
                                @if (in_array($loop->index, $savedResultIndexes ?? [], true))
                                    <x-ui.badge variant="success">{{ __('Saved') }}</x-ui.badge>
                                @endif
                            </div>

                            <p class="mt-3 text-sm leading-7 whitespace-pre-line" style="color: var(--theme-header-text-color);">{{ $item['caption'] }}</p>
                            @if (!empty($item['notes']))
                                <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $item['notes'] }}</p>
                            @endif

                            <div class="mt-4">
                                <x-ui.button
                                    type="button"
                                    size="sm"
                                    variant="{{ in_array($loop->index, $savedResultIndexes ?? [], true) ? 'outline' : 'secondary' }}"
                                    wire:click="saveResultCaption({{ $loop->index }})"
                                    wire:loading.attr="disabled"
                                    wire:target="saveResultCaption({{ $loop->index }})"
                                    :disabled="in_array($loop->index, $savedResultIndexes ?? [], true)"
                                >
                                    <i class="fa-light fa-book-bookmark"></i>
                                    <span wire:loading.remove wire:target="saveResultCaption({{ $loop->index }})">
                                        {{ in_array($loop->index, $savedResultIndexes ?? [], true) ? __('Saved to Captions') : __('Save to Captions') }}
                                    </span>
                                    <span wire:loading wire:target="saveResultCaption({{ $loop->index }})">{{ __('Saving...') }}</span>
                                </x-ui.button>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="fa-light fa-repeat" :title="__('No adapted captions yet')" :description="__('Add your source, choose platforms and select Repurpose content.')" />
            @endif</x-slot:results>
<x-slot:history>@if ($promptHistory->isNotEmpty())
        <x-ui.card padding="md">
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Prompt history') }}</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($promptHistory as $history)
                    <x-ui.button multiline type="button" variant="outline" wire:click="loadPromptHistory({{ $history->id }})" class="h-auto w-full whitespace-normal p-3 text-left"><span class="block min-w-0 w-full">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $history->title ?: __('Repurpose prompt') }}</p>
                        <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit($history->prompt, 82) }}</p>
                    </span></x-ui.button>
                @endforeach
            </div>
        </x-ui.card>
    @endif</x-slot:history>
</x-ui.ai-workspace>
