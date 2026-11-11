@props([
    'title' => null,
    'description' => null,
    'width' => 'md',
    'dismissible' => true,
    'closeOnBackdrop' => true,
    'closeOnEscape' => true,
    'initiallyOpen' => false,
    'bodyClass' => '',
    'footerClass' => '',
    'openEvent' => null,
    'closeEvent' => null,
])

@php
    $widths = [
        'sm' => 'max-w-[26rem]',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];

    $hasBodyContent = trim((string) $slot) !== '';
    $hasFooter = isset($footer) && trim((string) $footer) !== '';
@endphp

<div
    x-data="{ open: @js((bool) $initiallyOpen) }"
    x-id="['modal-title', 'modal-description']"
    @if ($openEvent)
        x-on:{{ $openEvent }}.window="open = true"
    @endif
    @if ($closeEvent)
        x-on:{{ $closeEvent }}.window="open = false"
    @endif
    {{ $attributes }}
>
    @if (isset($trigger))
        <div x-on:click="open = true">
            {{ $trigger }}
        </div>
    @endif

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-trap.inert.noscroll="open"
            tabindex="-1"
            @if ($title)
                x-bind:aria-labelledby="$id('modal-title')"
            @endif
            @if ($description)
                x-bind:aria-describedby="$id('modal-description')"
            @endif
            role="dialog" aria-modal="true" aria-label="{{ $title ?: __('Dialog') }}" class="sp-modal fixed inset-0 z-[160] flex items-center justify-center p-4 sm:p-6"
            x-on:keydown.escape.stop.prevent="@if($closeOnEscape) open = false @endif"
        >
            <div aria-hidden="true" class="absolute inset-0 bg-slate-950/35" x-on:click="@if($closeOnBackdrop) open = false @endif"></div>

            <div x-show="open" x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2" class="relative min-w-0 w-full {{ $widths[$width] ?? $widths['md'] }}">
                <div class="flex min-h-0 flex-col overflow-hidden border shadow-xl" style="max-height:calc(100dvh - 3rem);border-radius:var(--theme-card-radius, 1rem);border-color:var(--theme-border-color);background-color:var(--theme-surface-overlay);">
                    @if ($title || $description)
                        <div class="flex shrink-0 items-start justify-between gap-4 px-5 py-4 sm:px-6 sm:py-5">
                            <div class="min-w-0">
                                @if ($title)
                                    <h3 x-bind:id="$id('modal-title')" class="text-base font-semibold tracking-tight" style="color:var(--theme-header-text-color)">{{ $title }}</h3>
                                @endif
                                @if ($description)
                                    <p x-bind:id="$id('modal-description')" class="mt-1 text-sm leading-6" style="color:var(--theme-muted-text-color)">{{ $description }}</p>
                                @endif
                            </div>

                            @if ($dismissible)
                                <x-ui.button type="button" variant="ghost" size="sm" aria-label="{{ __('Close dialog') }}" class="shrink-0" x-on:click="open = false">
                                    <i aria-hidden="true" class="fa-light fa-xmark text-lg"></i>
                                </x-ui.button>
                            @endif
                        </div>
                    @endif

                    @if ($hasBodyContent)
                        <div class="min-h-0 overflow-y-auto overscroll-contain border-t px-5 py-5 sm:px-6 {{ $bodyClass }}" style="border-color:var(--theme-border-color);">
                            {{ $slot }}
                        </div>
                    @endif

                    @if ($hasFooter)
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-3 border-t px-5 py-4 sm:px-6 {{ $footerClass }}" style="border-color:var(--theme-border-color);background:var(--theme-surface-soft);">
                            {{ $footer }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
