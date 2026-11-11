@props(['min' => 0, 'max' => 100, 'step' => 1])
<input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
    {{ $attributes->merge(['style' => 'accent-color: var(--theme-accent);'])->class('app-ui-range h-2 w-full cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--theme-accent)] disabled:cursor-not-allowed disabled:opacity-50') }}>
