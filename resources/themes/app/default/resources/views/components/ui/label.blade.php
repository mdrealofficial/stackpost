<label {{ $attributes->merge(['style' => 'color: var(--theme-header-text-color);'])->class('block text-sm font-medium') }}>
    {{ $slot }}
</label>
