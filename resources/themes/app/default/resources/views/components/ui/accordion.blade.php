@props([
    'items' => [],
    'multiple' => false,
    'defaultOpen' => [],
])

@php
    $defaultKeys = collect($defaultOpen)->map(fn ($value) => (string) $value)->values();
@endphp

<div
    x-data="{
        multiple: @js($multiple),
        openItems: @js($defaultKeys),
        isOpen(key) { return this.openItems.includes(String(key)); },
        toggle(key) {
            key = String(key);
            if (this.isOpen(key)) {
                this.openItems = this.openItems.filter((item) => item !== key);
                return;
            }

            this.openItems = this.multiple ? [...this.openItems, key] : [key];
        }
    }"
    {{ $attributes->merge(['style' => 'border-color: var(--theme-border-color); background-color: var(--theme-surface-base);'])->class('divide-y overflow-hidden rounded-[var(--theme-card-radius,0.9rem)] border') }}
>
    @foreach ($items as $index => $item)
        @php
            $key = (string) ($item['key'] ?? $index);
            $title = $item['title'] ?? '';
            $description = $item['description'] ?? null;
            $content = $item['content'] ?? '';
        @endphp

        <section style="background-color: var(--theme-surface-base);">
            <button
                type="button"
                class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-slate-950/70"
                x-on:click="toggle(@js($key))"
                x-bind:aria-expanded="isOpen(@js($key))"
            >
                <span class="space-y-1">
                    <span class="block text-sm font-semibold text-slate-950 dark:text-white">{{ $title }}</span>
                    @if ($description)
                        <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $description }}</span>
                    @endif
                </span>

                <span
                    class="mt-0.5 inline-flex h-7 w-7 flex-none items-center justify-center rounded-full border text-slate-500 transition"
                    style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);"
                    x-bind:class="isOpen(@js($key)) ? 'rotate-180 text-slate-700 dark:text-slate-200' : ''"
                >
                    <i class="fa-light fa-chevron-down text-xs"></i>
                </span>
            </button>

            <div x-cloak x-show="isOpen(@js($key))" x-collapse>
                <div class="border-t px-5 py-4 text-sm leading-7 text-slate-600 dark:text-slate-300" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {!! $content !!}
                </div>
            </div>
        </section>
    @endforeach
</div>
