@php
    $accountSelectorOptions = collect([
        [
            'value' => '',
            'label' => __('All Accounts'),
            'description' => __('Apply a shared watermark across every connected account.'),
            'icon' => 'fa-light fa-layer-group',
        ],
    ])->merge(
        $accounts->map(function ($account) {
            $subtitle = collect([
                filled($account->username ?? null) ? '@'.$account->username : null,
                filled($account->provider_key ?? null) ? str((string) $account->provider_key)->headline()->toString() : null,
            ])->filter()->implode(' - ');

            return [
                'value' => (string) $account->id,
                'label' => (string) $account->display_name,
                'description' => $subtitle !== '' ? $subtitle : __('Connected channel'),
                'avatarUrl' => filled($account->avatar_url ?? null) ? (string) $account->avatar_url : null,
                'icon' => filled($account->avatar_url ?? null) ? null : 'fa-light fa-user-circle',
            ];
        })
    )->values()->all();

    $typeOptions = [
        ['value' => 'image', 'label' => __('Image'), 'icon' => 'fa-light fa-image'],
        ['value' => 'text', 'label' => __('Text'), 'icon' => 'fa-light fa-font-case'],
    ];

    $textColorOptions = [
        ['value' => 'brand-gradient', 'label' => __('Soft white'), 'swatch' => '#ffffff'],
        ['value' => 'sunset-gradient', 'label' => __('Warm white'), 'swatch' => '#ffe7d6'],
        ['value' => 'ocean-gradient', 'label' => __('Cool white'), 'swatch' => '#e2f5ff'],
        ['value' => 'dark', 'label' => __('Dark'), 'swatch' => '#0f172a'],
        ['value' => 'white', 'label' => __('White'), 'swatch' => '#ffffff'],
    ];

    $positionChoices = [
        'top-left' => ['icon' => 'fa-light fa-arrow-up-left'],
        'top-right' => ['icon' => 'fa-light fa-arrow-up-right'],
        'center' => ['icon' => 'fa-light fa-circle'],
        'bottom-left' => ['icon' => 'fa-light fa-arrow-down-left'],
        'bottom-right' => ['icon' => 'fa-light fa-arrow-down-right'],
    ];
@endphp

<div
    class="watermark-legacy-layout min-w-0 max-w-full space-y-6 overflow-x-hidden px-4 pb-6 pt-4 sm:px-5 xl:px-6" data-portal-tools="watermark"
    x-data="{
        type: $wire.entangle('type'),
        position: $wire.entangle('position'),
        opacity: $wire.entangle('opacityPercent'),
        scale: $wire.entangle('scalePercent'),
        textValue: $wire.entangle('text'),
        textPreset: $wire.entangle('textPreset'),
        textColor: $wire.entangle('textColor'),
        textWeight: $wire.entangle('textWeight'),
        fileId: $wire.entangle('fileId'),
        filePreviewUrl: $wire.entangle('filePreviewUrl'),
        overlayStyle() {
            const alpha = Math.max(0.05, Math.min(1, Number(this.opacity || 0) / 100));
            const anchor = {
                'top-left': 'top: 0.5rem; left: 0.5rem;',
                'top-right': 'top: 0.5rem; right: 0.5rem;',
                'center': 'top: 50%; left: 50%; transform: translate(-50%, -50%);',
                'bottom-left': 'bottom: 0.5rem; left: 0.5rem;',
                'bottom-right': 'bottom: 0.5rem; right: 0.5rem;',
            }[this.position] || 'bottom: 0.5rem; right: 0.5rem;';

            return anchor + ' opacity: ' + alpha + ';';
        },
        overlayImageUrl() {
            return this.filePreviewUrl || '';
        },
        imageOverlayStyle() {
            const widthRem = Math.max(4, Number(this.scale || 0) * 0.22);

            return this.overlayStyle()
                + ' width: ' + widthRem + 'rem;'
                + ' max-width: none;'
                + ' max-height: none;';
        },
        hasImageOverlay() {
            return this.type === 'image' && this.overlayImageUrl() !== '';
        },
        hasTextOverlay() {
            return this.type === 'text' && String(this.textValue || '').trim() !== '';
        },
        textOverlayStyle() {
            const palette = {
                dark: '#0f172a',
                white: '#ffffff',
                'brand-gradient': '#ffffff',
                'sunset-gradient': '#ffe7d6',
                'ocean-gradient': '#e2f5ff',
            };
            const weights = { medium: '500', semibold: '600', bold: '700' };
            const presetStyles = {
                glass: 'border: 1px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); box-shadow: 0 12px 30px -18px rgba(15,23,42,0.38);',
                'solid-dark': 'border: 1px solid rgba(15,23,42,0.16); background: rgba(15,23,42,0.84); box-shadow: 0 12px 30px -18px rgba(15,23,42,0.42);',
                'solid-light': 'border: 1px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.9); box-shadow: 0 12px 30px -18px rgba(15,23,42,0.22);',
                minimal: 'border: none; background: transparent; box-shadow: none; padding-left: 0; padding-right: 0;',
            };
            const isGradient = false;
            const resolvedColor = presetStyles[this.textPreset] === presetStyles['solid-dark'] && this.textColor === 'dark'
                ? '#ffffff'
                : (isGradient ? '#ffffff' : (palette[this.textColor] || palette.dark));
            const gradientTextStyle = isGradient
                ? ' background-image: ' + palette[this.textColor] + '; -webkit-background-clip: text; background-clip: text; color: transparent; -webkit-text-fill-color: transparent;'
                : '';
            const fontSizePx = Math.max(14, Math.round(12 + (Number(this.scale || 0) * 0.42)));
            const paddingY = Math.max(0.45, Number(this.scale || 0) * 0.012);
            const paddingX = Math.max(0.8, Number(this.scale || 0) * 0.024);

            return this.overlayStyle()
                + ' color: ' + resolvedColor + ';'
                + ' font-weight: ' + (weights[this.textWeight] || '600') + ';'
                + ' text-transform: none;'
                + ' letter-spacing: 0.02em;'
                + ' font-size: ' + fontSizePx + 'px;'
                + ' line-height: 1.25;'
                + ' padding: ' + paddingY + 'rem ' + paddingX + 'rem;'
                + ' ' + (presetStyles[this.textPreset] || presetStyles.glass)
                + gradientTextStyle;
        },
    }"
    x-on:image-picker:change.window="if (($event.detail?.name || '') === 'file_id') { fileId = String($event.detail?.value || ''); filePreviewUrl = String($event.detail?.previewUrl || ''); }"
    x-on:select-menu:change.window="if (($event.detail?.name || '') === 'account_selector') { $wire.selectAccount(String($event.detail?.value || '')) }"
