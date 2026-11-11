@props([
    'variant' => 'frontend',
    'title' => __('Live preview'),
])

<x-theme.section-card :title="$title" body-class="p-5">
    <div
        x-data="{
            previewAppearance: preview.default_appearance || 'system',
            supportsDark() {
                return String(preview.supports_dark_mode ?? '1') !== '0';
            },
            resolvedAppearance() {
                if (!this.supportsDark()) {
                    return 'light';
                }

                if (this.previewAppearance === 'dark') {
                    return 'dark';
                }

                if (this.previewAppearance === 'light') {
                    return 'light';
                }

                return 'system';
            },
            isDark() {
                return this.resolvedAppearance() === 'dark';
            },
            hexToRgb(value) {
                const normalized = String(value || '').trim().replace('#', '');
                if (!/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(normalized)) {
                    return null;
                }

                const full = normalized.length === 3
                    ? normalized.split('').map((char) => char + char).join('')
                    : normalized;

                const int = Number.parseInt(full, 16);

                return {
                    r: (int >> 16) & 255,
                    g: (int >> 8) & 255,
                    b: int & 255,
                };
            },
            mixWithWhite(value, whiteRatio = 0.75) {
                const rgb = this.hexToRgb(value);
                if (!rgb) {
                    return '#e2e8f0';
                }

                const mix = (channel) => Math.round((255 * whiteRatio) + (channel * (1 - whiteRatio)));

                return `rgb(${mix(rgb.r)}, ${mix(rgb.g)}, ${mix(rgb.b)})`;
            },
            themedValue(lightKey, darkKey, fallback = null) {
                if (this.isDark() && preview[darkKey]) {
                    return preview[darkKey];
                }

                return preview[lightKey] || fallback;
            },
            accentColor() {
                return this.themedValue('accent_color', 'dark_accent_color', '#4f46e5');
            },
            linkColor() {
                return this.themedValue('link_color', 'dark_link_color', this.accentColor());
            },
            headerActiveColor() {
                return this.themedValue('header_active_color', 'dark_header_active_color', '#0f172a');
            },
            successColor() {
                return this.themedValue('success_color', 'dark_success_color', '#059669');
            },
            warningColor() {
                return this.themedValue('warning_color', 'dark_warning_color', '#d97706');
            },
            previewBorder() {
                return this.themedValue('border_color', 'dark_border_color', '#cbd5e1');
            },
            previewMuted() {
                return this.themedValue('muted_text_color', 'dark_muted_text_color', '#64748b');
            },
            backendSidebarBg() {
                return this.themedValue('sidebar_bg_color', 'dark_sidebar_bg_color', '#f5f7fb');
            },
            backendSidebarText() {
                return this.themedValue('sidebar_text_color', 'dark_sidebar_text_color', '#475569');
            },
            backendHeaderBg() {
                return this.themedValue('header_bg_color', 'dark_header_bg_color', '#ffffff');
            },
            backendHeaderText() {
                return this.themedValue('header_text_color', 'dark_header_text_color', '#0f172a');
            },
        }"
        class="space-y-4 transition-all duration-200"
        :class="preview.preview_mode === 'mobile' ? 'mx-auto max-w-[320px]' : (preview.preview_mode === 'tablet' ? 'mx-auto max-w-[620px]' : 'w-full')"
        :style="{ maxWidth: preview.preview_mode === 'desktop' ? (preview.page_max_width || '100%') : '' }"
    >
        <div class="rounded-[1.05rem] border border-slate-200/85 bg-[linear-gradient(180deg,#fbfdff_0%,#f5f8ff_100%)] p-4 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.18)] dark:border-slate-800 dark:bg-[linear-gradient(180deg,#0f172a_0%,#0b1220_100%)]">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-slate-200/85 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">
                            {{ __('Preview mode') }}
                        </span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted(), backgroundColor: previewBorder() + '5e' }" x-text="(preview.preview_mode || 'desktop')"></span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Interactive theme canvas') }}</p>
                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Compare structure, hierarchy, and contrast before committing the current token set.') }}</p>
                    </div>
                </div>

                <div class="inline-flex items-center gap-1 rounded-[0.95rem] border border-slate-200/85 bg-white p-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <button type="button" class="rounded-[0.75rem] px-3 py-1.5 text-xs font-semibold transition" :class="previewAppearance === 'light' ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-500 dark:text-slate-400'" x-on:click="previewAppearance = 'light'">{{ __('Light') }}</button>
                    <button type="button" class="rounded-[0.75rem] px-3 py-1.5 text-xs font-semibold transition" x-bind:disabled="!supportsDark()" x-bind:class="!supportsDark() ? 'cursor-not-allowed opacity-40 text-slate-500 dark:text-slate-400' : (previewAppearance === 'dark' ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-500 dark:text-slate-400')" x-on:click="if (supportsDark()) previewAppearance = 'dark'">{{ __('Dark') }}</button>
                    <button type="button" class="rounded-[0.75rem] px-3 py-1.5 text-xs font-semibold transition" x-bind:disabled="!supportsDark()" x-bind:class="!supportsDark() ? 'cursor-not-allowed opacity-40 text-slate-500 dark:text-slate-400' : (previewAppearance === 'system' ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-500 dark:text-slate-400')" x-on:click="if (supportsDark()) previewAppearance = 'system'">{{ __('System') }}</button>
                </div>
            </div>
        </div>

        @if ($variant === 'backend')
            <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_24px_56px_-36px_rgba(15,23,42,0.28)]" :style="{ backgroundColor: isDark() ? '#020617' : '#f8fbff', borderColor: previewBorder(), fontFamily: previewFont() }">
                <div class="flex items-center justify-between border-b px-4 py-3" :style="{ backgroundColor: isDark() ? '#081120' : '#ffffff', borderColor: previewBorder() }">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-rose-300/80"></span>
                        <span class="size-2 rounded-full bg-amber-300/80"></span>
                        <span class="size-2 rounded-full bg-emerald-300/80"></span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted() }">
                        <span>{{ __('Workspace') }}</span>
                        <span class="rounded-full px-2 py-1" :style="{ backgroundColor: accentColor() + '14', color: linkColor() }">{{ __('Live') }}</span>
                    </div>
                </div>

                <div class="grid min-h-[250px] grid-cols-[58px_minmax(0,1fr)]">
                    <aside class="relative border-r px-3 py-4" :style="{ backgroundColor: backendSidebarBg(), borderColor: previewBorder(), color: backendSidebarText() }">
                        <div class="absolute inset-x-0 top-0 h-20 opacity-80" :style="{ background: 'linear-gradient(180deg, ' + accentColor() + '18 0%, transparent 100%)' }"></div>
                        <div class="relative space-y-2">
                            <div class="flex h-9 items-center justify-center rounded-[0.85rem]" :style="{ backgroundColor: isDark() ? '#111827' : '#ffffff', border: '1px solid ' + previewBorder() }">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-[0.7rem] text-white shadow-sm" :style="{ backgroundColor: accentColor() }">
                                    <i class="fa-light fa-grid-2 text-xs"></i>
                                </span>
                            </div>

                            <template x-for="index in 2" :key="index">
                                <div class="rounded-[0.8rem] p-1.5 transition" :style="index === 1 ? { backgroundColor: accentColor() + '16' } : {}">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-[0.65rem]" :style="index === 1 ? { backgroundColor: accentColor(), color: '#ffffff' } : { backgroundColor: isDark() ? '#1e293b' : '#ffffff', color: backendSidebarText(), border: '1px solid ' + previewBorder() }">
                                            <i class="fa-light fa-grid-2 text-xs"></i>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </aside>

                    <div class="min-w-0" :style="{ backgroundColor: isDark() ? '#0b1220' : '#f8fbff' }">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3" :style="{ backgroundColor: backendHeaderBg(), borderColor: previewBorder(), color: backendHeaderText() }">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted() }">
                                    <span class="rounded-full px-2 py-1" :style="{ color: headerActiveColor(), backgroundColor: isDark() ? accentColor() + '22' : headerActiveColor() + '12' }">{{ __('Dashboard') }}</span>
                                    <span>{{ __('Admin shell') }}</span>
                                </div>
                                <h4 class="text-sm font-semibold tracking-[-0.02em]" :style="{ color: backendHeaderText() }">{{ __('Command center') }}</h4>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="hidden text-xs font-medium 2xl:inline" :style="{ color: previewMuted() }">{{ __('Density') }}</span>
                                <span class="inline-flex h-8 items-center rounded-full px-3.5 text-sm font-semibold text-white shadow-sm" :style="{ backgroundColor: accentColor() }">{{ __('New') }}</span>
                            </div>
                        </div>

                        <div class="space-y-3 p-3" :style="{ gap: '0.75rem' }">
                            <div class="overflow-hidden rounded-[1rem] border shadow-sm" :style="{ backgroundColor: isDark() ? '#081120' : '#ffffff', borderColor: previewBorder() }">
                                <div class="border-b px-4 py-3" :style="{ borderColor: previewBorder() }">
                                    <div class="space-y-2">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: linkColor(), backgroundColor: linkColor() + '12' }">{{ __('Overview') }}</span>
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <h5 class="text-[15px] font-semibold tracking-[-0.03em]" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Typeface review') }}</h5>
                                                <p class="mt-1 text-sm leading-6" :style="{ color: previewMuted() }">{{ __('Quick pass on hierarchy and contrast.') }}</p>
                                            </div>
                                            <span class="inline-flex rounded-[0.85rem] px-3 py-2 text-sm font-semibold text-white shadow-sm" :style="{ backgroundColor: accentColor() }">{{ __('Approve') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-2.5 px-4 py-3 sm:grid-cols-2">
                                    <div class="rounded-[0.9rem] p-3" :style="{ backgroundColor: accentColor() + '12' }">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted() }">{{ __('Conversion') }}</p>
                                        <p class="mt-1.5 text-base font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">18.4%</p>
                                    </div>
                                    <div class="rounded-[0.9rem] p-3" :style="{ backgroundColor: successColor() + '14' }">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted() }">{{ __('Approved') }}</p>
                                        <p class="mt-1.5 text-base font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">24</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1rem] border p-3.5 shadow-sm" :style="{ backgroundColor: isDark() ? '#081120' : '#ffffff', borderColor: previewBorder() }">
                                    <div class="flex items-center justify-between">
                                        <div class="h-8 w-8 rounded-full" :style="{ backgroundColor: accentColor() + '15' }"></div>
                                        <span class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted() }">{{ __('Styles') }}</span>
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        <p class="text-sm font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Heading sample Aa') }}</p>
                                        <p class="text-sm leading-6" :style="{ color: previewMuted() }">{{ __('Readable labels and calm body copy.') }}</p>
                                    </div>
                                </div>

                                <div class="rounded-[1rem] border p-3.5 shadow-sm" :style="{ backgroundColor: isDark() ? '#081120' : '#ffffff', borderColor: previewBorder() }">
                                    <div class="flex items-center justify-between border-b pb-2.5" :style="{ borderColor: previewBorder() }">
                                        <span class="text-xs font-semibold uppercase tracking-[0.18em]" :style="{ color: previewMuted() }">{{ __('Rows') }}</span>
                                        <span class="text-xs font-medium" :style="{ color: previewMuted() }">{{ __('Live') }}</span>
                                    </div>
                                    <div class="space-y-2 pt-2.5">
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="font-medium" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Atlas') }}</span>
                                            <span :style="{ color: successColor() }">{{ __('Ready') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="font-medium" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Refresh') }}</span>
                                            <span :style="{ color: warningColor() }">{{ __('Draft') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_24px_56px_-36px_rgba(15,23,42,0.24)]" :style="{ backgroundColor: isDark() ? '#020617' : (preview.body_bg_color || '#f8faff'), borderColor: preview.border_color || '#e2e8f0', fontFamily: previewFont() }">
                <div class="relative border-b px-5 py-4" :style="{ backgroundColor: preview.header_bg_color || '#ffffff', borderColor: preview.border_color || '#e2e8f0' }">
                    <div class="absolute inset-x-0 top-0 h-16 opacity-70" :style="{ background: 'linear-gradient(180deg, ' + (preview.accent_color || '#0f766e') + '14 0%, transparent 100%)' }"></div>
                    <div class="relative flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-rose-300/80"></span>
                            <span class="size-2 rounded-full bg-amber-300/80"></span>
                            <span class="size-2 rounded-full bg-emerald-300/80"></span>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">
                            <span class="rounded-full px-2 py-1" :style="{ color: preview.header_active_color || '#0f172a', backgroundColor: (preview.header_active_color || '#0f172a') + '12' }">{{ __('Home') }}</span>
                            <span>{{ __('Pricing') }}</span>
                            <span>{{ __('Sign in') }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-5" :style="{ gap: preview.section_spacing || '5rem' }">
                    <div class="overflow-hidden rounded-[1.1rem] border shadow-sm" :style="{ backgroundColor: isDark() ? '#0f172a' : (preview.surface_bg_color || '#ffffff'), borderColor: preview.border_color || '#e2e8f0' }">
                        <div class="grid gap-6 p-5 2xl:grid-cols-[minmax(0,1.25fr)_220px]">
                            <div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.link_color || '#4f46e5', backgroundColor: (preview.link_color || '#4f46e5') + '12' }">{{ __('Theme preview') }}</span>
                                <h4 class="mt-4 text-[1.65rem] font-semibold tracking-[-0.04em]" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('A polished public-facing theme') }}</h4>
                                <p class="mt-3 max-w-xl text-sm leading-6" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Use the guest customizer to compare headline presence, body readability, and CTA contrast before publishing the landing experience.') }}</p>

                                <div class="mt-5 flex flex-wrap items-center gap-3">
                                    <span class="inline-flex rounded-[0.9rem] px-4 py-2.5 text-sm font-semibold text-white shadow-sm" :style="{ backgroundColor: preview.accent_color || '#0f766e' }">{{ __('Get started') }}</span>
                                    <span class="text-sm font-medium" :style="{ color: preview.link_color || '#4f46e5' }">{{ __('View docs') }}</span>
                                </div>
                            </div>

                            <div class="rounded-[1rem] border p-4" :style="{ backgroundColor: isDark() ? '#081120' : '#fbfcff', borderColor: preview.border_color || '#e2e8f0' }">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Status') }}</span>
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.success_color || '#059669', backgroundColor: (preview.success_color || '#059669') + '14' }">{{ __('Ready') }}</span>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <div class="rounded-[0.8rem] p-3" :style="{ backgroundColor: (preview.accent_color || '#0f766e') + '12' }">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Launch score') }}</p>
                                        <p class="mt-2 text-lg font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">92</p>
                                    </div>
                                    <div class="rounded-[0.8rem] p-3" :style="{ backgroundColor: (preview.warning_color || '#d97706') + '12' }">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Pending') }}</p>
                                        <p class="mt-2 text-lg font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">3</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_minmax(240px,0.9fr)]">
                        <div class="rounded-[1rem] border p-5 shadow-sm" :style="{ backgroundColor: isDark() ? '#0f172a' : (preview.surface_bg_color || '#ffffff'), borderColor: preview.border_color || '#e2e8f0' }">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Reading sample') }}</span>
                                <span class="h-9 w-9 rounded-full" :style="{ backgroundColor: (preview.accent_color || '#0f766e') + '15' }"></span>
                            </div>
                            <h5 class="mt-4 text-lg font-semibold tracking-[-0.03em]" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Sharper headlines, calmer paragraphs') }}</h5>
                            <p class="mt-3 text-sm leading-7" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('The best guest themes make pricing, product positioning, and signup prompts easy to scan without feeling cramped.') }}</p>
                            <div class="mt-5 grid gap-3 2xl:grid-cols-3">
                                <div class="rounded-[0.85rem] p-3" :style="{ backgroundColor: (preview.success_color || '#059669') + '12' }">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Signup') }}</p>
                                    <p class="mt-2 text-base font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">4.8%</p>
                                </div>
                                <div class="rounded-[0.85rem] p-3" :style="{ backgroundColor: (preview.warning_color || '#d97706') + '12' }">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Trials') }}</p>
                                    <p class="mt-2 text-base font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">128</p>
                                </div>
                                <div class="rounded-[0.85rem] p-3" :style="{ backgroundColor: (preview.danger_color || '#dc2626') + '12' }">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Drop-off') }}</p>
                                    <p class="mt-2 text-base font-semibold" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">11%</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[1rem] border p-4 shadow-sm" :style="{ backgroundColor: isDark() ? '#0f172a' : (preview.surface_bg_color || '#ffffff'), borderColor: preview.border_color || '#e2e8f0' }">
                            <div class="flex items-center justify-between border-b pb-3" :style="{ borderColor: preview.border_color || '#e2e8f0' }">
                                <span class="text-xs font-semibold uppercase tracking-[0.18em]" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Plan row') }}</span>
                                <span class="text-xs font-medium" :style="{ color: preview.muted_text_color || '#64748b' }">{{ __('Table') }}</span>
                            </div>
                            <div class="space-y-3 pt-3">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Starter plan') }}</span>
                                    <span :style="{ color: preview.success_color || '#059669' }">{{ __('$19/mo') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Growth plan') }}</span>
                                    <span :style="{ color: preview.warning_color || '#d97706' }">{{ __('$49/mo') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium" :style="{ color: isDark() ? '#f8fafc' : '#0f172a' }">{{ __('Enterprise') }}</span>
                                    <span :style="{ color: preview.danger_color || '#dc2626' }">{{ __('Custom') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-theme.section-card>
