    @php
        $frontendColorKeys = ['accent_color', 'body_bg_color', 'surface_bg_color', 'header_bg_color', 'header_active_color', 'link_color', 'link_hover_color', 'border_color', 'muted_text_color', 'success_color', 'warning_color', 'danger_color'];
        $frontendTypographyKeys = ['font_family'];
        $frontendComponentKeys = ['card_radius', 'input_radius', 'button_radius', 'button_style', 'button_shadow'];
        $frontendLayoutKeys = ['layout_width', 'page_max_width', 'supports_dark_mode', 'allow_user_appearance_toggle', 'default_appearance', 'density', 'section_spacing', 'preview_mode'];
        $frontendAdvancedKeys = ['custom_css', 'custom_js'];
        $fontStacks = [
            'inter' => '"Inter", ui-sans-serif, system-ui, sans-serif',
            'instrument-sans' => '"Instrument Sans", ui-sans-serif, system-ui, sans-serif',
            'plus-jakarta-sans' => '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif',
            'manrope' => '"Manrope", ui-sans-serif, system-ui, sans-serif',
            'outfit' => '"Outfit", ui-sans-serif, system-ui, sans-serif',
            'sora' => '"Sora", ui-sans-serif, system-ui, sans-serif',
            'space-grotesk' => '"Space Grotesk", ui-sans-serif, system-ui, sans-serif',
            'public-sans' => '"Public Sans", ui-sans-serif, system-ui, sans-serif',
            'ibm-plex-sans' => '"IBM Plex Sans", ui-sans-serif, system-ui, sans-serif',
            'dm-sans' => '"DM Sans", ui-sans-serif, system-ui, sans-serif',
            'system' => 'ui-sans-serif, system-ui, sans-serif',
        ];

        $frontendPreviewState = [
            'accent_color' => old('frontend_settings.accent_color', $frontendThemeValues['accent_color'] ?? ($frontendThemeSchema['accent_color']['default'] ?? '#0f766e')),
            'body_bg_color' => old('frontend_settings.body_bg_color', $frontendThemeValues['body_bg_color'] ?? ($frontendThemeSchema['body_bg_color']['default'] ?? '#f8faff')),
            'surface_bg_color' => old('frontend_settings.surface_bg_color', $frontendThemeValues['surface_bg_color'] ?? ($frontendThemeSchema['surface_bg_color']['default'] ?? '#ffffff')),
            'header_bg_color' => old('frontend_settings.header_bg_color', $frontendThemeValues['header_bg_color'] ?? ($frontendThemeSchema['header_bg_color']['default'] ?? '#ffffff')),
            'header_active_color' => old('frontend_settings.header_active_color', $frontendThemeValues['header_active_color'] ?? ($frontendThemeSchema['header_active_color']['default'] ?? '#0f172a')),
            'link_color' => old('frontend_settings.link_color', $frontendThemeValues['link_color'] ?? ($frontendThemeSchema['link_color']['default'] ?? '#4f46e5')),
            'link_hover_color' => old('frontend_settings.link_hover_color', $frontendThemeValues['link_hover_color'] ?? ($frontendThemeSchema['link_hover_color']['default'] ?? '#4338ca')),
            'border_color' => old('frontend_settings.border_color', $frontendThemeValues['border_color'] ?? ($frontendThemeSchema['border_color']['default'] ?? '#e2e8f0')),
            'muted_text_color' => old('frontend_settings.muted_text_color', $frontendThemeValues['muted_text_color'] ?? ($frontendThemeSchema['muted_text_color']['default'] ?? '#64748b')),
            'success_color' => old('frontend_settings.success_color', $frontendThemeValues['success_color'] ?? ($frontendThemeSchema['success_color']['default'] ?? '#059669')),
            'warning_color' => old('frontend_settings.warning_color', $frontendThemeValues['warning_color'] ?? ($frontendThemeSchema['warning_color']['default'] ?? '#d97706')),
            'danger_color' => old('frontend_settings.danger_color', $frontendThemeValues['danger_color'] ?? ($frontendThemeSchema['danger_color']['default'] ?? '#dc2626')),
            'font_family' => old('frontend_settings.font_family', $frontendThemeValues['font_family'] ?? ($frontendThemeSchema['font_family']['default'] ?? 'inter')),
            'layout_width' => old('frontend_settings.layout_width', $frontendThemeValues['layout_width'] ?? ($frontendThemeSchema['layout_width']['default'] ?? 'full')),
            'page_max_width' => old('frontend_settings.page_max_width', $frontendThemeValues['page_max_width'] ?? ($frontendThemeSchema['page_max_width']['default'] ?? '80rem')),
            'supports_dark_mode' => old('frontend_settings.supports_dark_mode', $frontendThemeValues['supports_dark_mode'] ?? ($frontendThemeSchema['supports_dark_mode']['default'] ?? '1')),
            'allow_user_appearance_toggle' => old('frontend_settings.allow_user_appearance_toggle', $frontendThemeValues['allow_user_appearance_toggle'] ?? ($frontendThemeSchema['allow_user_appearance_toggle']['default'] ?? '1')),
            'default_appearance' => old('frontend_settings.default_appearance', $frontendThemeValues['default_appearance'] ?? ($frontendThemeSchema['default_appearance']['default'] ?? ($frontendThemeValues['appearance'] ?? 'system'))),
            'density' => old('frontend_settings.density', $frontendThemeValues['density'] ?? ($frontendThemeSchema['density']['default'] ?? 'comfortable')),
            'section_spacing' => old('frontend_settings.section_spacing', $frontendThemeValues['section_spacing'] ?? ($frontendThemeSchema['section_spacing']['default'] ?? '5rem')),
            'preview_mode' => old('frontend_settings.preview_mode', $frontendThemeValues['preview_mode'] ?? ($frontendThemeSchema['preview_mode']['default'] ?? 'desktop')),
        ];

        $frontendColorPresets = [
            'accent_color' => ['#1d4ed8', '#0f766e', '#7c3aed', '#c2410c', '#be123c', '#111827'],
            'body_bg_color' => ['#f7f4ee', '#f5f8ff', '#f3faf7', '#faf5ff', '#fff6ef', '#f8fafc'],
            'surface_bg_color' => ['#fffdfa', '#ffffff', '#fbfdfb', '#ffffff', '#fffdf9', '#ffffff'],
            'header_bg_color' => ['#fffaf2', '#ffffff', '#f0fdf4', '#f5f3ff', '#fff1e8', '#111827'],
            'header_active_color' => ['#0f172a', '#1e3a8a', '#14532d', '#581c87', '#7c2d12', '#e5e7eb'],
            'link_color' => ['#1d4ed8', '#0f766e', '#7c3aed', '#c2410c', '#be123c', '#111827'],
            'link_hover_color' => ['#1e40af', '#115e59', '#6d28d9', '#9a3412', '#9f1239', '#020617'],
            'border_color' => ['#ddd6ce', '#dbe3ef', '#d7e6dd', '#ddd6fe', '#f0d4c2', '#334155'],
            'muted_text_color' => ['#6b7280', '#64748b', '#667564', '#6b7280', '#8a6f61', '#94a3b8'],
            'success_color' => ['#15803d', '#0f766e', '#166534', '#15803d', '#0f766e', '#22c55e'],
            'warning_color' => ['#d97706', '#d97706', '#ca8a04', '#d97706', '#ea580c', '#f59e0b'],
            'danger_color' => ['#b91c1c', '#dc2626', '#be123c', '#db2777', '#be123c', '#ef4444'],
        ];

        $frontendLibraryTags = collect($guestThemes)
            ->flatMap(fn ($theme) => is_array($theme->meta['tags'] ?? null) ? $theme->meta['tags'] : [])
            ->map(fn ($tag) => (string) $tag)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $frontendPalettePresets = [
            [
                'key' => 'canvas-ink',
                'name' => __('Canvas Ink'),
                'description' => __('Editorial cream base, dark ink typography, and a clean cobalt action color.'),
                'values' => [
                    'accent_color' => '#1d4ed8',
                    'body_bg_color' => '#f7f4ee',
                    'surface_bg_color' => '#fffdfa',
                    'header_bg_color' => '#fffaf2',
                    'header_active_color' => '#0f172a',
                    'link_color' => '#1d4ed8',
                    'link_hover_color' => '#1e40af',
                    'border_color' => '#ddd6ce',
                    'muted_text_color' => '#6b7280',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#b91c1c',
                ],
            ],
            [
                'key' => 'harbor-saas',
                'name' => __('Harbor SaaS'),
                'description' => __('Sharper blue product palette with cooler surfaces and much cleaner contrast.'),
                'values' => [
                    'accent_color' => '#1d4ed8',
                    'body_bg_color' => '#f5f8ff',
                    'surface_bg_color' => '#ffffff',
                    'header_bg_color' => '#ffffff',
                    'header_active_color' => '#1e3a8a',
                    'link_color' => '#1d4ed8',
                    'link_hover_color' => '#1e40af',
                    'border_color' => '#dbe3ef',
                    'muted_text_color' => '#64748b',
                    'success_color' => '#0f766e',
                    'warning_color' => '#d97706',
                    'danger_color' => '#be123c',
                ],
            ],
            [
                'key' => 'grove-journal',
                'name' => __('Grove Journal'),
                'description' => __('Softer botanical neutrals with deep evergreen actions for content and brand sites.'),
                'values' => [
                    'accent_color' => '#0f766e',
                    'body_bg_color' => '#f3faf7',
                    'surface_bg_color' => '#fbfdfb',
                    'header_bg_color' => '#f0fdf4',
                    'header_active_color' => '#14532d',
                    'link_color' => '#0f766e',
                    'link_hover_color' => '#115e59',
                    'border_color' => '#d7e6dd',
                    'muted_text_color' => '#667564',
                    'success_color' => '#166534',
                    'warning_color' => '#ca8a04',
                    'danger_color' => '#b91c1c',
                ],
            ],
            [
                'key' => 'violet-membership',
                'name' => __('Violet Membership'),
                'description' => __('Premium violet highlights without the candy-color feel of the old presets.'),
                'values' => [
                    'accent_color' => '#7c3aed',
                    'body_bg_color' => '#faf5ff',
                    'surface_bg_color' => '#ffffff',
                    'header_bg_color' => '#f5f3ff',
                    'header_active_color' => '#581c87',
                    'link_color' => '#7c3aed',
                    'link_hover_color' => '#6d28d9',
                    'border_color' => '#ddd6fe',
                    'muted_text_color' => '#6b7280',
                    'success_color' => '#15803d',
                    'warning_color' => '#d97706',
                    'danger_color' => '#be123c',
                ],
            ],
            [
                'key' => 'terracotta-campaign',
                'name' => __('Terracotta Campaign'),
                'description' => __('Warm paper-like surfaces and terracotta accents for bolder marketing pages.'),
                'values' => [
                    'accent_color' => '#c2410c',
                    'body_bg_color' => '#fff6ef',
                    'surface_bg_color' => '#fffdf9',
                    'header_bg_color' => '#fff1e8',
                    'header_active_color' => '#7c2d12',
                    'link_color' => '#c2410c',
                    'link_hover_color' => '#9a3412',
                    'border_color' => '#f0d4c2',
                    'muted_text_color' => '#8a6f61',
                    'success_color' => '#0f766e',
                    'warning_color' => '#ea580c',
                    'danger_color' => '#be123c',
                ],
            ],
            [
                'key' => 'midnight-signal',
                'name' => __('Midnight Signal'),
                'description' => __('Dark header treatment with graphite actions for more serious product frontends.'),
                'values' => [
                    'accent_color' => '#111827',
                    'body_bg_color' => '#f8fafc',
                    'surface_bg_color' => '#ffffff',
                    'header_bg_color' => '#111827',
                    'header_active_color' => '#e5e7eb',
                    'link_color' => '#111827',
                    'link_hover_color' => '#020617',
                    'border_color' => '#334155',
                    'muted_text_color' => '#94a3b8',
                    'success_color' => '#22c55e',
                    'warning_color' => '#f59e0b',
                    'danger_color' => '#ef4444',
                ],
            ],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-6">
        <div class="space-y-8">
            <x-ui.page-hero
                :eyebrow="__('Theme registry')"
                :title="__('Frontend Themes')"
                :description="__('Control the visual system for landing pages, public marketing surfaces, and guest authentication screens from one focused workspace.')"
                :count="$themeSummary['library']"
                icon="fa-light fa-window"
            >
                <x-slot:actions>
                    <x-ui.button href="{{ route('admin-themes.backend') }}" variant="outline" wire:navigate>{{ __('Open backend themes') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.page-hero>

            <x-ui.metric-strip
                :items="[
                    ['label' => __('Library'), 'value' => number_format($themeSummary['library']), 'description' => __('Installed guest themes available for public surfaces.'), 'progress' => 100, 'tone' => 'var(--theme-accent)'],
                    ['label' => __('Editor fields'), 'value' => number_format($themeSummary['editor_fields']), 'description' => __('Theme settings currently exposed in the active guest schema.'), 'progress' => $themeSummary['editor_fields'] > 0 ? 100 : 0, 'tone' => 'var(--theme-warning-color)'],
                    ['label' => __('Supports'), 'value' => number_format($themeSummary['supports']), 'description' => __('Feature flags advertised by the selected frontend theme.'), 'progress' => $themeSummary['supports'] > 0 ? 100 : 0, 'tone' => 'var(--theme-success-color)'],
                    ['label' => __('Backend themes'), 'value' => number_format($themeSummary['backend_themes']), 'description' => __('Admin theme variants available alongside the guest library.'), 'progress' => $themeSummary['backend_themes'] > 0 ? 100 : 0, 'tone' => 'var(--theme-muted-text-color)'],
                ]"
                :show-icons="false"
                columns="md:grid-cols-2 xl:grid-cols-4"
            />

        <form
            id="frontend-theme-form"
            method="POST"
            action="{{ route('admin-themes.frontend.update') }}"
            x-on:submit.prevent="submitThemeForm($el, $event)"
            class="space-y-8"
            x-data="{
                themeTab: @js(request('tab', 'select-theme')),
                themeName: @js(old('frontend_theme', $frontendTheme)),
                submitIntent: '',
                preview: @js($frontendPreviewState),
                fontStacks: @js($fontStacks),
                libraryTagFilter: 'all',
                libraryDarkFilter: 'all',
                importModalOpen: @js($errors->has('frontend_import_json')),
                themeSupports(theme, feature) {
                    const supports = Array.isArray(theme?.supports) ? theme.supports.map((item) => String(item)) : [];

                    return supports.includes(feature);
                },
                matchesLibraryTheme(theme) {
                    const tags = Array.isArray(theme?.tags) ? theme.tags.map((item) => String(item)) : [];

                    if (this.libraryTagFilter !== 'all' && !tags.includes(this.libraryTagFilter)) {
                        return false;
                    }

                    if (this.libraryDarkFilter === 'dark' && !this.themeSupports(theme, 'dark-mode')) {
                        return false;
                    }

                    return true;
                },
                applyPalette(values) {
                    Object.entries(values).forEach(([key, value]) => {
                        this.preview[key] = value;
                    });
                },
                selectTheme(themeName, defaults, switchToSettings = false) {
                    this.themeName = themeName;
                    this.preview = { ...defaults };

                    if (switchToSettings) {
                        this.themeTab = 'theme-settings';
                    }
                },
                paletteMatches(values) {
                    return Object.entries(values).every(([key, value]) => String(this.preview[key] ?? '').toLowerCase() === String(value ?? '').toLowerCase());
                },
                previewFont() {
                    return this.fontStacks[this.preview.font_family] || this.fontStacks.inter;
                },
                submitThemeForm(form, event) {
                    try {
                        const formData = new FormData(form);
                        formData.set('intent', this.submitIntent || '');
                        const params = new URLSearchParams();
                        for (const [key, value] of formData.entries()) {
                            params.append(key, String(value));
                        }
                        $wire.submit(params.toString());
                    } catch (error) {
                        console.error('Livewire submit failed, fallback to standard submit.', error);
                        form.removeAttribute('x-on:submit.prevent');
                        form.requestSubmit(event?.submitter ?? undefined);
                    }
                },
            }"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" x-model="themeTab">
            <input type="hidden" name="frontend_theme" x-model="themeName">
            <input type="hidden" name="intent" x-model="submitIntent">

            <div class="grid gap-8 xl:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="space-y-3 xl:sticky xl:top-24 xl:self-start">
                    <x-theme.sidebar-tabs
                        :library-count="count($guestThemes)"
                        :customize-count="count($frontendThemeSchema)"
                        :library-description="__('Browse available guest themes')"
                        :customize-description="__('Tune branding and layout tokens')"
                    />

                    @if (! empty($frontendThemeSchema))
                        <div x-show="themeTab === 'theme-settings'" x-cloak>
                            <x-theme.section-card :title="__('Transfer settings')" body-class="space-y-5 p-5">
                                <div class="space-y-2">
                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Export the current guest theme as JSON, or open the import modal to restore a previously exported preset.') }}</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-ui.button :href="route('admin-themes.frontend.export')" variant="outline" class="w-full justify-center whitespace-nowrap">
                                            {{ __('Export JSON') }}
                                        </x-ui.button>
                                        <x-ui.button type="button" variant="secondary" x-on:click="importModalOpen = true" class="w-full justify-center whitespace-nowrap">
                                            {{ __('Import JSON') }}
                                        </x-ui.button>
                                    </div>
                                </div>
                            </x-theme.section-card>
                        </div>
                    @endif
                </aside>

                <div class="space-y-8">
                    <div x-show="themeTab === 'select-theme'" x-cloak>
                        <x-theme.section-card
                            :title="__('Guest area')"
                            :description="__('Select the public-facing theme used for landing pages, authentication, and guest-facing screens.')"
                            body-class="space-y-5 p-6"
                        >
                            <x-slot:meta>
                                <span class="hidden rounded-full border border-slate-200/85 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 md:inline-flex dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">
                                    {{ count($guestThemes) }} {{ __('themes') }}
                                </span>
                            </x-slot:meta>

                            <div class="flex flex-col gap-4 rounded-[1.1rem] border border-slate-200/80 bg-[linear-gradient(180deg,#fbfdff_0%,#f7faff_100%)] p-5 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.12)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#0f172a_0%,#111827_100%)] lg:flex-row lg:items-end lg:justify-between">
                                <div class="space-y-1.5">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500">
                                        <i class="fa-light fa-compass text-[10px]"></i>
                                        {{ __('Library filters') }}
                                    </div>
                                    <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Find the right guest theme faster') }}</p>
                                    <p class="max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Browse themes by tag and narrow the list to themes that advertise dark mode support.') }}</p>
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <label class="block">
                                        <span class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Tag') }}</span>
                                        <div class="relative">
                                            <select x-model="libraryTagFilter" class="h-11 min-w-[220px] appearance-none rounded-[0.85rem] border border-slate-200 bg-white px-4 pr-12 text-sm font-medium text-slate-700 outline-none shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                <option value="all">{{ __('All tags') }}</option>
                                                @foreach ($frontendLibraryTags as $tag)
                                                    <option value="{{ $tag }}">{{ str($tag)->replace('-', ' ')->headline() }}</option>
                                                @endforeach
                                            </select>
                                            <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-slate-400"><i class="fa-light fa-chevron-down text-xs"></i></span>
                                        </div>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Support') }}</span>
                                        <div class="relative">
                                            <select x-model="libraryDarkFilter" class="h-11 min-w-[220px] appearance-none rounded-[0.85rem] border border-slate-200 bg-white px-4 pr-12 text-sm font-medium text-slate-700 outline-none shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                <option value="all">{{ __('All themes') }}</option>
                                                <option value="dark">{{ __('Supports dark mode') }}</option>
                                            </select>
                                            <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-slate-400"><i class="fa-light fa-chevron-down text-xs"></i></span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="grid gap-5 lg:grid-cols-2 2xl:grid-cols-3">
                                @foreach ($guestThemes as $theme)
                                    @php($previewImage = $theme->meta['thumbnail'] ?? $theme->meta['preview'] ?? null)
                                    @php($supports = is_array($theme->meta['supports'] ?? null) ? $theme->meta['supports'] : [])
                                    @php($tags = is_array($theme->meta['tags'] ?? null) ? $theme->meta['tags'] : [])
                                    @php($recommended = is_array($theme->meta['recommended_for'] ?? null) ? $theme->meta['recommended_for'] : [])
                                    @php($themeDefaults = $guestThemeDefaults[$theme->name] ?? [])
                                    <label class="block cursor-pointer" x-show="matchesLibraryTheme(@js($theme->meta))">
                                        <input type="radio" name="frontend_theme" value="{{ $theme->name }}" class="sr-only peer" @checked(old('frontend_theme', $frontendTheme) === $theme->name) x-bind:checked="themeName === @js($theme->name)" x-on:change="selectTheme(@js($theme->name), @js($themeDefaults))">
                                        <span class="relative flex h-full flex-col overflow-visible rounded-[1.15rem] border border-slate-200/85 bg-[linear-gradient(180deg,#ffffff_0%,#fbfdff_100%)] shadow-[0_24px_48px_-38px_rgba(15,23,42,0.16)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_30px_54px_-40px_rgba(15,23,42,0.22)] peer-checked:border-[var(--theme-accent,#4f46e5)] peer-checked:shadow-[0_28px_58px_-40px_rgba(79,70,229,0.24)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#0f172a_0%,#111827_100%)] dark:peer-checked:border-[var(--theme-accent,#818cf8)]">
                                            <span class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--theme-accent,#4f46e5)]/35 to-transparent opacity-0 transition peer-checked:opacity-100"></span>
                                            <span class="flex items-start justify-between gap-3 px-5 pb-4 pt-5">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ $theme->meta['name'] ?? ucfirst($theme->name) }}</span>
                                                    <span class="mt-1.5 block text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">{{ $theme->name }}</span>
                                                </span>
                                                <span class="inline-flex items-center rounded-full border border-slate-200/85 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">{{ __('Guest') }}</span>
                                            </span>
                                            <div class="space-y-4 p-5">
                                                <div class="overflow-hidden rounded-[1rem] border border-slate-200/80 bg-[linear-gradient(180deg,#f8fbff_0%,#f3f7ff_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.85)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#111827_0%,#0f172a_100%)]">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span class="px-4 pt-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">{{ __('Preview') }}</span>
                                                        <div class="mr-4 mt-4 flex items-center gap-2">
                                                            @if (in_array('dark-mode', $supports, true))
                                                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">{{ __('Dark mode') }}</span>
                                                            @endif
                                                            <span class="inline-flex size-7 items-center justify-center rounded-full border border-slate-200/85 bg-white text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"><i class="fa-light fa-browser"></i></span>
                                                        </div>
                                                    </div>
                                                    @if ($previewImage)
                                                        <div class="px-4 pb-4 pt-3">
                                                            <div class="overflow-hidden rounded-[0.8rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950">
                                                                <img src="{{ theme_asset_for($theme, $previewImage) }}" alt="{{ $theme->meta['name'] ?? $theme->name }}" class="block h-44 w-full object-cover object-top">
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="px-4 pb-4 pt-3">
                                                            <div class="space-y-2.5">
                                                                <div class="h-2.5 rounded-full bg-slate-200/95 dark:bg-slate-800"></div>
                                                                <div class="h-2.5 w-4/5 rounded-full bg-slate-200/80 dark:bg-slate-800/80"></div>
                                                                <div class="grid grid-cols-2 gap-2 pt-2">
                                                                    <div class="h-16 rounded-[0.8rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950"></div>
                                                                    <div class="h-16 rounded-[0.8rem] border border-slate-200/70 bg-white shadow-[0_10px_28px_-22px_rgba(15,23,42,0.22)] dark:border-slate-800 dark:bg-slate-950"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="space-y-3">
                                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $theme->meta['description'] ?? __('No description provided.') }}</p>
                                                    <div class="grid gap-2">
                                                        <div class="flex items-center justify-between gap-3 rounded-[0.9rem] border border-slate-200/80 bg-slate-50/70 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                                                            <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Author') }}</span>
                                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $theme->meta['author'] ?? __('Unknown') }}</span>
                                                        </div>
                                                    </div>
                                                    @if ($tags)
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach ($tags as $tag)
                                                                <span class="inline-flex items-center rounded-full border border-slate-200/80 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">{{ str($tag)->replace('-', ' ')->headline() }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="mt-auto flex items-center border-t border-slate-200/80 bg-slate-50/70 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/40">
                                                <span class="inline-flex items-center gap-2 text-sm font-medium" :class="themeName === @js($theme->name) ? 'text-slate-950 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                                                    <span class="inline-flex size-2 rounded-full" :class="themeName === @js($theme->name) ? 'bg-[var(--theme-accent,#4f46e5)] shadow-[0_0_0_4px_rgba(var(--theme-accent-rgb),0.12)]' : 'bg-slate-300 dark:bg-slate-700'"></span>
                                                    <span x-text="themeName === @js($theme->name) ? @js(__('Selected')) : @js(__('Available'))"></span>
                                                </span>
                                            </div>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </x-theme.section-card>
                    </div>

                    @if (! empty($frontendThemeSchema))
                        <div x-show="themeTab === 'theme-settings'" x-cloak class="space-y-6 pb-28">
                            <div id="frontend-theme-settings" class="space-y-6">
                                <div class="space-y-1">
                                    <h2 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Guest theme customization') }}</h2>
                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Control branding, colors, typography, and presentation settings for the guest theme.') }}</p>
                                </div>

                                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)]">
                                    <div class="space-y-6">
                                        @if (collect($frontendColorKeys)->contains(fn ($key) => isset($frontendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Color system')" body-class="space-y-5 p-5">
                                                <div class="rounded-[0.9rem] border border-dashed border-slate-300/90 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <div>
                                                            <h4 class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Preset palettes') }}</h4>
                                                            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Apply a full guest color set in one click, then fine-tune individual colors below if needed.') }}</p>
                                                        </div>
                                                        <span class="inline-flex shrink-0 whitespace-nowrap rounded-full border border-slate-200/80 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500">{{ __('Quick apply') }}</span>
                                                    </div>

                                                    <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                                        @foreach ($frontendPalettePresets as $palette)
                                                            <button
                                                                type="button"
                                                                x-on:click="applyPalette(@js($palette['values']))"
                                                                class="group rounded-[0.95rem] border border-slate-200/85 bg-white p-4 text-left shadow-[0_12px_34px_-28px_rgba(15,23,42,0.14)] transition hover:border-slate-300 hover:shadow-[0_18px_40px_-32px_rgba(15,23,42,0.18)] dark:border-slate-800 dark:bg-slate-950/60 dark:hover:border-slate-700"
                                                                x-bind:class="paletteMatches(@js($palette['values'])) ? 'border-[var(--theme-accent)] bg-[color:rgba(var(--theme-accent-rgb),0.08)] shadow-[0_18px_42px_-30px_rgba(var(--theme-accent-rgb),0.35)] dark:border-[var(--theme-accent)]' : ''"
                                                            >
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $palette['name'] }}</p>
                                                                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $palette['description'] }}</p>
                                                                    </div>
                                                                    <span class="rounded-full border border-slate-200/80 bg-slate-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500" x-text="paletteMatches(@js($palette['values'])) ? @js(__('Active')) : @js(__('Palette'))"></span>
                                                                </div>

                                                                <div class="mt-4 flex flex-wrap gap-2">
                                                                    @foreach ($palette['values'] as $swatch)
                                                                        <span class="size-7 rounded-full border border-white shadow-sm ring-1 ring-black/5 dark:border-slate-900" style="background-color: {{ $swatch }}"></span>
                                                                    @endforeach
                                                                </div>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <div class="grid gap-5 md:grid-cols-2">
                                                    @foreach ($frontendColorKeys as $key)
                                                        @continue(! isset($frontendThemeSchema[$key]))
                                                        @php($field = $frontendThemeSchema[$key])
                                                        <x-theme.swatch-editor
                                                            :input-name="'frontend_settings[' . $key . ']'"
                                                            :key-name="$key"
                                                            :label="__($field['label'] ?? str($key)->headline())"
                                                            :value="old('frontend_settings.' . $key, $frontendThemeValues[$key] ?? ($field['default'] ?? '#ffffff'))"
                                                            :presets="$frontendColorPresets[$key] ?? []"
                                                            preview-state="preview"
                                                            :picker-ref="'frontendColor' . $loop->index"
                                                        />
                                                    @endforeach
                                                </div>
                                            </x-theme.section-card>
                                        @endif

                                        @if (collect($frontendTypographyKeys)->contains(fn ($key) => isset($frontendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Typography')" body-class="space-y-5 p-5">
                                                @foreach ($frontendTypographyKeys as $key)
                                                    @continue(! isset($frontendThemeSchema[$key]))
                                                    @php($field = $frontendThemeSchema[$key])
                                                    <label class="block">
                                                        <span class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __($field['label'] ?? str($key)->headline()) }}</span>
                                                        <div class="relative">
                                                            <select name="frontend_settings[{{ $key }}]" class="h-11 w-full appearance-none rounded-[0.65rem] border border-slate-200 bg-white px-4 pr-14 text-sm font-medium text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                                @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                                                    <option value="{{ $optionValue }}" @selected(old("frontend_settings.$key", $frontendThemeValues[$key] ?? ($field['default'] ?? null)) == $optionValue)>{{ __($optionLabel) }}</option>
                                                                @endforeach
                                                            </select>
                                                            <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-slate-400">
                                                                <i class="fa-light fa-chevron-down text-xs"></i>
                                                            </span>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif

                                        @if (collect($frontendLayoutKeys)->contains(fn ($key) => isset($frontendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Layout & appearance')" body-class="space-y-5 p-5">
                                                @foreach ($frontendLayoutKeys as $key)
                                                    @continue(! isset($frontendThemeSchema[$key]))
                                                    @php($field = $frontendThemeSchema[$key])
                                                    <x-ui.radio-group
                                                        :name="'frontend_settings[' . $key . ']'"
                                                        :label="__($field['label'] ?? str($key)->headline())"
                                                        :value="old('frontend_settings.' . $key, $frontendThemeValues[$key] ?? ($field['default'] ?? null))"
                                                        :options="collect($field['options'] ?? [])->map(fn ($optionLabel, $optionValue) => [
                                                            'value' => $optionValue,
                                                            'label' => __($optionLabel),
                                                        ])->values()->all()"
                                                    />
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif

                                        @if (collect($frontendAdvancedKeys)->contains(fn ($key) => isset($frontendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Advanced')" body-class="space-y-5 p-5">
                                                @foreach ($frontendAdvancedKeys as $key)
                                                    @continue(! isset($frontendThemeSchema[$key]))
                                                    @php($field = $frontendThemeSchema[$key])
                                                    <x-ui.code-editor
                                                        :name="'frontend_settings[' . $key . ']'"
                                                        :label="__($field['label'] ?? str($key)->headline())"
                                                        :value="old('frontend_settings.' . $key, $frontendThemeValues[$key] ?? ($field['default'] ?? ''))"
                                                        :mode="str_contains($key, 'js') ? 'javascript' : 'css'"
                                                    />
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif

                                        @if (collect($frontendComponentKeys)->contains(fn ($key) => isset($frontendThemeSchema[$key])))
                                            <x-theme.section-card :title="__('Components')" body-class="space-y-5 p-5">
                                                @foreach ($frontendComponentKeys as $key)
                                                    @continue(! isset($frontendThemeSchema[$key]))
                                                    @php($field = $frontendThemeSchema[$key])
                                                    @if (($field['type'] ?? null) === 'number')
                                                        @php($value = old("frontend_settings.$key", $frontendThemeValues[$key] ?? ($field['default'] ?? null)))
                                                        @php($numericValue = is_numeric($value)
                                                            ? $value
                                                            : (str_ends_with((string) $value, 'rem')
                                                                ? round((float) preg_replace('/[^\d.]/', '', (string) $value) * 16)
                                                                : preg_replace('/[^\d.]/', '', (string) $value)))
                                                        <div class="space-y-2.5">
                                                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __($field['label'] ?? str($key)->headline()) }}</span>
                                                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem]">
                                                                <div class="flex items-center rounded-[0.8rem] border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                                                                    <input
                                                                        type="range"
                                                                        min="{{ $field['min'] ?? 0 }}"
                                                                        max="{{ $field['max'] ?? 100 }}"
                                                                        step="{{ $field['step'] ?? 1 }}"
                                                                        value="{{ $numericValue }}"
                                                                        class="h-2 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-[var(--theme-accent)] dark:bg-slate-700"
                                                                        oninput="this.closest('div').nextElementSibling.querySelector('input').value = this.value"
                                                                    >
                                                                </div>
                                                                <div class="flex items-center overflow-hidden rounded-[0.8rem] border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                                                                    <input
                                                                        type="number"
                                                                        name="frontend_settings[{{ $key }}]"
                                                                        min="{{ $field['min'] ?? 0 }}"
                                                                        max="{{ $field['max'] ?? 100 }}"
                                                                        step="{{ $field['step'] ?? 1 }}"
                                                                        value="{{ $numericValue }}"
                                                                        class="h-11 w-full border-0 bg-transparent px-3 text-sm font-medium text-slate-700 outline-none focus:ring-0 dark:text-slate-200"
                                                                        oninput="this.closest('.grid').querySelector('input[type=range]').value = this.value"
                                                                    >
                                                                    <span class="border-l border-slate-200 px-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:border-slate-700 dark:text-slate-500">{{ $field['unit'] ?? 'px' }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <x-ui.radio-group
                                                            :name="'frontend_settings[' . $key . ']'"
                                                            :label="__($field['label'] ?? str($key)->headline())"
                                                            :value="old('frontend_settings.' . $key, $frontendThemeValues[$key] ?? ($field['default'] ?? null))"
                                                            :options="collect($field['options'] ?? [])->map(fn ($optionLabel, $optionValue) => [
                                                                'value' => $optionValue,
                                                                'label' => __($optionLabel),
                                                            ])->values()->all()"
                                                        />
                                                    @endif
                                                @endforeach
                                            </x-theme.section-card>
                                        @endif
                                    </div>

                                    <div class="space-y-6">
                                        <div class="xl:sticky xl:top-24">
                                            <x-theme.preview-panel variant="frontend" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <x-theme.sticky-save-bar
                        :title="__('Ready to publish changes?')"
                        :description="__('Save the active guest theme configuration.')"
                        :reset-label="__('Set default')"
                        :save-label="__('Save frontend theme')"
                        form-id="frontend-theme-form"
                        intent-model="submitIntent"
                    />
                </div>
            </div>

            <template x-teleport="body">
                <div x-cloak x-show="importModalOpen" class="fixed inset-0 z-[120] flex items-center justify-center p-6" x-on:keydown.escape.window="importModalOpen = false">
                    <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" x-on:click="importModalOpen = false"></div>

                    <div x-show="importModalOpen" x-transition.opacity.scale.90 class="relative w-full max-w-2xl">
                        <div class="rounded-[var(--theme-card-radius,0.9rem)] border border-slate-200 bg-white shadow-[0_30px_80px_-30px_rgba(15,23,42,0.35)] dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('Import frontend theme JSON') }}</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Paste a JSON export to restore guest theme colors, typography, and layout settings.') }}</p>
                                </div>
                                <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="importModalOpen = false">
                                    <i class="fa-light fa-xmark text-lg"></i>
                                </button>
                            </div>

                            <div class="px-6 py-5">
                                <x-ui.textarea
                                    form="frontend-theme-form"
                                    name="frontend_import_json"
                                    :label="__('Import JSON')"
                                    rows="11"
                                    :error="$errors->first('frontend_import_json')"
                                >{{ old('frontend_import_json') }}</x-ui.textarea>
                            </div>

                            <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="importModalOpen = false">
                                        {{ __('Cancel') }}
                                    </x-ui.button>
                                    <x-ui.button type="submit" form="frontend-theme-form" variant="secondary" name="intent" value="import_settings" x-on:click="submitIntent = 'import_settings'">
                                        {{ __('Import settings') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            </form>
        </div>

        <link rel="stylesheet" href="{{ theme_shared_asset('plugins/codemirror5/lib/codemirror.css') }}">
        <link rel="stylesheet" href="{{ theme_shared_asset('plugins/codemirror5/theme/material-darker.css') }}">
        <script src="{{ theme_shared_asset('plugins/codemirror5/lib/codemirror.js') }}"></script>
        <script src="{{ theme_shared_asset('plugins/codemirror5/mode/css/css.js') }}"></script>
        <script src="{{ theme_shared_asset('plugins/codemirror5/mode/javascript/javascript.js') }}"></script>
        <script>
            (() => {
                const initThemeCodeEditors = () => {
                    document.querySelectorAll('textarea[data-code-editor]').forEach((textarea) => {
                        if (textarea.dataset.editorReady === 'true') {
                            return;
                        }

                        textarea.dataset.editorReady = 'true';

                        const editor = CodeMirror.fromTextArea(textarea, {
                            mode: textarea.dataset.codeEditor,
                            theme: 'material-darker',
                            lineNumbers: true,
                            lineWrapping: true,
                            indentUnit: 4,
                            tabSize: 4,
                            indentWithTabs: false,
                            viewportMargin: Infinity,
                        });

                        editor.setSize(null, 280);
                        editor.on('change', (instance) => {
                            textarea.value = instance.getValue();
                        });
                    });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initThemeCodeEditors, { once: true });
                } else {
                    initThemeCodeEditors();
                }
            })();
        </script>
    </div>
