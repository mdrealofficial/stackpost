@props([
    'section',
    'defaultOpen' => false,
])

<x-payments.settings-accordion-card
    :item-key="$section['key']"
    :title="__($section['title'])"
    :description="__($section['description'] ?? '')"
    :default-open="$defaultOpen"
    body-class="p-6"
>
    <div class="rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
            {{ __('No module-owned settings form is available for this gateway yet. The gateway can still appear at checkout when it is enabled in code.') }}
        </p>
    </div>
</x-payments.settings-accordion-card>
