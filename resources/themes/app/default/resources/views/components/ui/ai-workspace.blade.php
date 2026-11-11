@props(['title', 'description', 'inputTitle', 'inputDescription', 'outputTitle', 'outputDescription', 'action'])
<div class="mx-auto w-full max-w-[1440px] space-y-5 px-4 py-5 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="$title" :description="$description" />
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <x-ui.card padding="none">
            <div class="border-b p-5" style="border-color: var(--theme-border-color);">
                <p class="mb-2 text-xs font-medium" style="color: var(--theme-accent);">{{ __('Your input') }}</p>
                <h2 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $inputTitle }}</h2>
                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $inputDescription }}</p>
            </div>
            <div class="min-w-0 p-5">{{ $input }}</div>
        </x-ui.card>
        <x-ui.card padding="none" class="min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b p-5" style="border-color: var(--theme-border-color);">
                <div class="min-w-0">
                    <p class="mb-2 text-xs font-medium" style="color: var(--theme-accent);">{{ __('Results') }}</p>
                    <h2 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $outputTitle }}</h2>
                    <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $outputDescription }}</p>
                </div>
                {{ $resultActions ?? '' }}
            </div>
            <div class="min-w-0 p-5">
                <div wire:loading wire:target="{{ $action }}" role="status" class="w-full">
                    <x-ui.empty icon="fa-light fa-loader animate-spin" :title="__('Working on your request')" :description="__('Your results will appear here when ready.')" />
                </div>
                <div wire:loading.remove wire:target="{{ $action }}">{{ $results }}</div>
            </div>
        </x-ui.card>
    </div>
    {{ $history ?? '' }}
</div>
