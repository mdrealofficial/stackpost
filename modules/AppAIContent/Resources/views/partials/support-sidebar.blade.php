<aside class="hidden border-t p-4 xl:col-start-2 xl:block 2xl:col-start-auto 2xl:border-l 2xl:border-t-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background: color-mix(in srgb, var(--theme-surface-base) 98%, var(--theme-body-bg) 2%);">
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-[0.7rem]" style="background: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                <i class="fa-light fa-sliders-simple text-sm"></i>
            </span>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Current setup') }}</p>
        </div>
        <div class="grid gap-2 text-sm">
            <div class="flex items-center justify-between gap-3 rounded-[0.7rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: var(--theme-surface-base);">
                <span style="color: var(--theme-muted-text-color);">{{ __('Template') }}</span>
                <span class="min-w-0 truncate font-medium" style="color: var(--theme-header-text-color);">{{ $selectedTemplate?->category?->name ?: __('Custom prompt') }}</span>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-[0.7rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: var(--theme-surface-base);">
                <span style="color: var(--theme-muted-text-color);">{{ __('Output') }}</span>
                <span class="font-medium" style="color: var(--theme-header-text-color);">{{ __(':count x :words', ['count' => $totalResults, 'words' => $approximateWords]) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-[0.7rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.42); background-color: var(--theme-surface-base);">
                <span style="color: var(--theme-muted-text-color);">{{ __('Hashtags') }}</span>
                <span class="font-medium" style="color: var(--theme-header-text-color);">{{ ucfirst($hashtagMode) }}</span>
            </div>
        </div>
    </div>

    <div class="mt-5 space-y-3 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.65);">
        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Workflow') }}</p>
        <div class="space-y-2 text-sm leading-5" style="color: var(--theme-muted-text-color);">
            <div class="flex gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full" style="background: var(--theme-accent);"></span>
                <span>{{ __('Pick a template or write a custom prompt.') }}</span>
            </div>
            <div class="flex gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full" style="background: var(--theme-accent);"></span>
                <span>{{ __('Tune tone, language, length, and result count.') }}</span>
            </div>
            <div class="flex gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full" style="background: var(--theme-accent);"></span>
                <span>{{ __('Generate and save the best variants to Captions.') }}</span>
            </div>
        </div>
    </div>
</aside>
