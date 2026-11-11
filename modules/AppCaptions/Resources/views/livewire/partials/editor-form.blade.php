<div class="flex items-start justify-between gap-4">
    <div class="flex min-w-0 items-start gap-3">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" style="background-color: {{ $editingId ? 'rgba(245, 158, 11, 0.12)' : 'rgba(var(--theme-accent-rgb), 0.12)' }}; color: {{ $editingId ? 'rgb(217, 119, 6)' : 'var(--theme-accent)' }};">
            <i class="fa-light {{ $editingId ? 'fa-pen-to-square' : 'fa-pen-field' }}"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Caption editor') }}</p>
            <h2 class="mt-1 text-[1.25rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $editingId ? __('Edit caption') : __('Create caption') }}</h2>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                {{ $editingId ? __('Update content, tags, or status for this caption entry.') : __('Add a reusable caption block for your publishing team and future campaigns.') }}
            </p>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <x-ui.badge :variant="$editingId ? 'primary' : 'success'">{{ $editingId ? __('Editing') : __('New') }}</x-ui.badge>
        <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5"
            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
            wire:click="resetEditor"
            aria-label="{{ __('Close') }}"
        >
            <i class="fa-light fa-xmark"></i>
        </button>
    </div>
</div>

<form wire:submit="save" class="mt-6 space-y-4">
    <x-ui.input wire:model.defer="name" :label="__('Caption name')" :error="$errors->first('name')" :placeholder="__('Product teaser set')" />
    <div>

        <x-ui.select wire:model.defer="captionStatus" :label="__('Status')" :error="$errors->first('captionStatus')">
            <option value="active">{{ __('Active') }}</option>
            <option value="draft">{{ __('Draft') }}</option>
            <option value="archived">{{ __('Archived') }}</option>
        </x-ui.select>
    </div>
    <x-ui.textarea wire:model.defer="content" :label="__('Caption')" :error="$errors->first('content')" rows="8" :placeholder="__('Write or paste the caption body...')"></x-ui.textarea>
    <x-ui.input wire:model.defer="tags" :label="__('Tags')" :error="$errors->first('tags')" :placeholder="__('launch, promo, product')" />
    <details class="cap-editor-notes" @if($notes!=='' || $errors->has('notes')) open @endif><summary>{{ __('Internal notes (optional)') }}</summary>
    <x-ui.textarea wire:model.defer="notes" :label="__('Notes')" :error="$errors->first('notes')" rows="3" :placeholder="__('Optional internal context, usage notes, or reminders...')"></x-ui.textarea>
    </details>

    <div class="sticky bottom-0 -mx-5 -mb-5 mt-6 border-t px-5 py-4 backdrop-blur-xl sm:-mx-6 sm:-mb-6 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 94%, transparent);">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-ui.button type="button" variant="outline" wire:click="resetEditor">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
            <i class="fa-light fa-floppy-disk"></i>
            {{ $editingId ? __('Update Caption') : __('Create Caption') }}
        </x-ui.button>
        </div>
    </div>
</form>
