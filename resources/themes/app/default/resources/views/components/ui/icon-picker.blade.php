@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name' => null,
    'value' => null,
    'previewColor' => '#0f766e',
    'dialogTitle' => null,
    'dialogDescription' => null,
    'placeholder' => 'fa-light fa-hashtag',
    'icons' => [],
    'styleOptions' => [],
])

@php
    $defaultIcons = [
        'fa-light fa-hashtag',
        'fa-light fa-tag',
        'fa-light fa-tags',
        'fa-light fa-bookmark',
        'fa-light fa-thumbtack',
        'fa-light fa-thumbs-up',
        'fa-light fa-thumbs-down',
        'fa-light fa-flag',
        'fa-light fa-flag-pennant',
        'fa-light fa-certificate',
        'fa-light fa-award',
        'fa-light fa-link',
        'fa-light fa-paperclip',
        'fa-light fa-chain',
        'fa-light fa-anchor',
        'fa-light fa-folder',
        'fa-light fa-folder-open',
        'fa-light fa-folder-tree',
        'fa-light fa-folder-plus',
        'fa-light fa-file-lines',
        'fa-light fa-files',
        'fa-light fa-file-circle-plus',
        'fa-light fa-newspaper',
        'fa-light fa-book-open',
        'fa-light fa-book',
        'fa-light fa-book-atlas',
        'fa-light fa-bookmark-slash',
        'fa-light fa-feather',
        'fa-light fa-pen',
        'fa-light fa-pen-nib',
        'fa-light fa-pen-to-square',
        'fa-light fa-pencil',
        'fa-light fa-signature',
        'fa-light fa-quote-left',
        'fa-light fa-quote-right',
        'fa-light fa-paragraph',
        'fa-light fa-align-left',
        'fa-light fa-bolt',
        'fa-light fa-sparkles',
        'fa-light fa-wand-magic-sparkles',
        'fa-light fa-star',
        'fa-light fa-stars',
        'fa-light fa-badge-check',
        'fa-light fa-badge-percent',
        'fa-light fa-badge-dollar',
        'fa-light fa-fire',
        'fa-light fa-sun',
        'fa-light fa-moon',
        'fa-light fa-cloud',
        'fa-light fa-rainbow',
        'fa-light fa-snowflake',
        'fa-light fa-bolt-lightning',
        'fa-light fa-heart',
        'fa-light fa-heart-circle-check',
        'fa-light fa-face-smile',
        'fa-light fa-face-laugh',
        'fa-light fa-face-surprise',
        'fa-light fa-lightbulb',
        'fa-light fa-rocket',
        'fa-light fa-meteor',
        'fa-light fa-plane',
        'fa-light fa-paper-plane-top',
        'fa-light fa-bullhorn',
        'fa-light fa-megaphone',
        'fa-light fa-bell',
        'fa-light fa-bells',
        'fa-light fa-bell-ring',
        'fa-light fa-bell-on',
        'fa-light fa-globe',
        'fa-light fa-earth-asia',
        'fa-light fa-compass',
        'fa-light fa-location-dot',
        'fa-light fa-map',
        'fa-light fa-map-pin',
        'fa-light fa-signs-post',
        'fa-light fa-route',
        'fa-light fa-road',
        'fa-light fa-shapes',
        'fa-light fa-layer-group',
        'fa-light fa-grid-2',
        'fa-light fa-grid-2-plus',
        'fa-light fa-grid',
        'fa-light fa-sitemap',
        'fa-light fa-cubes',
        'fa-light fa-cube',
        'fa-light fa-objects-column',
        'fa-light fa-object-group',
        'fa-light fa-palette',
        'fa-light fa-brush',
        'fa-light fa-swatchbook',
        'fa-light fa-droplet',
        'fa-light fa-fill-drip',
        'fa-light fa-highlighter',
        'fa-light fa-pen-paintbrush',
        'fa-light fa-image',
        'fa-light fa-images',
        'fa-light fa-camera',
        'fa-light fa-camera-retro',
        'fa-light fa-clapperboard',
        'fa-light fa-film',
        'fa-light fa-photo-film',
        'fa-light fa-video',
        'fa-light fa-video-plus',
        'fa-light fa-photo-film-music',
        'fa-light fa-microphone',
        'fa-light fa-headphones',
        'fa-light fa-music-note',
        'fa-light fa-waveform-lines',
        'fa-light fa-podcast',
        'fa-light fa-radio',
        'fa-light fa-speaker',
        'fa-light fa-chart-column',
        'fa-light fa-chart-line',
        'fa-light fa-chart-pie',
        'fa-light fa-chart-network',
        'fa-light fa-chart-simple',
        'fa-light fa-gauge-high',
        'fa-light fa-signal-stream',
        'fa-light fa-arrow-trend-up',
        'fa-light fa-arrow-trend-down',
        'fa-light fa-chart-scatter',
        'fa-light fa-chart-mixed',
        'fa-light fa-trophy',
        'fa-light fa-medal',
        'fa-light fa-crown',
        'fa-light fa-gem',
        'fa-light fa-diamond',
        'fa-light fa-trophy-star',
        'fa-light fa-ranking-star',
        'fa-light fa-shield-check',
        'fa-light fa-circle-check',
        'fa-light fa-circle-info',
        'fa-light fa-circle-question',
        'fa-light fa-circle-exclamation',
        'fa-light fa-lock',
        'fa-light fa-key',
        'fa-light fa-fingerprint',
        'fa-light fa-shield-keyhole',
        'fa-light fa-user',
        'fa-light fa-users',
        'fa-light fa-user-pen',
        'fa-light fa-user-group',
        'fa-light fa-user-gear',
        'fa-light fa-address-card',
        'fa-light fa-id-badge',
        'fa-light fa-user-tie',
        'fa-light fa-user-doctor',
        'fa-light fa-user-graduate',
        'fa-light fa-briefcase',
        'fa-light fa-building',
        'fa-light fa-buildings',
        'fa-light fa-store',
        'fa-light fa-cart-shopping',
        'fa-light fa-bag-shopping',
        'fa-light fa-basket-shopping',
        'fa-light fa-shop',
        'fa-light fa-shop-lock',
        'fa-light fa-cash-register',
        'fa-light fa-credit-card',
        'fa-light fa-wallet',
        'fa-light fa-coins',
        'fa-light fa-gift',
        'fa-light fa-ticket',
        'fa-light fa-ticket-simple',
        'fa-light fa-leaf',
        'fa-light fa-seedling',
        'fa-light fa-tree',
        'fa-light fa-flower',
        'fa-light fa-sprout',
        'fa-light fa-mountain-sun',
        'fa-light fa-mug-hot',
        'fa-light fa-burger',
        'fa-light fa-pizza-slice',
        'fa-light fa-ice-cream',
        'fa-light fa-cookie',
        'fa-light fa-cake-candles',
        'fa-light fa-utensils',
        'fa-light fa-coffee-beans',
        'fa-light fa-bowl-food',
        'fa-light fa-salad',
        'fa-light fa-wine-glass',
        'fa-light fa-glass-water',
        'fa-light fa-laptop',
        'fa-light fa-desktop',
        'fa-light fa-mobile-screen',
        'fa-light fa-tablet-screen',
        'fa-light fa-keyboard',
        'fa-light fa-mouse',
        'fa-light fa-server',
        'fa-light fa-database',
        'fa-light fa-code',
        'fa-light fa-terminal',
        'fa-light fa-brackets-curly',
        'fa-light fa-window',
        'fa-light fa-microchip',
        'fa-light fa-robot',
        'fa-light fa-cpu',
        'fa-light fa-bug',
        'fa-light fa-shield-halved',
        'fa-light fa-plug',
        'fa-light fa-wifi',
        'fa-light fa-satellite-dish',
        'fa-light fa-cloud-arrow-up',
        'fa-light fa-cloud-arrow-down',
        'fa-light fa-envelope',
        'fa-light fa-envelopes',
        'fa-light fa-inbox',
        'fa-light fa-paper-plane',
        'fa-light fa-message',
        'fa-light fa-messages',
        'fa-light fa-comment',
        'fa-light fa-comments',
        'fa-light fa-comment-dots',
        'fa-light fa-comment-lines',
        'fa-light fa-phone',
        'fa-light fa-phone-volume',
        'fa-light fa-calendar',
        'fa-light fa-calendar-days',
        'fa-light fa-clock',
        'fa-light fa-hourglass-half',
        'fa-light fa-stopwatch',
        'fa-light fa-timer',
        'fa-light fa-alarm-clock',
        'fa-light fa-list-check',
        'fa-light fa-list-tree',
        'fa-light fa-check-double',
        'fa-light fa-bars-progress',
        'fa-light fa-sliders',
        'fa-light fa-gear',
        'fa-light fa-gears',
        'fa-light fa-wrench',
        'fa-light fa-screwdriver-wrench',
        'fa-light fa-toolbox',
        'fa-light fa-search',
        'fa-light fa-magnifying-glass',
        'fa-light fa-binoculars',
        'fa-light fa-eye',
        'fa-light fa-eye-slash',
        'fa-light fa-trash',
        'fa-light fa-trash-can',
        'fa-light fa-box-archive',
        'fa-light fa-download',
        'fa-light fa-upload',
        'fa-light fa-arrows-rotate',
        'fa-light fa-share-nodes',
        'fa-light fa-square-plus',
        'fa-light fa-circle-plus',
        'fa-light fa-plus',
        'fa-light fa-minus',
        'fa-light fa-xmark',
        'fa-light fa-arrow-up-right-from-square',
        'fa-light fa-up-right-and-down-left-from-center',
    ];

    $defaultStyleOptions = [
        ['value' => 'fa-light', 'label' => 'Classic Light'],
        ['value' => 'fa-regular', 'label' => 'Classic Regular'],
        ['value' => 'fa-solid', 'label' => 'Classic Solid'],
        ['value' => 'fa-thin', 'label' => 'Classic Thin'],
        ['value' => 'fa-duotone', 'label' => 'Duotone Solid'],
        ['value' => 'fa-duotone fa-light', 'label' => 'Duotone Light'],
        ['value' => 'fa-duotone fa-regular', 'label' => 'Duotone Regular'],
        ['value' => 'fa-duotone fa-thin', 'label' => 'Duotone Thin'],
        ['value' => 'fa-sharp fa-light', 'label' => 'Sharp Light'],
        ['value' => 'fa-sharp fa-regular', 'label' => 'Sharp Regular'],
        ['value' => 'fa-sharp fa-solid', 'label' => 'Sharp Solid'],
        ['value' => 'fa-sharp fa-thin', 'label' => 'Sharp Thin'],
        ['value' => 'fa-sharp-duotone fa-light', 'label' => 'Sharp Duo Light'],
        ['value' => 'fa-sharp-duotone fa-regular', 'label' => 'Sharp Duo Regular'],
        ['value' => 'fa-sharp-duotone fa-solid', 'label' => 'Sharp Duo Solid'],
        ['value' => 'fa-sharp-duotone fa-thin', 'label' => 'Sharp Duo Thin'],
        ['value' => 'fa-brands', 'label' => 'Brands'],
        ['value' => 'fa-chisel fa-regular', 'label' => 'Chisel Regular'],
        ['value' => 'fa-etch fa-solid', 'label' => 'Etch Solid'],
        ['value' => 'fa-graphite fa-thin', 'label' => 'Graphite Thin'],
        ['value' => 'fa-jelly fa-regular', 'label' => 'Jelly Regular'],
        ['value' => 'fa-jelly-fill fa-regular', 'label' => 'Jelly Fill'],
        ['value' => 'fa-jelly-duo fa-regular', 'label' => 'Jelly Duo'],
        ['value' => 'fa-notdog fa-solid', 'label' => 'Notdog Solid'],
        ['value' => 'fa-notdog-duo fa-solid', 'label' => 'Notdog Duo'],
        ['value' => 'fa-slab fa-regular', 'label' => 'Slab Regular'],
        ['value' => 'fa-slab-press fa-regular', 'label' => 'Slab Press'],
        ['value' => 'fa-thumbprint fa-light', 'label' => 'Thumbprint Light'],
        ['value' => 'fa-utility fa-semibold', 'label' => 'Utility Semi'],
        ['value' => 'fa-utility-fill fa-semibold', 'label' => 'Utility Fill'],
        ['value' => 'fa-utility-duo fa-semibold', 'label' => 'Utility Duo'],
        ['value' => 'fa-whiteboard fa-semibold', 'label' => 'Whiteboard Semi'],
    ];

    $normalizedIcons = collect(count($icons) ? $icons : $defaultIcons)
        ->map(fn ($icon) => trim((string) $icon))
        ->filter()
        ->unique()
        ->values();

    $normalizedStyleOptions = collect(count($styleOptions) ? $styleOptions : $defaultStyleOptions)
        ->map(function ($option) {
            if (is_string($option)) {
                return ['value' => $option, 'label' => str_replace('fa-', '', $option)];
            }

            return [
                'value' => (string) ($option['value'] ?? 'fa-light'),
                'label' => (string) ($option['label'] ?? str_replace('fa-', '', (string) ($option['value'] ?? 'fa-light'))),
            ];
        })
        ->filter(fn ($option) => $option['value'] !== '')
        ->unique('value')
        ->values();

    $specialFamilyIcons = [
        'fa-brands' => [
            'fa-light fa-github',
            'fa-light fa-facebook',
            'fa-light fa-instagram',
            'fa-light fa-linkedin',
            'fa-light fa-x-twitter',
            'fa-light fa-youtube',
            'fa-light fa-tiktok',
            'fa-light fa-discord',
            'fa-light fa-telegram',
            'fa-light fa-whatsapp',
            'fa-light fa-pinterest',
            'fa-light fa-dribbble',
        ],
        'fa-graphite fa-thin' => [
            'fa-light fa-hashtag',
            'fa-light fa-tag',
            'fa-light fa-bookmark',
            'fa-light fa-link',
            'fa-light fa-folder',
            'fa-light fa-file-lines',
            'fa-light fa-book',
            'fa-light fa-pen',
            'fa-light fa-star',
            'fa-light fa-heart',
            'fa-light fa-bell',
            'fa-light fa-globe',
            'fa-light fa-shapes',
            'fa-light fa-image',
            'fa-light fa-chart-column',
            'fa-light fa-user',
            'fa-light fa-briefcase',
            'fa-light fa-leaf',
            'fa-light fa-calendar',
            'fa-light fa-gear',
            'fa-light fa-search',
            'fa-light fa-trash',
            'fa-light fa-download',
            'fa-light fa-plus',
            'fa-light fa-minus',
            'fa-light fa-xmark',
        ],
        'fa-jelly fa-regular' => [
            'fa-light fa-face-smile',
            'fa-light fa-face-laugh',
            'fa-light fa-heart',
            'fa-light fa-star',
            'fa-light fa-sparkles',
            'fa-light fa-bell',
            'fa-light fa-gift',
            'fa-light fa-ice-cream',
            'fa-light fa-cookie',
            'fa-light fa-cake-candles',
            'fa-light fa-mug-hot',
            'fa-light fa-burger',
            'fa-light fa-pizza-slice',
            'fa-light fa-leaf',
            'fa-light fa-flower',
            'fa-light fa-rainbow',
            'fa-light fa-cloud',
            'fa-light fa-rainbow',
        ],
        'fa-jelly-fill fa-regular' => [
            'fa-light fa-heart',
            'fa-light fa-star',
            'fa-light fa-bell',
            'fa-light fa-gift',
            'fa-light fa-cookie',
            'fa-light fa-cake-candles',
            'fa-light fa-mug-hot',
            'fa-light fa-burger',
            'fa-light fa-pizza-slice',
            'fa-light fa-flower',
            'fa-light fa-cloud',
            'fa-light fa-face-smile',
            'fa-light fa-face-laugh',
        ],
        'fa-jelly-duo fa-regular' => [
            'fa-light fa-heart',
            'fa-light fa-star',
            'fa-light fa-bell',
            'fa-light fa-gift',
            'fa-light fa-cookie',
            'fa-light fa-cake-candles',
            'fa-light fa-mug-hot',
            'fa-light fa-burger',
            'fa-light fa-pizza-slice',
            'fa-light fa-flower',
            'fa-light fa-cloud',
            'fa-light fa-face-smile',
            'fa-light fa-face-laugh',
        ],
        'fa-chisel fa-regular' => [
            'fa-light fa-star',
            'fa-light fa-crown',
            'fa-light fa-gem',
            'fa-light fa-medal',
            'fa-light fa-trophy',
            'fa-light fa-shield-check',
            'fa-light fa-key',
            'fa-light fa-lock',
            'fa-light fa-book',
            'fa-light fa-feather',
            'fa-light fa-pen-nib',
            'fa-light fa-fire',
            'fa-light fa-shield-check',
            'fa-light fa-medal',
        ],
        'fa-etch fa-solid' => [
            'fa-light fa-hashtag',
            'fa-light fa-tag',
            'fa-light fa-bookmark',
            'fa-light fa-link',
            'fa-light fa-folder',
            'fa-light fa-file-lines',
            'fa-light fa-book',
            'fa-light fa-pen',
            'fa-light fa-star',
            'fa-light fa-heart',
            'fa-light fa-bell',
            'fa-light fa-globe',
            'fa-light fa-gear',
            'fa-light fa-shapes',
        ],
        'fa-notdog fa-solid' => [
            'fa-light fa-face-smile',
            'fa-light fa-heart',
            'fa-light fa-star',
            'fa-light fa-bell',
            'fa-light fa-gift',
            'fa-light fa-burger',
            'fa-light fa-pizza-slice',
            'fa-light fa-cookie',
            'fa-light fa-cake-candles',
            'fa-light fa-mug-hot',
        ],
        'fa-notdog-duo fa-solid' => [
            'fa-light fa-face-smile',
            'fa-light fa-heart',
            'fa-light fa-star',
            'fa-light fa-bell',
            'fa-light fa-gift',
            'fa-light fa-burger',
            'fa-light fa-pizza-slice',
            'fa-light fa-cookie',
            'fa-light fa-cake-candles',
            'fa-light fa-mug-hot',
        ],
        'fa-thumbprint fa-light' => [
            'fa-light fa-fingerprint',
            'fa-light fa-shield-keyhole',
            'fa-light fa-lock',
            'fa-light fa-key',
            'fa-light fa-user',
            'fa-light fa-user-group',
            'fa-light fa-circle-check',
            'fa-light fa-circle-info',
            'fa-light fa-circle-question',
            'fa-light fa-eye',
            'fa-light fa-eye-slash',
            'fa-light fa-badge-check',
            'fa-light fa-user',
            'fa-light fa-user-group',
        ],
        'fa-utility fa-semibold' => [
            'fa-light fa-gear',
            'fa-light fa-sliders',
            'fa-light fa-circle-check',
            'fa-light fa-circle-info',
            'fa-light fa-circle-exclamation',
            'fa-light fa-search',
            'fa-light fa-plus',
        ],
        'fa-utility-fill fa-semibold' => [
            'fa-light fa-gear',
            'fa-light fa-sliders',
            'fa-light fa-circle-check',
            'fa-light fa-circle-info',
            'fa-light fa-circle-exclamation',
            'fa-light fa-search',
            'fa-light fa-plus',
        ],
        'fa-utility-duo fa-semibold' => [
            'fa-light fa-gear',
            'fa-light fa-sliders',
            'fa-light fa-circle-check',
            'fa-light fa-circle-info',
            'fa-light fa-circle-exclamation',
            'fa-light fa-search',
            'fa-light fa-plus',
        ],
        'fa-whiteboard fa-semibold' => [
            'fa-light fa-lightbulb',
            'fa-light fa-bullhorn',
            'fa-light fa-megaphone',
            'fa-light fa-bookmark',
            'fa-light fa-thumbtack',
            'fa-light fa-pen',
            'fa-light fa-pen-to-square',
            'fa-light fa-pencil',
            'fa-light fa-shapes',
            'fa-light fa-grid-2',
            'fa-light fa-layer-group',
            'fa-light fa-star',
            'fa-light fa-note-sticky',
            'fa-light fa-thumbtack',
        ],
        'fa-slab fa-regular' => [
            'fa-light fa-book',
            'fa-light fa-book-open',
            'fa-light fa-book-atlas',
            'fa-light fa-newspaper',
            'fa-light fa-file-lines',
            'fa-light fa-folder',
            'fa-light fa-tag',
            'fa-light fa-bookmark',
            'fa-light fa-feather',
            'fa-light fa-pen-nib',
        ],
        'fa-slab-press fa-regular' => [
            'fa-light fa-book',
            'fa-light fa-book-open',
            'fa-light fa-book-atlas',
            'fa-light fa-newspaper',
            'fa-light fa-file-lines',
            'fa-light fa-folder',
            'fa-light fa-tag',
            'fa-light fa-bookmark',
            'fa-light fa-feather',
            'fa-light fa-pen-nib',
        ],
    ];

    $initialValue = old($name ?? '', $value ?: $placeholder);
