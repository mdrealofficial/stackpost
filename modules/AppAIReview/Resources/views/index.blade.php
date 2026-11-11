<x-ui.ai-workspace :title="__('AI Review')" :description="__('Get practical feedback before you publish.')" :input-title="__('Draft to review')" :input-description="__('Paste the content you want to improve.')" :output-title="__('Review feedback')" :output-description="__('See strengths, risks and suggested changes.')" action="review">
<x-slot:input><div>
                <x-ui.textarea
                    wire:model.defer="reviewDraft"
                    :label="__('Draft')"
                    :error="$errors->first('reviewDraft')"
                    rows="10"
                    placeholder="{{ __('Paste the draft to review...') }}"
                >{{ $reviewDraft }}</x-ui.textarea>
            </div>

            <div class="mt-4">
                <x-ui.language-select
                    wire:model.live="language"
                    :label="__('Output language')"
                    :preferred="['vi', 'en']"
                />
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.5);">
                <x-ui.button type="button" wire:click="review" wire:loading.attr="disabled" wire:target="review" :disabled="!($creditPreview['enough'] ?? true)">
                    <i class="fa-light fa-shield-check"></i>
                    <span wire:loading.remove wire:target="review">{{ __('Review draft') }}</span>
                    <span wire:loading wire:target="review">{{ __('Reviewing...') }}</span>
                </x-ui.button>

                <x-ui.ai-credit-summary :preview="$creditPreview" />
            </div>

            @if (!($creditPreview['enough'] ?? true))
                <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ __('Not enough credits remaining for this action.') }}</p>
                @include(theme_view('partials.credit-topup-cta', 'app'))
            @endif</x-slot:input>
<x-slot:resultActions>@if ($result)
                    <span class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em]" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                        {{ $result['score'] ?? 0 }}/100
                    </span>
                @endif</x-slot:resultActions>
<x-slot:results>@if ($result)
                <div class="rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background: var(--theme-surface-base);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $result['verdict'] ?? __('Review complete') }}</p>
                    <div class="mt-3 h-2.5 overflow-hidden" style="background-color: rgba(var(--theme-border-color-rgb), 0.18);">
                        <div class="h-full transition-all duration-500" style="width: {{ max(0, min(100, (int) ($result['score'] ?? 0))) }}%; background: linear-gradient(90deg, rgba(var(--theme-accent-rgb), 0.55), var(--theme-accent));"></div>
                    </div>
                    @if (!empty($result['final_tip']))
                        <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $result['final_tip'] }}</p>
                    @endif
                </div>

                <div class="grid gap-3">
                    @foreach (['strengths' => __('Strengths'), 'risks' => __('Risks'), 'fixes' => __('Fixes')] as $key => $label)
                        @if (!empty($result[$key]))
                            <x-ui.card padding="sm">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-accent);">{{ $label }}</p>
                                @foreach ($result[$key] as $line)
                                    <p class="mt-3 text-sm leading-7" style="color: var(--theme-header-text-color);">{{ $line }}</p>
                                @endforeach
                            </x-ui.card>
                        @endif
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="fa-light fa-clipboard-check" :title="__('Ready for a second look')" :description="__('Add your draft and select Review draft.')" />
            @endif</x-slot:results>
<x-slot:history>@if ($promptHistory->isNotEmpty())
        <x-ui.card padding="md">
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Prompt history') }}</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($promptHistory as $history)
                    <x-ui.button multiline type="button" variant="outline" wire:click="loadPromptHistory({{ $history->id }})" class="h-auto w-full whitespace-normal p-3 text-left"><span class="block min-w-0 w-full">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $history->title ?: __('Review prompt') }}</p>
                        <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit($history->prompt, 82) }}</p>
                    </span></x-ui.button>
                @endforeach
            </div>
        </x-ui.card>
    @endif</x-slot:history>
</x-ui.ai-workspace>
