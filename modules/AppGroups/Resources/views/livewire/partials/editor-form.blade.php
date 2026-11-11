<div class="flex items-start justify-between gap-4">
    <div class="flex min-w-0 items-start gap-3">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" style="background-color: {{ $editingId ? 'rgba(245, 158, 11, 0.12)' : 'rgba(var(--theme-accent-rgb), 0.12)' }}; color: {{ $editingId ? 'rgb(217, 119, 6)' : 'var(--theme-accent)' }};">
            <i class="fa-light {{ $editingId ? 'fa-pen-to-square' : 'fa-layer-plus' }}"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Group editor') }}</p>
            <h2 class="mt-1 text-[1.25rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $editingId ? __('Edit group') : __('Create group') }}</h2>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                {{ $editingId ? __('Update channels, notes, or status for this group.') : __('Add a reusable account cluster for your publishing workspace and future campaigns.') }}
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
    <x-ui.input wire:model.defer="name" :label="__('Group name')" :error="$errors->first('name')" :placeholder="__('Launch accounts')" />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.color-picker wire:model.defer="color" name="color" :label="__('Accent color')" :value="$color" :error="$errors->first('color')" />
        <x-ui.select wire:model.defer="groupStatus" :label="__('Status')" :error="$errors->first('groupStatus')">
            <option value="active">{{ __('Active') }}</option>
            <option value="inactive">{{ __('Inactive') }}</option>
        </x-ui.select>
    </div>

    <x-ui.channel-selector
        name="account_ids"
        wire-model="accountIds"
        :options="$channelOptions"
        :network-options="$channelNetworks"
        :selected="collect($accountIds)->map(fn ($id) => (string) $id)->all()"
        :label="__('Accounts')"
        :error="$errors->first('accountIds') ?: $errors->first('accountIds.*')"
        :placeholder="__('Choose one or more accounts')"
        :empty-label="__('No matching channels found.')"
        :multiple="true"
        :show-network-filters="true"
        :connect-href="route('portal.channels')"
        :connect-label="__('Connect a channel')"
        :inline-panel="true"
    />

    <x-ui.textarea wire:model.defer="description" :label="__('Description')" :error="$errors->first('description')" rows="6" :placeholder="__('Optional internal note about campaign purpose, region, or audience.')"></x-ui.textarea>

    <div class="sticky bottom-0 -mx-5 -mb-5 mt-6 border-t px-5 py-4 backdrop-blur-xl sm:-mx-6 sm:-mb-6 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 94%, transparent);">
        <div class="flex flex-wrap items-center justify-end gap-3">
        <x-ui.button type="button" variant="outline" wire:click="resetEditor">
            {{ __('Cancel') }}
        </x-ui.button>
        <x-ui.button type="submit">
            <i class="fa-light {{ $editingId ? 'fa-floppy-disk' : 'fa-plus' }}"></i>
            {{ $editingId ? __('Update Group') : __('Create Group') }}
        </x-ui.button>
        </div>
    </div>
</form>
