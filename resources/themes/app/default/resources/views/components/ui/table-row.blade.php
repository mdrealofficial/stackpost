<tr {{ $attributes->merge(['style' => '--table-row-hover: var(--theme-table-row-hover-bg); color: var(--theme-header-text-color);'])->class('text-sm transition-[background-color,transform] duration-150 hover:bg-[var(--table-row-hover)]') }}>
    {{ $slot }}
</tr>
