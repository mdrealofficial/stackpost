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
                $nameValue = old("name_translations.$code", data_get($tag->name_translations, $code, $code === $defaultLocale ? $tag->name : ''));
            @endphp

            <x-ui.locale-card :title="$name" :locale="$code" :default="$code === $defaultLocale">
                <x-ui.input
                    :name="'name_translations['.$code.']'"
                    :label="__('Name')"
                    :value="$nameValue"
                    :error="$errors->first('name_translations.'.$code)"
                    :required="$code === $defaultLocale"
                />
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

        @php
            $statusValue = (int) old('status', (int) ($tag->status ?? true));
        @endphp

        <x-ui.settings-panel
            icon="fa-light fa-toggle-on"
            :title="__('Status')"
            :description="__('Choose whether this tag is available for editors and publishing flows.')"
        >
            <x-ui.field :error="$errors->first('status')" class="mt-4">
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
            <x-ui.input name="slug" :label="__('Slug')" :value="old('slug', $tag->slug)" :error="$errors->first('slug')" :help="__('Leave empty to generate from the default language name.')" />
            <x-ui.field :label="__('Highlight color')" :error="$errors->first('color')" :help="__('Pick a color or edit the hex value.')">
                <div x-data="{ color: @js(old('color', $tag->color ?: '#0f766e')) }" class="flex items-center gap-3">
                    <label class="relative block h-11 w-16 shrink-0 cursor-pointer overflow-hidden rounded-[0.95rem] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="{{ $optionIdleStyle }}">
                        <span class="pointer-events-none absolute inset-[5px] rounded-[0.72rem] border border-white/80 shadow-inner" :style="`background-color: ${color};`"></span>
                        <input type="color" x-model="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                    </label>
                    <x-ui.input name="color" x-model="color" class="flex-1" />
                </div>
            </x-ui.field>
            <x-ui.icon-picker name="icon" :value="old('icon', $tag->icon ?: 'fa-light fa-hashtag')" :label="__('Icon class')" :error="$errors->first('icon')" :preview-color="old('color', $tag->color ?: '#0f766e')" />
        </x-ui.settings-panel>
    </x-slot:sidebar>
</x-ui.form-layout>
