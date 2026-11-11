@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name' => null,
    'options' => [],
    'selected' => [],
    'description' => null,
    'placeholder' => null,
    'emptyLabel' => null,
    'wireModel' => null,
    'livewireId' => null,
    'multiple' => true,
    'maxSelections' => null,
    'live' => false,
    'syncOnClose' => false,
    'initiallyOpen' => false,
    'allowedProviders' => [],
    'networkOptions' => [],
    'showNetworkFilters' => true,
    'groupOptions' => [],
    'connectHref' => null,
    'connectLabel' => null,
    'inlinePanel' => false,
])

@php
    $normalizedOptions = collect($options)
        ->map(function ($option) {
            $providerKey = trim((string) ($option['providerKey'] ?? ($option['provider_key'] ?? '')));
            $providedToneSurface = trim((string) ($option['providerToneSurface'] ?? ($option['provider_tone_surface'] ?? '')));
            $providedToneText = trim((string) ($option['providerToneText'] ?? ($option['provider_tone_text'] ?? '')));
            $providerTone = publishing_provider_tone($providerKey);

            return [
                'key' => trim((string) ($option['key'] ?? '')),
                'label' => trim((string) ($option['label'] ?? '')),
                'subtitle' => trim((string) ($option['subtitle'] ?? ($option['meta'] ?? ''))),
                'avatarUrl' => trim((string) ($option['avatarUrl'] ?? ($option['avatar_url'] ?? ''))),
                'providerKey' => $providerKey,
                'providerLabel' => trim((string) ($option['providerLabel'] ?? ($option['provider_label'] ?? ''))),
                'providerIcon' => trim((string) ($option['providerIcon'] ?? ($option['provider_icon'] ?? ''))),
                'providerColor' => trim((string) ($option['providerColor'] ?? ($option['provider_color'] ?? ''))),
                'providerToneSurface' => $providedToneSurface !== '' ? $providedToneSurface : (string) $providerTone['surface'],
                'providerToneText' => $providedToneText !== '' ? $providedToneText : (string) $providerTone['text'],
                'isAddon' => (bool) ($option['isAddon'] ?? ($option['is_addon'] ?? false)),
            ];
        })
        ->filter(fn ($option) => $option['key'] !== '' && $option['label'] !== '')
        ->values()
        ->all();

    $normalizedAllowedProviders = collect(is_array($allowedProviders) ? $allowedProviders : [$allowedProviders])
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values()
        ->all();

    $normalizedNetworkOptions = collect($networkOptions)
        ->map(function ($option) {
            $providerKey = trim((string) ($option['key'] ?? ''));
            $providedToneSurface = trim((string) ($option['toneSurface'] ?? ($option['providerToneSurface'] ?? ($option['tone_surface'] ?? ''))));
            $providedToneText = trim((string) ($option['toneText'] ?? ($option['providerToneText'] ?? ($option['tone_text'] ?? ''))));
            $providerTone = publishing_provider_tone($providerKey);

            return [
                'key' => $providerKey,
                'label' => trim((string) ($option['label'] ?? '')),
                'icon' => trim((string) ($option['icon'] ?? '')),
                'color' => trim((string) ($option['color'] ?? '')),
                'toneSurface' => $providedToneSurface !== '' ? $providedToneSurface : (string) $providerTone['surface'],
                'toneText' => $providedToneText !== '' ? $providedToneText : (string) $providerTone['text'],
                'isAddon' => (bool) ($option['isAddon'] ?? ($option['is_addon'] ?? false)),
            ];
        })
        ->filter(fn ($option) => $option['key'] !== '' && $option['label'] !== '')
        ->values()
        ->all();

    $normalizedGroupOptions = collect($groupOptions)
        ->map(function ($option) {
            $optionKeys = collect((array) ($option['optionKeys'] ?? $option['option_keys'] ?? []))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all();

            return [
                'key' => trim((string) ($option['key'] ?? '')),
                'label' => trim((string) ($option['label'] ?? '')),
                'optionKeys' => $optionKeys,
                'count' => (int) ($option['count'] ?? count($optionKeys)),
            ];
        })
        ->filter(fn ($option) => $option['key'] !== '' && $option['label'] !== '')
        ->values()
        ->all();

    $normalizedSelected = collect(is_array($selected) ? $selected : [$selected])
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values()
        ->all();
