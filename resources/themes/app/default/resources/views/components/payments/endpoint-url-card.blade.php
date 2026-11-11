@props([
    'label',
    'value',
])

<div
    class="rounded-[0.85rem] border px-4 py-3"
    style="border-color: var(--theme-border-color); background: var(--theme-surface-color);"
    x-data="{
        copied: false,
        async copy() {
            try {
                await navigator.clipboard.writeText(@js($value));
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            } catch (e) {}
        }
    }"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">
                {{ $label }}
            </p>
            <p class="mt-2 break-all font-mono text-xs sm:text-sm" style="color: var(--theme-header-text-color);">
                {{ $value }}
            </p>
        </div>

        <x-ui.button
            type="button"
            variant="outline"
            size="sm"
            class="shrink-0"
            @click="copy()"
        >
            <i class="fa-light" :class="copied ? 'fa-check' : 'fa-copy'"></i>
            <span x-show="!copied">{{ __('Copy') }}</span>
            <span x-show="copied">{{ __('Copied') }}</span>
        </x-ui.button>
    </div>
</div>