>
    {{-- portal-tools: preserve the original layout; presentation changes only. --}}
    <style>
        .watermark-legacy-layout [class*="shadow-["] { box-shadow: none !important; }
        .watermark-legacy-layout [class*="rounded-[1."] { border-radius: 12px !important; }
        .watermark-legacy-layout h1 { font-size: 28px; letter-spacing: -.035em; }
        .watermark-legacy-layout input[type="range"] { accent-color: var(--theme-accent); background: var(--theme-border-color); }
        .watermark-legacy-layout button { box-shadow: none !important; }
        .watermark-legacy-layout button:focus-visible { outline: 2px solid var(--theme-accent); outline-offset: 3px; }
        .watermark-hero { display:flex; align-items:center; justify-content:space-between; gap:32px; padding:26px 28px; border:1px solid var(--theme-border-color); border-radius:12px; background:var(--theme-surface-base); }
        .watermark-hero-intro { display:flex; align-items:center; gap:16px; min-width:0; }
        .watermark-hero-icon { display:grid; place-items:center; flex:0 0 46px; width:46px; height:46px; border-radius:12px; color:var(--theme-accent); background:rgba(var(--theme-accent-rgb),.08); font-size:20px; }
        .watermark-hero h1 { margin:0; font-size:26px; line-height:1.25; font-weight:650; color:var(--theme-header-text-color); }
        .watermark-hero-intro p { margin:7px 0 0; font-size:13px; line-height:1.65; color:var(--theme-muted-text-color); }
        .watermark-hero-summary { display:flex; flex-shrink:0; align-items:flex-start; gap:24px; margin:0; padding-left:28px; border-left:1px solid var(--theme-border-color); }
        .watermark-hero-summary dt { font-size:11px; line-height:1.5; color:var(--theme-muted-text-color); }
        .watermark-hero-summary dd { margin:6px 0 0; font-size:17px; font-weight:600; line-height:1.3; color:var(--theme-header-text-color); font-variant-numeric:tabular-nums; }
        .watermark-hero-position { display:block; margin-top:4px; font-size:11px; color:var(--theme-muted-text-color); text-transform:capitalize; }
        @media(max-width:1100px) { .watermark-hero { align-items:stretch; flex-direction:column; gap:20px; padding:22px; } .watermark-hero-summary { padding:18px 0 0; border-left:0; border-top:1px solid var(--theme-border-color); gap:32px; } }
        @media(max-width:480px) { .watermark-hero { padding:18px; } .watermark-hero-intro { align-items:flex-start; gap:12px; } .watermark-hero-icon { flex-basis:38px; width:38px; height:38px; font-size:17px; } .watermark-hero h1 { font-size:23px; } .watermark-hero-summary { justify-content:space-between; gap:12px; } }
    </style>
    <header class="watermark-hero">
        <div class="watermark-hero-intro">
            <span class="watermark-hero-icon" aria-hidden="true"><i class="fa-light fa-stamp"></i></span>
            <div>
                <h1>{{ __('Watermark') }}</h1>
                <p>{{ __('Add your signature to every image, across all accounts or a selected channel.') }}</p>
            </div>
        </div>
        <dl class="watermark-hero-summary" aria-label="{{ __('Current setup') }}">
            <div class="watermark-hero-type">
                <dt>{{ __('Watermark type') }}</dt>
                <dd x-text="type === 'image' ? @js(__('Image')) : @js(__('Text'))"></dd>
                <span class="watermark-hero-position" x-text="String(position || 'bottom-right').replaceAll('-', ' ')"></span>
            </div>
            <div>
                <dt>{{ __('Opacity') }}</dt>
                <dd x-text="opacity + '%'"></dd>
            </div>
            <div>
                <dt>{{ __('Size') }}</dt>
                <dd x-text="scale + '%'"></dd>
            </div>
        </dl>
    </header>

    

    <form wire:submit="save" class="min-w-0 max-w-full space-y-4">
        <section
            wire:key="watermark-editor-{{ $selectedAccountId !== '' ? $selectedAccountId : 'global' }}-{{ $editingId ?? 'new' }}"
            class="min-w-0 max-w-full overflow-hidden rounded-[1.55rem] border shadow-[0_26px_70px_-46px_rgba(15,23,42,0.2)]"
            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent);"
        >
            <div class="grid min-w-0 max-w-full gap-0 xl:grid-cols-[minmax(0,1fr)_23rem]">
                <div class="min-w-0 border-b p-4 sm:p-6 xl:border-b-0 xl:border-r" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="space-y-4">
                        <div class="min-w-0 max-w-full rounded-[1.25rem] border p-3 sm:p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
                            radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.08), transparent 26%),
                            linear-gradient(180deg, rgba(15,23,42,0.02), rgba(15,23,42,0.05));">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                                        <i class="fa-light fa-eye"></i>
                                    </span>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Live preview') }}</p>
                                        <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ __('Canvas updates as you adjust type, position, opacity and size.') }}</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background: rgba(var(--theme-surface-base-rgb), 0.72); color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-crosshairs"></i>
                                    <span x-text="String(position || 'bottom-right').replaceAll('-', ' ')"></span>
                                </span>
                            </div>
                            <div class="relative min-w-0 max-w-full aspect-[4/4] overflow-hidden rounded-[1rem] border bg-cover bg-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-image:
                                linear-gradient(135deg, rgba(15,23,42,0.26), rgba(15,23,42,0.06)),
                                radial-gradient(circle at 20% 20%, rgba(96,165,250,0.18), transparent 26%),
                                radial-gradient(circle at 80% 16%, rgba(56,189,248,0.14), transparent 24%),
                                linear-gradient(180deg, #dfe7f5 0%, #f8fbff 100%);">
                                <div class="absolute inset-0" style="background-image:
                                    linear-gradient(rgba(255,255,255,0.12) 1px, transparent 1px),
                                    linear-gradient(90deg, rgba(255,255,255,0.12) 1px, transparent 1px);
                                    background-size: 2.5rem 2.5rem;"></div>

                                <div class="absolute inset-0 flex items-end justify-between p-4 sm:p-6">
                                    <div class="max-w-[10rem] sm:max-w-[12rem]">
                                        <p class="text-[1.1rem] font-semibold tracking-[-0.04em] sm:text-[1.35rem]" style="color: rgba(15,23,42,0.78);">{{ __('Preview canvas') }}</p>
                                        <p class="mt-2 text-sm leading-6" style="color: rgba(15,23,42,0.56);">{{ __('This mockup shows approximate overlay placement, scale, and opacity before publishing.') }}</p>
                                    </div>
                                </div>

                                <template x-if="hasImageOverlay()">
                                    <img :src="overlayImageUrl()" alt="" class="absolute h-auto object-contain drop-shadow-[0_10px_20px_rgba(15,23,42,0.25)]" :style="imageOverlayStyle()">
                                </template>

                                <template x-if="hasTextOverlay()">
                                    <div class="absolute rounded-[0.9rem] whitespace-nowrap" :style="textOverlayStyle()" x-text="textValue"></div>
                                </template>

                                <template x-if="!hasImageOverlay() && !hasTextOverlay()">
                                    <div class="absolute bottom-5 right-5 rounded-[0.85rem] border border-dashed px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em]" style="border-color: rgba(15,23,42,0.18); color: rgba(15,23,42,0.46);">
                                        {{ __('No overlay selected') }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                    <aside class="min-w-0 flex flex-col justify-between p-4 sm:p-6">
                    <div class="min-w-0 space-y-6">
                        <section
                            wire:key="watermark-account-selector-{{ $selectedAccountId !== '' ? $selectedAccountId : 'global' }}"
                            class="rounded-[1.15rem] border p-4"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.62); background:
                                linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.04), transparent 34%),
                                color-mix(in srgb, var(--theme-surface-base) 98%, transparent);"
                        >
                            <x-ui.select-menu
                                name="account_selector"
                                :value="$selectedAccountId"
                                :options="$accountSelectorOptions"
                                :placeholder="__('Choose account')"
                            />
                        </section>

                        <x-ui.field :label="__('Watermark type')" :error="$errors->first('type')">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($typeOptions as $option)
                                    <button
                                        type="button"
                                        x-on:click="type = '{{ $option['value'] }}'"
                                        class="min-w-0 max-w-full flex items-center gap-3 rounded-[1rem] border px-4 py-3 text-left transition"
                                        :style="type === '{{ $option['value'] }}'
                                            ? 'border-color: rgba(var(--theme-accent-rgb), 0.32); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent); box-shadow: 0 10px 30px -22px rgba(var(--theme-accent-rgb), 0.55);'
                                            : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);'"
                                    >
                                        <span
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border text-sm transition"
                                            :style="type === '{{ $option['value'] }}'
                                                ? 'border-color: rgba(var(--theme-accent-rgb), 0.18); background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);'
                                                : 'border-color: rgba(var(--theme-border-color-rgb), 0.56); background-color: rgba(var(--theme-border-color-rgb), 0.04); color: var(--theme-muted-text-color);'"
                                        >
                                            <i class="{{ $option['icon'] }}"></i>
                                        </span>

                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold">{{ $option['label'] }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </x-ui.field>

                        <div x-show="type === 'image'" x-cloak class="min-w-0 max-w-full" wire:key="watermark-image-picker-{{ $selectedAccountId !== '' ? $selectedAccountId : 'global' }}-{{ $editingId ?? 'new' }}-{{ $fileId !== '' ? $fileId : 'empty' }}">
                            <x-ui.image-picker
                                name="file_id"
                                context="portal"
                                value-field="id"
                                :value="$fileId"
                                :preview="$filePreviewUrl"
                                :label="__('Watermark image')"
                                :error="$errors->first('fileId')"
                                :help="__('Browse your Files library and pick the image overlay for this account.')"
                                :button-label="__('Choose image file')"
                                :dialog-title="__('Choose watermark image')"
                                :dialog-description="__('Select an image from your Files library to use as the watermark overlay.')"
                            />
                        </div>

                        <div x-show="type === 'text'" x-cloak class="min-w-0 max-w-full">
                            <div class="min-w-0 max-w-full space-y-4 rounded-[1.15rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.62); background:
                                linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.04), transparent 34%),
                                color-mix(in srgb, var(--theme-surface-base) 98%, transparent);">
                                <div class="space-y-2">
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Watermark text') }}</p>
                                        <p class="text-xs leading-6" style="color: var(--theme-muted-text-color);">{{ __('Type the label you want rendered on top of the image preview.') }}</p>
                                    </div>

                                    <x-ui.textarea x-model="textValue" :error="$errors->first('text')" rows="4" :placeholder="__('Example: @brandname or Confidential')">{{ $text }}</x-ui.textarea>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-ui.select x-model="textPreset" :label="__('Style')">
                                        <option value="glass">{{ __('Glass') }}</option>
                                        <option value="solid-dark">{{ __('Solid dark') }}</option>
                                        <option value="solid-light">{{ __('Solid light') }}</option>
                                        <option value="minimal">{{ __('Minimal') }}</option>
                                    </x-ui.select>

                                    <x-ui.select x-model="textWeight" :label="__('Weight')">
                                        <option value="medium">{{ __('Medium') }}</option>
                                        <option value="semibold">{{ __('Semibold') }}</option>
                                        <option value="bold">{{ __('Bold') }}</option>
                                    </x-ui.select>

                                    <x-ui.field :label="__('Color')" class="sm:col-span-2">
                                        <div class="grid grid-cols-5 gap-2">
                                            @foreach ($textColorOptions as $option)
                                                <button
                                                    type="button"
                                                    x-on:click="textColor = '{{ $option['value'] }}'"
                                                    class="group flex flex-col items-center gap-2 rounded-[0.9rem] border px-2 py-2 transition"
                                                    :style="textColor === '{{ $option['value'] }}'
                                                        ? 'border-color: rgba(var(--theme-accent-rgb), 0.34); background-color: rgba(var(--theme-accent-rgb), 0.10); box-shadow: 0 10px 24px -20px rgba(var(--theme-accent-rgb), 0.55);'
                                                        : 'border-color: rgba(var(--theme-border-color-rgb), 0.62); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent);'"
                                                    title="{{ $option['label'] }}"
                                                >
                                                    <span class="inline-flex h-8 w-8 rounded-full border shadow-sm" style="border-color: rgba(255,255,255,0.7); background: {{ $option['swatch'] }};"></span>
                                                    <span class="text-[10px] font-semibold leading-none" style="color: var(--theme-muted-text-color);">{{ $option['label'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </x-ui.field>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Position') }}</p>
                            <div class="mt-3 grid w-fit max-w-full grid-cols-3 gap-2">
                                @foreach ([
                                    ['key' => 'top-left', 'enabled' => true],
                                    ['key' => '', 'enabled' => false],
                                    ['key' => 'top-right', 'enabled' => true],
                                    ['key' => '', 'enabled' => false],
                                    ['key' => 'center', 'enabled' => true],
                                    ['key' => '', 'enabled' => false],
                                    ['key' => 'bottom-left', 'enabled' => true],
                                    ['key' => '', 'enabled' => false],
                                    ['key' => 'bottom-right', 'enabled' => true],
                                ] as $cell)
                                    @if ($cell['enabled'])
                                        <button
                                            type="button"
                                            aria-label="{{ str($cell['key'])->headline()->toString() }}" x-on:click="position = '{{ $cell['key'] }}'"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-[0.75rem] border text-sm transition"
                                            :style="position === '{{ $cell['key'] }}'
                                                ? 'border-color: rgba(var(--theme-accent-rgb),0.4); background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);'
                                                : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent); color: var(--theme-muted-text-color);'"
                                        >
                                            <i class="{{ $positionChoices[$cell['key']]['icon'] }}"></i>
                                        </button>
                                    @else
                                        <span class="inline-flex h-10 w-10 rounded-[0.75rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.3); background-color: rgba(var(--theme-border-color-rgb), 0.04);"></span>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Size') }}</p>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);" x-text="scale"></span>
                            </div>
                            <x-ui.range x-model="scale" min="5" max="90" step="1" class="mt-4" aria-label="{{ __('Size') }}" />
                            <div class="mt-2 flex items-center justify-between text-[11px]" style="color: var(--theme-muted-text-color);">
                                <span>5</span>
                                <span>90</span>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Opacity') }}</p>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);" x-text="opacity"></span>
                            </div>
                            <x-ui.range x-model="opacity" min="5" max="100" step="1" class="mt-4" aria-label="{{ __('Opacity') }}" />
                            <div class="mt-2 flex items-center justify-between text-[11px]" style="color: var(--theme-muted-text-color);">
                                <span>5</span>
                                <span>100</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="min-w-0 max-w-full rounded-[1.55rem] border px-4 py-4 sm:px-6 shadow-[0_26px_70px_-46px_rgba(15,23,42,0.2)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent);">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    @if ($editingId)
                        <x-ui.dialog width="sm" dismissible :title="__('Delete this watermark?')" :description="__('This permanently removes the watermark for the current account target.')">
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger" size="lg">
                                    <i class="fa-light fa-trash-can"></i>
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </x-slot:trigger>

                            <x-slot:footer>
                                <div class="flex items-center justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                        {{ __('Cancel') }}
                                    </x-ui.button>
                                    <x-ui.button type="button" variant="danger" size="lg" x-on:click="open = false" :disabled="!$canManage" wire:click="deleteWatermark">
                                        {{ __('Delete') }}
                                    </x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    @endif

                    <x-ui.button type="button" variant="outline" size="lg" wire:click="resetFormState">
                        <i class="fa-light fa-rotate"></i>
                        {{ __('Reset') }}
                    </x-ui.button>
                </div>

                <x-ui.button :disabled="!$canManage" type="submit" size="lg">
                    {{ $editingId ? __('Save Changes') : __('Create Watermark') }}
                </x-ui.button>
            </div>
        </section>
    </form>
</div>
