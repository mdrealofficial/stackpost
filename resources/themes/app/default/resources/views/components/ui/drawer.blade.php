@props([
    'title' => null,
    'description' => null,
    'side' => 'right',
    'width' => 'md',
])

@php
    $widths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
    ];
    $position = $side === 'left' ? 'justify-start' : 'justify-end';
    $translate = $side === 'left' ? '-translate-x-full' : 'translate-x-full';
@endphp

<div x-data="{ open: false }" {{ $attributes }}>
    <div x-on:click="open = true">
        {{ $trigger ?? '' }}
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="open" x-trap.inert.noscroll="open" tabindex="-1" role="dialog" aria-modal="true" aria-label="{{ $title ?: __('File menu') }}" class="fixed inset-0 z-[120] flex {{ $position }}" x-on:keydown.escape.stop.prevent="open = false">
            <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" x-on:click="open = false"></div>

            <aside
                x-show="open"
                x-transition:enter="transform transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="{{ $translate }}"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="{{ $translate }}"
                class="relative flex h-full min-h-0 flex-col w-full {{ $widths[$width] ?? $widths['md'] }} border-l shadow-xl"
                style="border-color: var(--theme-border-color); background-color: var(--theme-surface-overlay);"
            >
                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4" style="border-color: var(--theme-border-color);">
                    <div>
                        @if ($title)
                            <h3 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $title }}</h3>
                        @endif
                        @if ($description)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
                        @endif
                    </div>

                    <x-ui.button type="button" variant="ghost" size="sm" aria-label="{{ __('Close menu') }}" class="shrink-0" x-on:click="open = false">
                        <i aria-hidden="true" class="fa-light fa-xmark text-lg"></i>
                    </x-ui.button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5">
                    {{ $slot }}
                </div>
            </aside>
        </div>
    </template>
</div>