@endphp

<x-ui.field :label="$label" :help="$help" :error="$error">
    @if ($description)
        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
    @endif

    <div
        data-no-loading
        wire:ignore
        x-data="{
            open: @js((bool) $initiallyOpen),
            query: '',
            options: @js($normalizedOptions),
            selected: @js($normalizedSelected),
            multiple: @js((bool) $multiple),
            maxSelections: @js($maxSelections !== null ? (int) $maxSelections : null),
            live: @js((bool) $live),
            syncOnClose: @js((bool) $syncOnClose),
            pendingSync: false,
            activeProvider: 'all',
            groupMenuOpen: false,
            allowedProviders: @js($normalizedAllowedProviders),
            networkOptions: @js($normalizedNetworkOptions),
            groupOptions: @js($normalizedGroupOptions),
            showNetworkFilters: @js((bool) $showNetworkFilters),
            wireModel: @js($wireModel),
            livewireId: @js($livewireId),
            togglePanel() {
                if (this.open) {
                    this.closePanel();
                    return;
                }

                this.open = true;
            },
            closePanel() {
                this.open = false;
                this.groupMenuOpen = false;

                if (this.pendingSync) {
                    this.syncLivewire();
                }
            },
            providerAllowed(option) {
                if (!this.allowedProviders.length) {
                    return true;
                }

                return this.allowedProviders.includes(String(option.providerKey || ''));
            },
            providerMatches(option) {
                if (!this.providerAllowed(option)) {
                    return false;
                }

                if (this.activeProvider === 'all') {
                    return true;
                }

                return String(option.providerKey || '') === this.activeProvider;
            },
            matches(option) {
                const q = this.query.trim().toLowerCase();
                if (q === '') return true;

                return [option.label, option.subtitle, option.providerLabel]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(q);
            },
            get filteredOptions() {
                return this.options.filter((option) => this.providerMatches(option) && this.matches(option));
            },
            validOptionKeys() {
                return this.options.map((option) => String(option.key || ''));
            },
            canonicalizeSelection(keys) {
                const validKeys = this.validOptionKeys();

                return this.normalizedKeys(keys).filter((key) => validKeys.includes(key));
            },
            get selectedOptions() {
                const selectedKeys = this.canonicalizeSelection(this.selected);

                return this.options.filter((option) => selectedKeys.includes(String(option.key || '')));
            },
            selectedKeys() {
                return this.selectedOptions.map((option) => String(option.key || ''));
            },
            selectedCount() {
                return this.selectedOptions.length;
            },
            get visibleProviders() {
                const registry = new Map();

                this.options.forEach((option) => {
                    const providerKey = String(option.providerKey || '');
                    if (!providerKey || !this.providerAllowed(option)) {
                        return;
                    }

                    const configured = this.networkOptions.find((item) => item.key === providerKey) || {};

                    if (!registry.has(providerKey)) {
                        registry.set(providerKey, {
                            key: providerKey,
                            label: configured.label || option.providerLabel || providerKey,
                            icon: configured.icon || option.providerIcon || '',
                            color: configured.color || option.providerColor || '',
                            toneSurface: configured.toneSurface || option.providerToneSurface || '',
                            toneText: configured.toneText || option.providerToneText || '',
                            isAddon: Boolean(configured.isAddon || option.isAddon),
                            count: 0,
                        });
                    }

                    registry.get(providerKey).count += 1;
                });

                return Array.from(registry.values());
            },
            get resolvedGroupOptions() {
                return this.groupOptions;
            },
            normalizedKeys(keys) {
                return [...new Set((Array.isArray(keys) ? keys : [])
                    .map((key) => String(key || '').trim())
                    .filter(Boolean))];
            },
            selectableOptionKeys() {
                return this.normalizedKeys(
                    this.options
                        .map((option) => option.key)
                        .filter((key) => this.canSelect(key) || this.isSelected(key))
                );
            },
            allOptionsSelected() {
                const keys = this.selectableOptionKeys();

                return keys.length > 0 && keys.every((key) => this.isSelected(key));
            },
            isSelected(key) {
                const resolvedKey = String(key || '');

                return this.selectedOptions.some((option) => String(option.key || '') === resolvedKey);
            },
            canSelect(key) {
                if (!this.multiple) {
                    return true;
                }

                if (this.isSelected(key)) {
                    return true;
                }

                if (this.maxSelections === null) {
                    return true;
                }

                return this.selected.length < this.maxSelections;
            },
            initials(label) {
                return String(label || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part.charAt(0))
                    .join('')
                    .toUpperCase();
            },
            toggle(key) {
                if (!this.canSelect(key)) {
                    return;
                }

                if (this.multiple) {
                    this.selected = this.isSelected(key)
                        ? this.selected.filter((item) => item !== key)
                        : this.canonicalizeSelection([...this.selected, key]);
                } else {
                    this.selected = this.isSelected(key) ? [] : this.canonicalizeSelection([key]);
                    this.open = false;
                }

                this.emitSelectionChanged(key);

                if (this.multiple && this.syncOnClose) {
                    this.pendingSync = true;
                    return;
                }

                this.syncLivewire();
            },
            toggleSelectAll() {
                if (!this.multiple) {
                    return;
                }

                const keys = this.selectableOptionKeys();
                if (!keys.length) {
                    return;
                }

                if (this.allOptionsSelected()) {
                    this.selected = [];
                } else {
                    this.selected = [...keys];
                }

                this.emitSelectionChanged(keys[keys.length - 1] || null);

                if (this.syncOnClose) {
                    this.pendingSync = true;
                    return;
                }

                this.syncLivewire();
            },
            clearAllSelections() {
                if (!this.multiple) {
                    this.selected = [];
                } else {
                    this.selected = [];
                }

                this.emitSelectionChanged(null);
                this.groupMenuOpen = false;

                if (this.syncOnClose) {
                    this.pendingSync = true;
                    return;
                }

                this.syncLivewire();
            },
            applyGroup(optionKeys) {
                if (!this.multiple) {
                    return;
                }

                const keys = Array.isArray(optionKeys)
                    ? this.normalizedKeys(optionKeys.filter((key) => this.canSelect(key) || this.isSelected(key)))
                    : [];

                if (!keys.length) {
                    return;
                }

                this.selected = keys;
                this.groupMenuOpen = false;
                this.emitSelectionChanged(keys[keys.length - 1] || null);

                if (this.syncOnClose) {
                    this.pendingSync = true;
                    return;
                }

                this.syncLivewire();
            },
            providerButtonStyle(provider) {
                const providerKey = String(provider?.key || '');
                const toneSurface = String(provider?.toneSurface || 'rgba(var(--theme-accent-rgb), 0.14)');
                const toneText = String(provider?.toneText || 'var(--theme-accent)');

                if (this.activeProvider === providerKey) {
                    return `border-color: transparent; background-color: ${toneSurface}; color: ${toneText};`;
                }

                return 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94); color: var(--theme-header-text-color);';
            },
            remove(key) {
                this.selected = this.canonicalizeSelection(this.selected.filter((item) => item !== key));
                this.emitSelectionChanged(key);

                if (this.multiple && this.syncOnClose && this.open) {
                    this.pendingSync = true;
                    return;
                }

                this.syncLivewire();
            },
            syncLivewire() {
                this.selected = this.canonicalizeSelection(this.selected);
                this.pendingSync = false;

                this.$nextTick(() => {
                    if (this.$refs.hiddenInput) {
                        this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                const resolvedSelectedKeys = this.selectedKeys();
                const payload = this.multiple ? [...resolvedSelectedKeys] : (resolvedSelectedKeys[0] ?? '');

                if (this.wireModel && this.$wire) {
                    this.$wire.set(this.wireModel, payload, this.live);
                    return;
                }

                if (this.wireModel && this.livewireId && window.Livewire) {
                    const component = window.Livewire.find(this.livewireId);
                    if (component) {
                        component.set(this.wireModel, payload, this.live);
                    }
                }
            },
            emitSelectionChanged(changedKey = null) {
                window.dispatchEvent(new CustomEvent('channel-selector:change', {
                    detail: {
                        model: this.wireModel,
                        changedKey,
                        selectedKeys: this.selectedKeys(),
                        selectedOptions: this.selectedOptions.map((option) => ({ ...option })),
                    },
                }));
            },
        }"
        x-init="selected = canonicalizeSelection(selected); syncLivewire()"
        x-on:click.outside="closePanel()"
        class="relative space-y-3"
    >
        <div class="overflow-visible rounded-[1rem] border shadow-[0_18px_42px_-30px_rgba(15,23,42,0.2)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="flex min-w-0 flex-1 flex-wrap gap-2 cursor-pointer" x-on:click="togglePanel()">
                    <template x-if="selectedOptions.length">
                        <template x-for="option in selectedOptions" :key="'chip-'+option.key">
                            <span class="inline-flex max-w-full items-center gap-2 rounded-[0.85rem] border px-3 py-2 text-sm font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96); color: var(--theme-header-text-color);">
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-[0.5rem] border text-[10px] font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.04); color: var(--theme-muted-text-color);">
                                    <template x-if="option.avatarUrl">
                                        <img x-bind:src="option.avatarUrl" x-bind:alt="option.label" class="h-full w-full object-cover">
                                    </template>
                                    <template x-if="!option.avatarUrl">
                                        <span x-text="initials(option.label)"></span>
                                    </template>
                                </span>
                                <span class="truncate" x-text="option.label"></span>
                                <button type="button" class="inline-flex h-5 w-5 items-center justify-center rounded-full transition hover:bg-slate-900/5" x-on:click.stop="remove(option.key)">
                                    <i class="fa-light fa-xmark text-xs" style="color: var(--theme-muted-text-color);"></i>
                                </button>
                            </span>
                        </template>
                    </template>

                    <template x-if="!selectedOptions.length">
                        <div class="flex min-h-[2.75rem] min-w-0 flex-1 items-center gap-3 rounded-[0.9rem] px-1 pr-2">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[0.85rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                <i class="fa-light fa-rectangle-history-circle-user text-sm"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $placeholder ?: __('Choose channel accounts') }}</span>
                                <span class="block truncate text-xs" style="color: var(--theme-muted-text-color);">{{ __('Pick one or more connected profiles for this workflow.') }}</span>
                            </span>
                        </div>
                    </template>
                </div>

                <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[0.8rem] border transition hover:bg-slate-900/[0.03]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click="togglePanel()">
                    <i class="fa-light fa-chevron-down transition" x-bind:class="open ? 'rotate-180' : ''"></i>
                </button>
            </div>

            <div x-show="open" x-transition.origin.top.left x-cloak class="{{ $inlinePanel ? 'relative mt-3' : 'absolute inset-x-0 top-[calc(100%+0.75rem)] z-40' }} overflow-visible rounded-[1rem] border shadow-[0_24px_60px_-34px_rgba(15,23,42,0.32)]" style="display: none; border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.99);">
                <div class="border-b px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex flex-nowrap items-center gap-3 rounded-[0.9rem] border px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                        <i class="fa-light fa-magnifying-glass text-sm" style="color: var(--theme-muted-text-color);"></i>
                        <input
                            type="text"
                            x-model="query"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm font-medium outline-none focus:ring-0"
                            style="color: var(--theme-header-text-color);"
                            placeholder="{{ __('Search') }}"
                        >
                        <div x-show="multiple" x-cloak class="ml-auto flex shrink-0 items-center gap-2 whitespace-nowrap">
                            <span class="shrink-0 rounded-full px-3 py-1.5 text-sm font-medium" style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);" x-text="`${selectedCount()} {{ __('selected') }}`"></span>

                            <button
                                type="button"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[0.6rem] border transition hover:bg-slate-900/[0.03]"
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color); background-color: var(--theme-surface-base);"
                                x-on:click.stop.prevent="toggleSelectAll()"
                                x-bind:title="allOptionsSelected() ? '{{ __('Unselect all') }}' : '{{ __('Select all') }}'"
                            >
                                <span
                                    class="inline-flex h-4.5 w-4.5 items-center justify-center rounded-[0.35rem] border transition"
                                    x-bind:style="allOptionsSelected() ? 'border-color: transparent; background-color: var(--theme-accent); color: white;' : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03); color: transparent;'"
                                >
                                    <i class="fa-light fa-check text-[9px]"></i>
                                </span>
                            </button>

                            <div class="relative" x-show="resolvedGroupOptions.length > 0" x-cloak>
                                <button
                                    type="button"
                                    class="inline-flex h-9 shrink-0 items-center gap-2 rounded-full border px-3 text-sm font-medium transition hover:border-[color:rgba(var(--theme-accent-rgb),0.38)] hover:text-[color:var(--theme-accent)]"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color); background-color: var(--theme-surface-base);"
                                    x-on:click.stop="groupMenuOpen = !groupMenuOpen"
                                >
                                    <i class="fa-light fa-users"></i>
                                    <span>{{ __('Group') }}</span>
                                </button>

                                <div
                                    x-show="groupMenuOpen"
                                    x-cloak
                                    x-transition.origin.top.right
                                    x-on:click.outside="groupMenuOpen = false"
                                    class="absolute right-0 top-[calc(100%+0.5rem)] z-20 w-48 overflow-hidden rounded-[1rem] border shadow-[0_24px_60px_-34px_rgba(15,23,42,0.32)]"
                                    style="display: none; border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.99);"
                                >
                                    <div class="px-2 py-2">
                                        <template x-for="group in resolvedGroupOptions" :key="'group-'+group.key">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm font-medium transition hover:bg-slate-900/[0.03] disabled:cursor-not-allowed disabled:opacity-45"
                                                style="color: var(--theme-header-text-color);"
                                                x-bind:disabled="!group.optionKeys.length"
                                                x-on:click.stop="if (group.optionKeys.length) applyGroup(group.optionKeys)"
                                            >
                                                <i class="fa-light fa-users text-xs" style="color: var(--theme-muted-text-color);"></i>
                                                <span class="min-w-0 flex-1 truncate" x-text="group.label"></span>
                                                <span class="shrink-0 text-[11px]" style="color: var(--theme-muted-text-color);" x-text="`${group.count || group.optionKeys.length} {{ __('accounts') }}`"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template x-if="showNetworkFilters && visibleProviders.length > 1">
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                x-on:click="activeProvider = 'all'"
                                x-bind:style="providerButtonStyle({ key: 'all', toneSurface: 'rgba(var(--theme-accent-rgb), 0.14)', toneText: 'var(--theme-accent)' })"
                            >
                                <span>{{ __('All networks') }}</span>
                            </button>

                            <template x-for="provider in visibleProviders" :key="'provider-'+provider.key">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    x-on:click="activeProvider = provider.key"
                                    x-bind:style="providerButtonStyle(provider)"
                                >
                                    <template x-if="provider.icon">
                                        <i x-bind:class="provider.icon"></i>
                                    </template>
                                    <span x-text="provider.label"></span>
                                    <template x-if="provider.isAddon"><span class="rounded-full border px-1.5 py-0.5 text-[8px] font-extrabold uppercase tracking-[0.06em]" style="border-color: color-mix(in srgb, var(--theme-accent) 38%, var(--theme-border-color)); background-color: color-mix(in srgb, var(--theme-accent) 8%, var(--theme-surface-base)); color: var(--theme-accent);">{{ __('Addon') }}</span></template>
                                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold" x-bind:style="activeProvider === provider.key ? 'background-color: rgba(255,255,255,0.22); color: white;' : 'background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-muted-text-color);'" x-text="provider.count"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="max-h-80 overflow-y-auto">
                    <template x-if="filteredOptions.length">
                        <div class="divide-y" style="divide-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <template x-for="option in filteredOptions" :key="'option-'+option.key">
                                <button
                                    type="button"
                                    class="grid w-full grid-cols-[3rem_minmax(0,1fr)_2rem] items-center gap-3 px-4 py-4 text-left transition hover:bg-slate-900/[0.02]"
                                    x-bind:disabled="!canSelect(option.key)"
                                    x-bind:class="!canSelect(option.key) ? 'opacity-50' : ''"
                                    x-on:click="toggle(option.key)"
                                >
                                    <span class="inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-[0.7rem] border text-xs font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.04); color: var(--theme-muted-text-color);">
                                        <template x-if="option.avatarUrl">
                                            <img x-bind:src="option.avatarUrl" x-bind:alt="option.label" class="h-full w-full object-cover">
                                        </template>
                                        <template x-if="!option.avatarUrl">
                                            <span x-text="initials(option.label)"></span>
                                        </template>
                                    </span>

                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5 truncate text-sm font-semibold" style="color: var(--theme-header-text-color);"><span class="truncate" x-text="option.label"></span><template x-if="option.isAddon"><span class="shrink-0 rounded-full border px-1.5 py-0.5 text-[8px] font-extrabold uppercase tracking-[0.06em]" style="border-color: color-mix(in srgb, var(--theme-accent) 38%, var(--theme-border-color)); background-color: color-mix(in srgb, var(--theme-accent) 8%, var(--theme-surface-base)); color: var(--theme-accent);">{{ __('Addon') }}</span></template></span>
                                        <span class="mt-1 block truncate text-sm" style="color: var(--theme-muted-text-color);" x-text="option.subtitle || option.providerLabel"></span>
                                    </span>

                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-[0.7rem] border transition" x-bind:style="isSelected(option.key) ? 'border-color: transparent; background-color: var(--theme-accent); color: white;' : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03); color: transparent;'">
                                        <i class="fa-light fa-check text-xs"></i>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>

                    <template x-if="!filteredOptions.length">
                        <div class="px-4 py-10 text-center text-sm" style="color: var(--theme-muted-text-color);">
                            {{ $emptyLabel ?: __('No matching channels found.') }}
                        </div>
                    </template>
                </div>

                @if ($connectHref)
                    <div class="border-t px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <x-ui.button :href="$connectHref" block>
                            <i class="fa-light fa-plus"></i>
                            {{ $connectLabel ?: __('Connect a channel') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        </div>

        @if ($name && $multiple)
            <template x-for="selectedKey in selectedKeys()" :key="'input-'+selectedKey">
                <input type="hidden" name="{{ $name }}[]" x-bind:value="selectedKey">
            </template>
        @endif

        @if ($name && ! $multiple)
            <input type="hidden" name="{{ $name }}" x-bind:value="selectedKeys()[0] ?? ''">
        @endif

        @if (! $multiple)
            <input
                x-ref="hiddenInput"
                type="hidden"
                x-bind:value="selectedKeys()[0] ?? ''"
                @if ($wireModel && ! $live) wire:model.defer="{{ $wireModel }}" @endif
            >
        @endif
    </div>
</x-ui.field>
