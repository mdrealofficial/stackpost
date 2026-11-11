@props([
    'label' => null,
    'help' => null,
    'error' => null,
])
@php
    $controlId = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::uuid();
    $hintId = $controlId.'-hint';
@endphp


<div {{ $attributes->only('class')->class('space-y-2.5') }}>
    @if ($label)
        <x-ui.label :for="$controlId">{{ $label }}</x-ui.label>
    @endif

    <textarea {{ $attributes->merge(['id' => $controlId, 'aria-invalid' => $error ? 'true' : null, 'aria-describedby' => ($error || $help) ? $hintId : null, 'style' => 'border-radius: var(--theme-input-radius, 0.75rem); border-width: var(--theme-control-border-width, 1px); border-color: var(--theme-control-border-color, var(--theme-border-color)); background-color: var(--theme-input-surface); color: var(--theme-input-text);'])->except('class')->class('app-ui-control flex min-h-32 w-full border px-4 py-3.5 text-sm leading-6 shadow-none outline-none transition duration-150 placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-2 focus:ring-[color:rgba(var(--theme-accent-rgb),0.12)]') }}>{{ $slot }}</textarea>

    @if ($error)
        <p id="{{ $hintId }}" class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $error }}</p>
    @elseif ($help)
        <p id="{{ $hintId }}" class="text-sm" style="color: var(--theme-muted-text-color);">{{ $help }}</p>
    @endif
</div>
