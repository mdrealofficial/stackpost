@component(theme_view('layouts.app', 'app'), ['title' => __('Prompt History')])
    @php
        $activeModule = request('module', '');
        $search = request('q', '');
        $creditsLeft = ($creditSummary['unlimited'] ?? false) ? __('Unlimited') : number_format((int) ($creditSummary['remaining'] ?? 0));

        $formatValue = function ($value): string {
            if (is_array($value)) {
                return collect($value)
                    ->map(fn ($item) => is_scalar($item) || $item === null ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->filter()
                    ->implode(', ');
            }

            if (is_bool($value)) {
                return $value ? __('Yes') : __('No');
            }

            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };
    @endphp

    <div class="mx-auto max-w-[1320px] space-y-5 px-4 pb-8 pt-4 sm:px-5 xl:px-6">
        <x-ui.sub-header :compact="true" :title="__('Prompt History')" :description="__('Find, review and reuse your previous AI prompts.')"><x-slot:actions>
                        <x-ui.button :href="route('portal.ai-content')" wire:navigate>
                            <i class="fa-light fa-pen-nib"></i>
                            {{ __('Create content') }}
                        </x-ui.button>
                        <x-ui.button :href="route('portal.ai-studio.settings')" variant="outline" wire:navigate>
                            <i class="fa-light fa-sliders"></i>
                            {{ __('AI Settings') }}
                        </x-ui.button>
                    </x-slot:actions></x-ui.sub-header>

        <section class="rounded-[1rem] border p-4 sm:p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base); box-shadow: 0 18px 50px -44px rgba(15,23,42,0.34);">
            <form method="GET" action="{{ route('portal.ai-studio.prompt-history') }}" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_240px_auto] lg:items-end">
                <x-ui.input name="q" :label="__('Search')" :value="$search" :placeholder="__('Prompt title or content...')" />
                <x-ui.select name="module" :label="__('Module')">
                    <option value="">{{ __('All modules') }}</option>
                    @foreach ($modules as $key => $label)
                        <option value="{{ $key }}" @selected($activeModule === $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex gap-2">
                    <x-ui.button type="submit"><i class="fa-light fa-filter"></i>{{ __('Filter') }}</x-ui.button>
                    <x-ui.button :href="route('portal.ai-studio.prompt-history')" variant="outline" wire:navigate><i class="fa-light fa-rotate-left"></i>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </section>

        <section class="space-y-3">
            @forelse ($histories as $history)
                @php
                    $moduleLabel = $modules[$history->module] ?? ucfirst(str_replace('_', ' ', (string) $history->module));
                    $inputConfig = collect((array) $history->input_payload)
                        ->except(['generation_prompt'])
                        ->filter(fn ($value) => $formatValue($value) !== '');
                    $primaryConfig = $inputConfig->take(4);
                    $extraConfig = $inputConfig->slice(4);
                    $generationPrompt = (string) data_get($history->input_payload, 'generation_prompt', '');
                    $outputPreview = trim((string) json_encode($history->output_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                @endphp

                <article class="rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: var(--theme-surface-base); box-shadow: 0 18px 46px -44px rgba(15,23,42,0.42);">
                    <div class="grid grid-cols-1 gap-0 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <div class="min-w-0 p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $moduleLabel }}</span>
                                        @if ($history->language)
                                            <span class="rounded-full border px-2.5 py-1 text-xs" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); color: var(--theme-muted-text-color);">{{ strtoupper($history->language) }}</span>
                                        @endif
                                        @if ($history->tone)
                                            <span class="rounded-full border px-2.5 py-1 text-xs" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); color: var(--theme-muted-text-color);">{{ ucfirst($history->tone) }}</span>
                                        @endif
                                        <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ $history->created_at?->format('Y-m-d H:i') }}</span>
                                    </div>

                                    <h2 class="mt-3 text-base font-semibold leading-6 sm:text-lg" style="color: var(--theme-header-text-color);">{{ $history->title ?: __('Untitled prompt') }}</h2>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <x-ui.modal width="lg" :title="__('Rename prompt history')" :description="__('Update the label shown in your prompt archive without changing the saved prompt body.')">
                                        <x-slot:trigger>
                                            <x-ui.button type="button" variant="outline" size="sm"><i class="fa-light fa-pen"></i>{{ __('Rename') }}</x-ui.button>
                                        </x-slot:trigger>

                                        <form id="rename-prompt-history-{{ $history->id }}" method="POST" action="{{ route('portal.ai-studio.prompt-history.update', $history->id) }}" class="space-y-5">
                                            @csrf
                                            @method('PUT')
                                            <x-ui.input name="title" :label="__('Title')" :value="$history->title ?: __('Prompt history')" required />
                                        </form>

                                        <x-slot:footer>
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="submit" form="rename-prompt-history-{{ $history->id }}">{{ __('Save title') }}</x-ui.button>
                                        </x-slot:footer>
                                    </x-ui.modal>

                                    <x-ui.dialog :title="__('Delete this prompt history?')" :description="__('This permanently removes the saved prompt entry from your AI Studio history.')" width="sm" dismissible>
                                        <x-slot:trigger>
                                            <x-ui.button type="button" variant="danger" size="sm"><i class="fa-light fa-trash"></i>{{ __('Delete') }}</x-ui.button>
                                        </x-slot:trigger>

                                        <x-slot:footer>
                                            <div class="flex justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                <form method="POST" action="{{ route('portal.ai-studio.prompt-history.destroy', $history->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" variant="danger" x-on:click="open = false">{{ __('Delete prompt') }}</x-ui.button>
                                                </form>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                </div>
                            </div>

                            <div class="mt-4 rounded-[0.9rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--theme-body-bg) 4%);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Prompt') }}</p>
                                <p class="mt-3 max-h-44 overflow-y-auto whitespace-pre-line break-words pr-2 text-sm leading-7" style="color: var(--theme-header-text-color);">{{ $history->prompt }}</p>
                            </div>

                            @if ($generationPrompt !== '')
                                <details class="mt-3 rounded-[0.9rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background: color-mix(in srgb, var(--theme-surface-base) 98%, var(--theme-body-bg) 2%);">
                                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Generation prompt') }}</summary>
                                    <p class="mt-3 max-h-36 overflow-y-auto whitespace-pre-line break-words pr-2 text-xs leading-6" style="color: var(--theme-muted-text-color);">{{ $generationPrompt }}</p>
                                </details>
                            @endif
                        </div>

                        <aside class="min-w-0 border-t p-4 lg:border-l lg:border-t-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: color-mix(in srgb, var(--theme-surface-base) 98%, var(--theme-body-bg) 2%);">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Details') }}</p>
                                @if ($inputConfig->isNotEmpty())
                                    <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ $inputConfig->count() }} {{ __('items') }}</span>
                                @endif
                            </div>

                            @if ($primaryConfig->isNotEmpty())
                                <dl class="mt-3 divide-y rounded-[0.75rem] border px-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background: var(--theme-surface-base); --tw-divide-opacity: 1; divide-color: rgba(var(--theme-border-color-rgb), 0.42);">
                                    @foreach ($primaryConfig as $key => $value)
                                        <div class="flex gap-3 py-2.5 text-sm">
                                            <dt class="w-28 shrink-0 truncate text-xs font-semibold uppercase tracking-[0.1em]" style="color: var(--theme-muted-text-color);">{{ str($key)->replace('_', ' ')->title() }}</dt>
                                            <dd class="min-w-0 flex-1 truncate font-medium" style="color: var(--theme-header-text-color);">{{ $formatValue($value) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @else
                                <p class="mt-3 rounded-[0.75rem] border px-3 py-2.5 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background: var(--theme-surface-base); color: var(--theme-muted-text-color);">
                                    {{ __('No input config saved.') }}
                                </p>
                            @endif

                            @if ($extraConfig->isNotEmpty() || ($outputPreview !== '' && $outputPreview !== 'null'))
                                <details class="mt-3 rounded-[0.75rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background: var(--theme-surface-base);">
                                    <summary class="cursor-pointer text-[10px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ __('More details') }}</summary>
                                    @if ($extraConfig->isNotEmpty())
                                        <dl class="mt-2 divide-y" style="--tw-divide-opacity: 1; divide-color: rgba(var(--theme-border-color-rgb), 0.35);">
                                            @foreach ($extraConfig as $key => $value)
                                                <div class="flex gap-3 py-2 text-xs">
                                                    <dt class="w-28 shrink-0 truncate font-semibold uppercase tracking-[0.1em]" style="color: var(--theme-muted-text-color);">{{ str($key)->replace('_', ' ')->title() }}</dt>
                                                    <dd class="min-w-0 flex-1 truncate" style="color: var(--theme-header-text-color);">{{ $formatValue($value) }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @endif
                                    @if ($outputPreview !== '' && $outputPreview !== 'null')
                                        <p class="mt-2 max-h-24 overflow-y-auto break-words border-t pt-2 text-xs leading-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.35); color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit($outputPreview, 500) }}</p>
                                    @endif
                                </details>
                            @endif
                        </aside>
                    </div>
                </article>
            @empty
                <x-ui.empty
                    class="rounded-[1rem] border px-6 py-10 sm:px-7"
                    icon="fa-light fa-clock-rotate-left"
                    :title="__('No prompt history yet')"
                    :description="__('Generated prompts will appear here after you use AI Studio modules.')"
                />
            @endforelse
        </section>

        @if ($histories->hasPages())
            <div>
                {{ $histories->links() }}
            </div>
        @endif
    </div>
@endcomponent
