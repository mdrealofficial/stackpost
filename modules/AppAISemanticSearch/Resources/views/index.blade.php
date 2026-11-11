<x-ui.ai-workspace :title="__('Search content')" :description="__('Find related captions and posts in your workspace.')" :input-title="__('What are you looking for?')" :input-description="__('Describe the topic or idea you want to find.')" :output-title="__('Related content')" :output-description="__('Matches from your saved captions and publishing posts.')" action="search">
<x-slot:input><form wire:submit.prevent="search" class="mt-5 space-y-4">
                <x-ui.input
                    wire:model.defer="searchQuery"
                    :label="__('Search intent')"
                    :error="$errors->first('searchQuery')"
                    :placeholder="__('Launch offer, product education, customer win, reminder...')"
                />

                <div class="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.5);">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="search" :disabled="!($creditPreview['enough'] ?? true)">
                        <i class="fa-light fa-magnifying-glass"></i>
                        <span wire:loading.remove wire:target="search">{{ __('Search') }}</span>
                        <span wire:loading wire:target="search">{{ __('Searching...') }}</span>
                    </x-ui.button>

                    <x-ui.ai-credit-summary :preview="$creditPreview" />
                </div>
            </form>

            @if (!($creditPreview['enough'] ?? true))
                <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ __('Not enough credits remaining for this action.') }}</p>
                @include(theme_view('partials.credit-topup-cta', 'app'))
            @endif</x-slot:input>
<x-slot:resultActions>@if ($result)
                    <span class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em]" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                        {{ count($result) }} {{ __('matches') }}
                    </span>
                @endif</x-slot:resultActions>
<x-slot:results>@if ($result)
                <div class="grid max-h-[35rem] gap-3 overflow-y-auto pr-1">
                    @foreach ($result as $item)
                        @php
                            $type = (string) ($item['type'] ?? 'content');
                            $score = (int) ($item['score'] ?? 0);
                            $tags = array_slice((array) ($item['tags'] ?? []), 0, 4);
                            $meta = (array) ($item['meta'] ?? []);
                        @endphp
                        <x-ui.card padding="sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['title'] ?? __('Untitled content') }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                                        <span class="rounded-full border px-2.5 py-1 font-semibold uppercase tracking-[0.12em]" style="border-color: rgba(var(--theme-accent-rgb), 0.22); background-color: rgba(var(--theme-accent-rgb), 0.07); color: var(--theme-accent);">{{ $type }}</span>
                                        @foreach (array_filter($meta) as $value)
                                            <span>{{ $value }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $score }}</span>
                            </div>
                            <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $item['excerpt'] ?? '' }}</p>
                            @if ($tags)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($tags as $tag)
                                        <span class="rounded-full px-2.5 py-1 text-xs" style="background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="fa-light fa-magnifying-glass" :title="$hasSearched ? __('No matching content') : __('Find content you can reuse')" :description="$hasSearched ? __('Try a broader topic or a phrase used in your saved captions and posts.') : __('Enter a topic or idea and select Search.')" />
            @endif</x-slot:results>
</x-ui.ai-workspace>
