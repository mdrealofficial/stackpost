@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name' => null,
    'value' => null,
    'placeholder' => null,
    'options' => [],
    'searchable' => false,
    'searchPlaceholder' => null,
])

@php
    $normalizedOptions = collect($options)->map(function ($option) {
        if (is_string($option) || is_numeric($option)) {
            return [
                'value' => (string) $option,
                'label' => (string) $option,
                'description' => null,
                'avatarUrl' => null,
                'icon' => null,
                'disabled' => false,
            ];
        }

        return [
            'value' => (string) ($option['value'] ?? ''),
            'label' => (string) ($option['label'] ?? ($option['value'] ?? '')),
            'description' => $option['description'] ?? null,
            'avatarUrl' => trim((string) ($option['avatarUrl'] ?? ($option['avatar_url'] ?? ''))) ?: null,
            'icon' => trim((string) ($option['icon'] ?? '')) ?: null,
            'disabled' => (bool) ($option['disabled'] ?? false),
        ];
    })->values();

    $initialValue = old($name ?? '', $value);
    $initialLabel = $normalizedOptions->firstWhere('value', (string) $initialValue)['label']
        ?? $placeholder
        ?? __('Select');
@endphp

<x-ui.field :label="$label" :help="$help" :error="$error" {{ $attributes->except(['class'])->merge(['class' => '']) }}>
    <div
        x-data="{
            open: false,
            value: @js((string) $initialValue),
            label: @js($initialLabel),
            search: '',
            searchable: @js((bool) $searchable),
            options: @js($normalizedOptions->all()),
            filteredOptions() {
                const query = String(this.search || '').trim().toLowerCase();

                if (!this.searchable || query === '') {
                    return this.options;
                }

                return this.options.filter((option) => {
                    return String(option.label || '').toLowerCase().includes(query)
                        || String(option.description || '').toLowerCase().includes(query)
                        || String(option.value || '').toLowerCase().includes(query);
                });
            },
            initials(label) {
                return String(label || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part.charAt(0))
                    .join('')
                    .toUpperCase();
            },
            choose(option) {
                if (option.disabled) return;
                this.value = option.value;
                this.label = option.label;
                this.search = '';
                this.open = false;
                this.$refs.input.value = option.value;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                window.dispatchEvent(new CustomEvent('select-menu:change', {
                    detail: {
                        name: @js($name),
                        value: option.value,
                        option: option,
                    },
                }));
            },
            currentOption() {
                return this.options.find(option => option.value === this.value) || null;
            },
            toggle() {
                this.open = !this.open;

                if (this.open && this.searchable) {
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            },
        }"
        class="relative"
        x-on:keydown.escape.window="open = false"
    >
        @if ($name)
            <input x-ref="input" type="hidden" name="{{ $name }}" value="{{ $initialValue }}">
        @endif

        <button
            type="button"
            class="flex h-11 w-full items-center justify-between rounded-[0.8rem] border px-3 text-left text-sm outline-none transition duration-200 focus:border-[var(--theme-accent)] focus:ring-2 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
            style="border-radius: var(--theme-input-radius, 0.65rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            x-bind:aria-expanded="open"
            x-on:click="toggle()"
        >
            <span class="flex min-w-0 items-center gap-3">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-[0.85rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.56); background-color: var(--theme-surface-soft);">
                    <template x-if="currentOption()?.avatarUrl">
                        <img
                            x-bind:src="currentOption().avatarUrl"
                            alt=""
                            class="h-full w-full object-cover"
                            x-on:error="currentOption().avatarUrl = ''"
                        >
                    </template>

                    <template x-if="!currentOption()?.avatarUrl && currentOption()?.icon">
                        <i x-bind:class="currentOption().icon" class="text-sm" style="color: var(--theme-accent);"></i>
                    </template>

                    <template x-if="!currentOption()?.avatarUrl && !currentOption()?.icon">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-accent);" x-text="initials(currentOption()?.label || label)"></span>
                    </template>
                </span>

                <span class="min-w-0">
                    <span class="block truncate text-[13px] font-semibold leading-5" x-text="label" x-bind:class="value === '' ? 'opacity-60' : ''"></span>
                    <template x-if="currentOption()?.description">
                        <span class="mt-0.5 block truncate text-[10px] leading-4" style="color: var(--theme-muted-text-color);" x-text="currentOption().description"></span>
                    </template>
                </span>
            </span>

            <span class="ml-4 inline-flex shrink-0 items-center" x-bind:class="open ? 'rotate-180' : ''" style="color: var(--theme-muted-text-color); transition: transform 160ms ease;">
                <i class="fa-light fa-chevron-down text-xs"></i>
            </span>
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-140"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            x-on:click.outside="open = false"
            class="absolute left-0 right-0 top-[calc(100%+0.55rem)] z-[90] overflow-hidden rounded-[1rem] border bg-white p-1.5 shadow-[0_24px_70px_-28px_rgba(15,23,42,0.32)] dark:bg-slate-900"
            style="border-color: var(--theme-shell-border-color);"
        >
            <div x-show="searchable" class="p-1.5">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 inline-flex items-center" style="color: var(--theme-muted-text-color);">
                        <i class="fa-light fa-magnifying-glass text-xs"></i>
                    </span>
                    <input
                        x-ref="searchInput"
                        x-model="search"
                        type="search"
                        class="h-10 w-full rounded-[0.75rem] border pl-9 pr-3 text-sm outline-none transition focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                        style="border-color: rgba(var(--theme-border-color-rgb),0.68); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                        placeholder="{{ $searchPlaceholder ?: __('Search...') }}"
                        x-on:keydown.stop
                    >
                </div>
            </div>
            <div class="max-h-72 overflow-y-auto">
                <template x-for="option in filteredOptions()" :key="option.value">
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-3 rounded-[0.8rem] px-3 py-2.5 text-left transition"
                        x-bind:class="option.disabled
                            ? 'cursor-not-allowed opacity-45'
                            : value === option.value
                                ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/90'"
                        x-on:click="choose(option)"
                    >
                        <span class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-[1rem] border"
                                x-bind:class="value === option.value ? 'border-white/20 bg-white/10 dark:border-slate-700 dark:bg-slate-900' : ''"
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: var(--theme-surface-soft);">
                                <template x-if="option.avatarUrl">
                                    <img
                                        x-bind:src="option.avatarUrl"
                                        alt=""
                                        class="h-full w-full object-cover"
                                        x-on:error="option.avatarUrl = ''"
                                    >
                                </template>

                                <template x-if="!option.avatarUrl && option.icon">
                                    <i x-bind:class="option.icon" class="text-sm" x-bind:style="value === option.value ? 'color: white;' : 'color: var(--theme-accent);'"></i>
                                </template>

                                <template x-if="!option.avatarUrl && !option.icon">
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" x-bind:class="value === option.value ? 'text-white dark:text-slate-900' : ''" x-bind:style="value === option.value ? '' : 'color: var(--theme-accent);'" x-text="initials(option.label)"></span>
                                </template>
                            </span>

                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold" x-text="option.label"></span>
                                <template x-if="option.description">
                                    <span class="mt-0.5 block truncate text-[11px]" x-bind:class="value === option.value ? 'text-white/72 dark:text-slate-600' : 'text-slate-400 dark:text-slate-500'" x-text="option.description"></span>
                                </template>
                            </span>
                        </span>
                        <span class="mt-0.5 shrink-0 text-[12px]" x-show="value === option.value">
                            <i class="fa-light fa-check"></i>
                        </span>
                    </button>
                </template>

                <div x-show="filteredOptions().length === 0" class="px-3 py-7 text-center text-sm" style="color: var(--theme-muted-text-color);">
                    {{ __('No results found') }}
                </div>
            </div>
        </div>
    </div>
</x-ui.field>
