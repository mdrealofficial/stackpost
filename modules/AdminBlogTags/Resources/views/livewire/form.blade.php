<div class="space-y-6">
    @php
        $optionIdleStyle = 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);';
    @endphp

    <x-ui.form-page
        :eyebrow="__('Blogs')"
        :title="$isEditing ? __('Edit Blog Tag') : __('Create Blog Tag')"
        :description="$isEditing ? __('Update the tag name and display settings.') : __('Create a reusable tag for filtering blog content.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-blog-tags.index')" variant="outline" wire:navigate>
                {{ __('Back to tags') }}
            </x-ui.button>
        </x-slot:actions>
        @if ($statusMessage)
            <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible class="mb-6" />
        @endif

        <form
            wire:submit="save"
            class="space-y-6"
            x-data="{
                defaultName: @entangle('nameTranslations.'.$defaultLocale),
                slugValue: @entangle('slug'),
                slugManual: @entangle('slugManuallyEdited'),
                slugify(value) {
                    return (value || '')
                        .toString()
                        .toLowerCase()
                        .replace(/đ/g, 'd')
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                },
                syncSlugFromName(value) {
                    if (this.slugManual) return;
                    this.slugValue = this.slugify(value);
                },
                syncSlugManualState(value) {
                    const next = (value || '').trim();
                    if (next === '') {
                        this.slugManual = false;
                        this.slugValue = this.slugify(this.defaultName);
                        return;
                    }

                    this.slugManual = true;
                },
            }"
            x-init="if (!slugManual) { slugValue = slugify(defaultName) }"
        >
            <x-ui.form-layout>
                <x-slot:main>
                    <x-ui.settings-panel icon="fa-light fa-language">
                        <x-ui.checkbox
                            wire:model.defer="autoTranslateMissing"
                            name="auto_translate_missing"
                            value="1"
                            :checked="$autoTranslateMissing"
                            :label="__('Auto-fill missing translations')"
                            :description="__('When enabled, active languages with auto-translate turned on will be generated for any empty tag names.')"
                        />
                    </x-ui.settings-panel>

                    <div>
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Localized content') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Provide the tag name for each active language. The default locale is required.') }}</p>
                    </div>

                    @foreach ($languages as $language)
                        @php
                            $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                            $name = data_get($language, 'name', strtoupper($code));
                        @endphp

                        <x-ui.locale-card :title="$name" :locale="$code" :default="$code === $defaultLocale">
                            @if ($code === $defaultLocale)
                                <x-ui.emoji-input
                                    x-model="defaultName"
                                    x-on:input="syncSlugFromName($event.target.value)"
                                    :label="__('Name')"
                                    :error="$errors->first('nameTranslations.'.$code)"
                                    :help="__('You can add emoji to the default tag name.')"
                                    :required="true"
                                />
                            @else
                                <x-ui.input
                                    wire:model="nameTranslations.{{ $code }}"
                                    :label="__('Name')"
                                    :error="$errors->first('nameTranslations.'.$code)"
                                />
                            @endif
                        </x-ui.locale-card>
                    @endforeach
                </x-slot:main>

                <x-slot:sidebar>
                    <x-ui.settings-panel
                        hero
                        icon="fa-light fa-sliders"
                        :title="__('Settings')"
                        :description="__('Secondary options for publishing, slug generation, and display styling.')"
                    />

                    <x-ui.settings-panel
                        icon="fa-light fa-toggle-on"
                        :title="__('Status')"
                        :description="__('Choose whether this tag is available for editors and publishing flows.')"
                    >
                        <x-ui.field :error="$errors->first('status')" class="mt-4">
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex cursor-pointer items-center gap-3 rounded-[1rem] border px-4 py-3 transition" style="border-color: {{ $status === '1' ? 'rgba(var(--theme-accent-rgb), 0.28)' : 'var(--theme-border-color)' }}; background-color: {{ $status === '1' ? 'rgba(var(--theme-accent-rgb), 0.08)' : 'color-mix(in srgb, var(--theme-surface-base) 98%, transparent)' }}; color: var(--theme-header-text-color);">
                                    <input type="radio" name="status" value="1" wire:model.defer="status" class="h-4 w-4" />
                                    <span>
                                        <span class="block text-sm font-semibold">{{ __('Enable') }}</span>
                                        <span class="block text-xs" style="color: var(--theme-muted-text-color);">{{ __('Visible') }}</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-[1rem] border px-4 py-3 transition" style="border-color: {{ $status === '0' ? 'rgba(100, 116, 139, 0.24)' : 'var(--theme-border-color)' }}; background-color: {{ $status === '0' ? 'rgba(148, 163, 184, 0.08)' : 'color-mix(in srgb, var(--theme-surface-base) 98%, transparent)' }}; color: var(--theme-header-text-color);">
                                    <input type="radio" name="status" value="0" wire:model.defer="status" class="h-4 w-4" />
                                    <span>
                                        <span class="block text-sm font-semibold">{{ __('Disable') }}</span>
                                        <span class="block text-xs" style="color: var(--theme-muted-text-color);">{{ __('Hidden') }}</span>
                                    </span>
                                </label>
                            </div>
                        </x-ui.field>
                    </x-ui.settings-panel>

                    <x-ui.settings-panel class="space-y-4">
                        <x-ui.input
                            x-model="slugValue"
                            x-on:input="syncSlugManualState($event.target.value)"
                            :label="__('Slug')"
                            :error="$errors->first('slug')"
                            :help="__('Leave empty to generate from the default language name.')"
                        />
                        <x-ui.field :label="__('Highlight color')" :error="$errors->first('color')" :help="__('Pick a color or edit the hex value.')">
                            <div class="flex items-center gap-3">
                                <label class="relative block h-11 w-16 shrink-0 cursor-pointer overflow-hidden rounded-[0.95rem] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="{{ $optionIdleStyle }}">
                                    <span class="pointer-events-none absolute inset-[5px] rounded-[0.72rem] border border-white/80 shadow-inner" style="background-color: {{ $color ?: '#0f766e' }};"></span>
                                    <input type="color" wire:model.defer="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                                </label>
                                <x-ui.input wire:model.defer="color" class="flex-1" />
                            </div>
                        </x-ui.field>
                        <x-ui.icon-picker wire:model.defer="icon" :label="__('Icon class')" :error="$errors->first('icon')" :preview-color="$color ?: '#0f766e'" />
                    </x-ui.settings-panel>
                </x-slot:sidebar>
            </x-ui.form-layout>

            <x-ui.form-actions
                :cancel-href="route('admin-blog-tags.index')"
                :submit-label="__('Save changes')"
            />
        </form>
    </x-ui.form-page>
</div>
