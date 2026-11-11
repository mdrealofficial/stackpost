@php
    $languages = available_languages();
@endphp

@if ($languages->isNotEmpty())
    @php
        $activeLanguage = $languages->firstWhere('code', app()->getLocale()) ?? $languages->first();
    @endphp
    <div x-data="{ open: false }" class="relative">
        <button
            x-on:click="open = !open"
            x-on:click.outside="open = false"
            type="button" aria-label="{{ __('Choose language') }}" x-bind:aria-expanded="open" x-on:keydown.escape.window="open = false"
            class="inline-flex h-11 items-center gap-2 rounded-full border px-3 text-sm font-medium transition"
            style="border-color: rgba(var(--theme-border-color-rgb), 0.88); background: rgba(var(--theme-surface-bg-rgb), 0.82); color: var(--theme-header-text-color);"
        >
            <span class="{{ language_flag_class($activeLanguage ?? app()->getLocale()) }} rounded-sm text-[18px]"></span>
            <span>{{ strtoupper((string) ($activeLanguage->code ?? app()->getLocale())) }}</span>
            <i class="fa-light fa-chevron-down text-xs" style="color: var(--theme-muted-text-color);"></i>
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            class="guest-marketing-language-dropdown absolute right-0 top-full z-30 mt-3 w-56 overflow-hidden rounded-[1.4rem] border p-2 shadow-[0_30px_80px_-34px_rgba(15,23,42,0.32)] backdrop-blur-xl"
            style="border-color: var(--theme-border-color); background: var(--theme-surface-overlay);"
        >
            @foreach ($languages as $language)
                @php
                    $code = strtolower((string) $language->code);
                    $label = $language->name ?? strtoupper($code);
                    $isActiveLanguage = app()->getLocale() === $language->code;
                @endphp
                <a
                    href="{{ route('language.switch', $language->code) }}"
                    class="guest-marketing-language-option no-theme-link flex items-center gap-3 rounded-[1rem] px-3 py-3 text-sm font-medium transition {{ $isActiveLanguage ? 'bg-emerald-500 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]' : '' }}"
                    @unless($isActiveLanguage)
                        style="color: var(--theme-header-text-color);"
                    @endunless
                >
                    <span class="{{ language_flag_class($language) }} rounded-sm text-[18px]"></span>
                    <span class="flex-1">{{ $label }}</span>
                    @if ($isActiveLanguage)
                        <i class="fa-light fa-check text-xs"></i>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
@endif
