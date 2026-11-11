@props([
    'head' => false,
])

@if ($head)
    <th {{ $attributes->merge(['style' => 'border-color: var(--theme-border-color); color: var(--theme-muted-text-color);'])->class('border-b px-4 py-3 align-middle text-xs font-semibold first:pl-5 last:pr-5') }}>
        {{ $slot }}
    </th>
@else
    <td {{ $attributes->merge(['style' => 'border-color: var(--theme-border-color);'])->class('border-b px-4 py-3 align-middle first:pl-5 last:pr-5') }}>
        {{ $slot }}
    </td>
@endif
