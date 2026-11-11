<x-ui.section-card
    :title="__('No integration modules registered')"
    :description="__('API Integration starts empty. Providers only appear here after a module calls register_integration_item().')"
    body-class="p-6"
>
    <div class="rounded-[1.35rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.07), rgba(var(--theme-border-color-rgb), 0.03));">
        <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
            {{ __('Register one or more integration modules to expose their API settings here.') }}
        </p>
    </div>
</x-ui.section-card>
