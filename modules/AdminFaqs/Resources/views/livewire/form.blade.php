<div class="space-y-6">
    <x-ui.sub-header
        :eyebrow="__('Settings')"
        :title="$isEditing ? __('Edit FAQ') : __('Create FAQ')"
        :description="$isEditing ? __('Update an existing answer, slug, and publication status for this FAQ item.') : __('Write a new frequently asked question and answer for your public pages.')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-faqs.index') }}" variant="outline" wire:navigate>
                {{ __('Back') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.sub-header>

    <div class="mx-auto max-w-[860px] pb-8">
        @if ($statusMessage)
            <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" class="mb-4" />
        @endif

        <form wire:submit="save" class="space-y-4">
            <x-ui.form-section :title="__('FAQ information')">
                <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="inline-flex cursor-pointer items-start gap-3">
                            <input
                                type="radio"
                                name="status"
                                value="1"
                                wire:model.live="status"
                                @checked($status === '1')
                                class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                                style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                            >
                            <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Enable') }}</span>
                        </label>
                        <label class="inline-flex cursor-pointer items-start gap-3">
                            <input
                                type="radio"
                                name="status"
                                value="0"
                                wire:model.live="status"
                                @checked($status === '0')
                                class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                                style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                            >
                            <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Disable') }}</span>
                        </label>
                    </div>
                </x-ui.field>

                <x-ui.input wire:model="title" :label="__('Title')" :error="$errors->first('title')" required autofocus />
                <x-ui.input wire:model="slug" :label="__('Slug')" :error="$errors->first('slug')" :help="__('Leave empty to generate from the title.')" />
                <x-ui.textarea wire:model="content" :label="__('Content')" :error="$errors->first('content')" rows="10"></x-ui.textarea>
            </x-ui.form-section>

            <div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-[0.5rem] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background-color: #1f2430;">
                    {{ __('Save changes') }}
                </button>
            </div>
        </form>

        @if ($isEditing)
            <div class="mt-4 overflow-hidden rounded-[0.75rem] border shadow-[0_2px_10px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color);">
                    <h3 class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ __('Danger zone') }}</h3>
                </div>
                <div class="space-y-4 px-6 py-6">
                    <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Delete this FAQ if it should no longer appear in your public knowledge base.') }}</p>
                    <x-ui.dialog :title="__('Delete FAQ?')" :description="__('This permanently removes the selected FAQ entry.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger">
                                {{ __('Delete FAQ') }}
                            </x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="delete" x-on:click="open = false">
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </div>
            </div>
        @endif
    </div>
</div>
