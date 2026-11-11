@php
    $languages = function_exists('available_languages') ? available_languages() : collect();
    $defaultLocale = function_exists('locale_manager') ? locale_manager()->defaultCode() : (string) config('app.fallback_locale', 'en');
    $optionIdleStyle = 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);';

    if ($languages->isEmpty()) {
        $languages = collect([(object) ['code' => $defaultLocale, 'name' => strtoupper($defaultLocale)]]);
    }
@endphp

<x-ui.form-layout>
    <x-slot:main>
        <x-ui.settings-panel icon="fa-light fa-language">
            <x-ui.checkbox
                name="auto_translate_missing"
                value="1"
                :checked="old('auto_translate_missing', '1') === '1'"
                :label="__('Auto-fill missing translations')"
                :description="__('When enabled, active languages with auto-translate turned on will be generated for any empty category fields.')"
            />
        </x-ui.settings-panel>

        <div class="space-y-4">
            <div>
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Localized content') }}</p>
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Provide translated labels and descriptions for each active language. The default locale is required.') }}</p>
            </div>

            @foreach ($languages as $language)
                @php
                    $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                    $name = data_get($language, 'name', strtoupper($code));
                    $nameValue = old("name_translations.$code", data_get($category->name_translations, $code, $code === $defaultLocale ? $category->name : ''));
                    $descriptionValue = old("description_translations.$code", data_get($category->description_translations, $code, $code === $defaultLocale ? $category->description : ''));
                @endphp

                <x-ui.locale-card :title="$name" :locale="$code" :default="$code === $defaultLocale">
                    <x-ui.emoji-input
                        :name="'name_translations['.$code.']'"
                        :label="__('Name')"
                        :value="$nameValue"
                        :error="$errors->first('name_translations.'.$code)"
                        :required="$code === $defaultLocale"
                    />

                    <x-ui.emoji-textarea
                        :name="'description_translations['.$code.']'"
                        :label="__('Description')"
                        :error="$errors->first('description_translations.'.$code)"
                        rows="4"
                        picker-position="top"
                        picker-align="right"
                        trigger-position="inside-top-right"
                    >{{ $descriptionValue }}</x-ui.emoji-textarea>
                </x-ui.locale-card>
            @endforeach
        </div>
    </x-slot:main>

    <x-slot:sidebar>
        <x-ui.settings-panel
            hero
            icon="fa-light fa-sliders"
            :title="__('Settings')"
            :description="__('Secondary options for publishing, slug generation, ordering, and display styling.')"
        />

        @php
            $statusValue = (int) old('status', (int) ($category->status ?? true));
            $colorValue = old('color', $category->color ?: '#0f766e');
        @endphp

        <x-ui.settings-panel
            icon="fa-light fa-toggle-on"
            :title="__('Status')"
            :description="__('Choose whether this category is available for editors and publishing flows.')"
        >
            <x-ui.field :error="$errors->first('status')">
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex cursor-pointer items-center gap-3 rounded-[1rem] border px-4 py-3 transition" style="border-color: {{ $statusValue === 1 ? 'rgba(var(--theme-accent-rgb), 0.28)' : 'var(--theme-border-color)' }}; background-color: {{ $statusValue === 1 ? 'rgba(var(--theme-accent-rgb), 0.08)' : 'color-mix(in srgb, var(--theme-surface-base) 98%, transparent)' }}; color: var(--theme-header-text-color);">
                        <input type="radio" name="status" value="1" @checked($statusValue === 1) class="h-4 w-4" />
                        <span>
                            <span class="block text-sm font-semibold">{{ __('Enable') }}</span>
                            <span class="block text-xs" style="color: var(--theme-muted-text-color);">{{ __('Visible') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-[1rem] border px-4 py-3 transition" style="border-color: {{ $statusValue === 0 ? 'rgba(100, 116, 139, 0.24)' : 'var(--theme-border-color)' }}; background-color: {{ $statusValue === 0 ? 'rgba(148, 163, 184, 0.08)' : 'color-mix(in srgb, var(--theme-surface-base) 98%, transparent)' }}; color: var(--theme-header-text-color);">
                        <input type="radio" name="status" value="0" @checked($statusValue === 0) class="h-4 w-4" />
                        <span>
                            <span class="block text-sm font-semibold">{{ __('Disable') }}</span>
                            <span class="block text-xs" style="color: var(--theme-muted-text-color);">{{ __('Hidden') }}</span>
                        </span>
                    </label>
                </div>
            </x-ui.field>
        </x-ui.settings-panel>

        <x-ui.settings-panel class="space-y-4">
            <x-ui.input name="slug" :label="__('Slug')" :value="old('slug', $category->slug)" :error="$errors->first('slug')" :help="__('Leave empty to generate from the default language name.')" />
            <x-ui.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="old('sort_order', $category->sort_order ?? 0)" :error="$errors->first('sort_order')" />
            <x-ui.field :label="__('Highlight color')" :error="$errors->first('color')" :help="__('Pick a color or edit the hex value.')">
                <div x-data="{ color: @js($colorValue) }" class="flex items-center gap-3">
                    <label class="relative block h-11 w-16 shrink-0 cursor-pointer overflow-hidden rounded-[0.95rem] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="{{ $optionIdleStyle }}">
                        <span class="pointer-events-none absolute inset-[5px] rounded-[0.72rem] border border-white/80 shadow-inner" :style="`background-color: ${color};`"></span>
                        <input type="color" x-model="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                    </label>
                    <x-ui.input name="color" x-model="color" class="flex-1" />
                </div>
            </x-ui.field>
            <x-ui.icon-picker name="icon" :value="old('icon', $category->icon ?: 'fa-light fa-folder-tree')" :label="__('Icon class')" :error="$errors->first('icon')" :preview-color="$colorValue" />
        </x-ui.settings-panel>
    </x-slot:sidebar>
</x-ui.form-layout>
