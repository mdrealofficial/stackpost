@props([
    'label' => null,
    'checked' => false,
    'description' => null,
    'id' => null,
    'minimal' => false,
    'labelClass' => '',
])

@php
    $checkboxId = $id ?: ($attributes->get('name')
        ? $attributes->get('name').'-'.\Illuminate\Support\Str::slug((string) $attributes->get('value', 'checkbox'))
        : 'checkbox-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10)));
@endphp

<label for="{{ $checkboxId }}" {{ $attributes->only('class')->class($minimal ? 'group inline-flex cursor-pointer items-center gap-2' : 'group inline-flex cursor-pointer items-start gap-3') }}>
    <input
        id="{{ $checkboxId }}"
        type="checkbox"
        @checked($checked)
        {{ $attributes->except('class')->class('peer sr-only') }}
    >

    <span class="{{ $minimal ? '' : 'mt-0.5 ' }}inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-[0.4rem] border border-slate-300 bg-white text-white shadow-[0_1px_2px_rgba(15,23,42,0.06)] transition-all duration-150 group-hover:border-[color:rgba(var(--theme-accent-rgb),0.45)] peer-checked:border-[color:var(--theme-accent)] peer-checked:bg-[color:var(--theme-accent)] peer-checked:text-white peer-checked:shadow-[0_0_0_3px_rgba(var(--theme-accent-rgb),0.14)] peer-checked:[&_svg]:opacity-100 dark:border-slate-700 dark:bg-slate-950">
        <svg viewBox="0 0 16 16" aria-hidden="true" class="h-3.5 w-3.5 opacity-0 transition-opacity duration-150" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2">
            <path d="M3.5 8.5 6.5 11.5 12.5 4.5" />
        </svg>
    </span>

    @if ($label || trim((string) $slot) !== '' || $description)
        <span class="min-w-0 {{ $minimal ? '' : 'pt-0.5' }}">
            @if ($label)
                <span @class(['block text-sm font-medium', $labelClass]) style="color: var(--theme-header-text-color);">{{ $label }}</span>
            @elseif (trim((string) $slot) !== '')
                <span @class(['block text-sm font-medium', $labelClass]) style="color: var(--theme-header-text-color);">{{ $slot }}</span>
            @endif

            @if ($description)
                <span class="mt-1 block text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</span>
            @endif
        </span>
    @endif
</label>