@endphp

<x-ui.field :label="$label" :help="$help" :error="$error" {{ $attributes->only('class') }}>
    <div
        x-data="{
            search: '',
            icon: @js($initialValue),
            icons: @js($normalizedIcons->all()),
            styleOptions: @js($normalizedStyleOptions->all()),
            specialFamilyIcons: @js($specialFamilyIcons),
            iconStyle: 'fa-light',
            iconName: 'fa-hashtag',
            init() {
                this.parseIcon(this.icon || '{{ $placeholder }}');
                this.syncInput(false);
            },
            choose(value) {
                const nextName = this.shortLabel(value) || this.iconName || 'fa-hashtag';

                if (nextName === this.iconName) {
                    this.syncInput(true);
                    return;
                }

                this.iconName = nextName;
                this.syncInput(true);
            },
            parseIcon(value) {
                const raw = (value || '').trim();
                const parts = raw.split(/\s+/).filter(Boolean);
                const styleMatch = this.styleOptions
                    .slice()
                    .sort((a, b) => b.value.split(' ').length - a.value.split(' ').length)
                    .find((option) => {
                        const styleParts = option.value.split(' ');
                        return styleParts.every((stylePart) => parts.includes(stylePart));
                    });
                const styleParts = styleMatch ? styleMatch.value.split(' ') : [];
                const iconPart = parts.find((part) => part.startsWith('fa-') && !styleParts.includes(part));

                this.iconStyle = styleMatch ? styleMatch.value : (this.iconStyle || 'fa-light');
                this.iconName = iconPart || this.iconName || 'fa-hashtag';
                this.icon = `${this.iconStyle} ${this.iconName}`.trim();
            },
            syncInput(shouldDispatch = true) {
                const nextIcon = `${this.iconStyle} ${this.iconName}`.trim();

                this.icon = nextIcon;

                if (!this.$refs.input) {
                    return;
                }

                this.$refs.input.value = nextIcon;

                if (!shouldDispatch) {
                    return;
                }

                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            },
            setStyle(value) {
                if (this.iconStyle === value) {
                    return;
                }

                this.iconStyle = value;
                this.syncInput(true);
            },
            filteredIcons() {
                let source = this.specialFamilyIcons[this.iconStyle] || this.icons;
                if (this.search.trim() === '') return source;
                const keyword = this.search.trim().toLowerCase();
                return source.filter((icon) => {
                    const full = icon.toLowerCase();
                    const short = this.shortLabel(icon).toLowerCase();
                    return full.includes(keyword) || short.includes(keyword);
                });
            },
            shortLabel(value) {
                return value
                    .split(/\s+/)
                    .filter((part) => part.startsWith('fa-') && !this.styleOptions.some((option) => option.value.split(' ').includes(part)))
                    .join(' ');
            },
            iconClass(value) {
                return `${this.iconStyle} ${this.shortLabel(value)}`.trim();
            },
        }"
        class="space-y-3"
    >
        <input
            x-ref="input"
            type="hidden"
            @if ($name) name="{{ $name }}" @endif
            value="{{ $initialValue }}"
            {{ $attributes->except('class') }}
        >

        <div class="flex items-center gap-3">
            <div class="flex h-11 w-14 shrink-0 items-center justify-center rounded-[0.95rem] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: {{ $previewColor }};">
                <i x-bind:class="icon || '{{ $placeholder }}'" class="text-lg"></i>
            </div>
            <x-ui.input x-model="icon" x-on:input="parseIcon(icon); syncInput()" class="flex-1" />
        </div>

        <x-ui.modal
            :title="$dialogTitle ?: __('Choose an icon')"
            :description="$dialogDescription ?: __('Select a Font Awesome icon or search by keyword.')"
            width="xl"
            dismissible
        >
            <x-slot:trigger>
                <x-ui.button type="button" variant="outline" class="w-full justify-center">
                    <i class="fa-light fa-icons"></i>
                    {{ __('Browse Font Awesome icons') }}
                </x-ui.button>
            </x-slot:trigger>

            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <template x-for="option in styleOptions" :key="option.value">
                        <button
                            type="button"
                            x-on:click="setStyle(option.value)"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] transition"
                            :style="iconStyle === option.value
                                ? 'border-color: rgba(var(--theme-accent-rgb), 0.28); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);'
                                : 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-muted-text-color);'"
                            x-text="option.label"
                        ></button>
                    </template>
                </div>

                <x-ui.icon-input
                    x-model="search"
                    icon="fa-light fa-magnifying-glass"
                    :placeholder="__('Search icons...')"
                />

                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    <template x-for="presetIcon in filteredIcons()" :key="presetIcon">
                        <button
                            type="button"
                            x-on:click="choose(presetIcon); open = false"
                            class="group flex flex-col items-center justify-center gap-2 rounded-[1rem] border px-3 py-4 text-center transition hover:-translate-y-0.5"
                            :style="iconName === shortLabel(presetIcon)
                                ? 'border-color: rgba(var(--theme-accent-rgb), 0.28); background-color: rgba(var(--theme-accent-rgb), 0.08);'
                                : 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 97%, transparent);'"
                        >
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl" style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: {{ $previewColor }};">
                                <i :class="iconClass(presetIcon)" class="text-base"></i>
                            </span>
                            <span class="text-[11px] font-medium leading-4" style="color: var(--theme-muted-text-color);" x-text="shortLabel(presetIcon)"></span>
                        </button>
                    </template>
                </div>

                <p x-show="filteredIcons().length === 0" class="text-sm" style="color: var(--theme-muted-text-color);">
                    {{ __('No icons match your search.') }}
                </p>
            </div>
        </x-ui.modal>
    </div>
</x-ui.field>
