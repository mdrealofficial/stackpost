<thead style="background-color: var(--theme-table-head-bg);">
    <tr {{ $attributes->merge(['style' => 'color: var(--theme-muted-text-color);'])->class('text-left text-xs font-semibold') }}>
        {{ $slot }}
    </tr>
</thead>
