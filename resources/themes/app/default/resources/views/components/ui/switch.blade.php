@props([
    'name' => null,
    'checked' => false,
    'value' => '1',
    'label' => null,
])

<label class="inline-flex items-center gap-3">
    @if ($name)
        <input type="hidden" name="{{ $name }}" value="0">
    @endif
    <span
        x-data="{ on: @js((bool) $checked) }"
        class="inline-flex items-center gap-3"
    >
        @if ($name)
            <input type="checkbox" name="{{ $name }}" value="{{ $value }}" class="sr-only" x-model="on">
        @endif
        <button
            type="button"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition"
            x-bind:class="on ? 'bg-[var(--theme-accent)]' : 'bg-slate-200 dark:bg-slate-700'"
            x-on:click.prevent="on = !on"
            role="switch"
            x-bind:aria-checked="on"
        >
            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition" x-bind:class="on ? 'translate-x-5' : 'translate-x-0.5'"></span>
        </button>
        @if ($label)
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</span>
        @endif
    </span>
</label>
