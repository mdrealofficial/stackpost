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
            type="button"
            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-2.5 text-sm font-medium text-white/88 transition hover:bg-white/[0.05]"
        >
            <span class="{{ language_flag_class($activeLanguage ?? app()->getLocale()) }} rounded-sm text-[18px]"></span>
            <i class="fa-light fa-chevron-down text-xs text-white/44"></i>
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            class="guest-marketing-language-dropdown absolute right-0 top-full z-30 mt-3 w-56 overflow-hidden rounded-[1.4rem] border border-white/10 bg-[linear-gradient(180deg,rgba(16,18,32,0.99)_0%,rgba(9,12,24,0.98)_100%)] p-2 shadow-[0_30px_80px_-34px_rgba(0,0,0,0.9)] backdrop-blur-xl"
        >
            @foreach ($languages as $language)
                @php
                    $code = strtolower((string) $language->code);
                    $label = $language->name ?? strtoupper($code);
                    $isActiveLanguage = app()->getLocale() === $language->code;
                @endphp
                <a
                    href="{{ route('language.switch', $language->code) }}"
                    class="guest-marketing-language-option no-theme-link {{ $isActiveLanguage ? 'bg-emerald-500 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]' : 'text-white/86 hover:bg-white/[0.05] hover:text-white' }} flex items-center gap-3 rounded-[1rem] px-3 py-3 text-sm font-medium transition"
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
