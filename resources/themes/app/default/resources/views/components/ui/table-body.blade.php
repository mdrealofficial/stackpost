<tbody {{ $attributes->merge(['style' => 'border-color: var(--theme-border-color);'])->class('divide-y') }}>
    {{ $slot }}
</tbody>
