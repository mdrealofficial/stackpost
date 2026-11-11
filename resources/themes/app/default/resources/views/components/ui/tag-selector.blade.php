@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name',
    'options' => [],
    'selected' => [],
    'description' => null,
    'placeholder' => null,
    'emptyLabel' => null,
    'createEndpoint' => null,
    'createLabel' => null,
    'wireModel' => null,
    'livewireId' => null,
    'live' => false,
])

@php
    $normalizedOptions = collect($options)
        ->map(fn ($option) => [
            'key' => (string) ($option['key'] ?? ''),
            'label' => (string) ($option['label'] ?? ''),
        ])
        ->filter(fn ($option) => $option['key'] !== '' && $option['label'] !== '')
        ->values();

    $normalizedSelected = collect($selected)
        ->map(fn ($value) => (string) $value)
        ->filter()
        ->values()
        ->all();
@endphp

<x-ui.field :label="$label" :help="$help" :error="$error">
    @if ($description)
        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
    @endif

    <div
        x-data="{
            query: '',
            options: @js($normalizedOptions->all()),
            selected: @js($normalizedSelected),
            createEndpoint: @js($createEndpoint),
            wireModel: @js($wireModel),
            livewireId: @js($livewireId),
            creating: false,
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
            normalizeLabel(value) {
                return String(value ?? '').trim().toLowerCase();
            },
            findOptionByLabel(label) {
                const normalized = this.normalizeLabel(label);
                return this.options.find((option) => this.normalizeLabel(option.label) === normalized) || null;
            },
            parseQueryTokens() {
                return String(this.query ?? '')
                    .split(/[\n,;]+/g)
                    .map((item) => item.trim())
                    .filter(Boolean);
            },
            get createCandidate() {
                return this.parseQueryTokens()[0] || '';
            },
            get filteredOptions() {
                const q = this.query.trim().toLowerCase();
                return this.options.filter((option) => {
                    if (!option?.key || !this.normalizeLabel(option?.label)) return false;
                    if (this.selected.includes(option.key)) return false;
                    if (q === '') return true;
                    return option.label.toLowerCase().includes(q);
                });
            },
            get selectedOptions() {
                return this.options.filter((option) => this.selected.includes(option.key) && !!this.normalizeLabel(option?.label));
            },
            toggle(key) {
                if (this.selected.includes(key)) {
                    this.selected = this.selected.filter((item) => item !== key);
                    this.syncLivewire();
                    return;
                }

                this.selected = [...this.selected, key];
                this.query = '';
                this.syncLivewire();
            },
            remove(key) {
                this.selected = this.selected.filter((item) => item !== key);
                this.syncLivewire();
            },
            syncLivewire() {
                this.$nextTick(() => {
                    if (this.$refs.livewireSelect) {
                        this.$refs.livewireSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                if (this.wireModel && !@js((bool) $live)) {
                    return;
                }

                if (this.wireModel && this.$wire) {
                    this.$wire.set(this.wireModel, [...this.selected], @js((bool) $live));
                    return;
                }

                if (this.wireModel && this.livewireId && window.Livewire) {
                    const component = window.Livewire.find(this.livewireId);
                    if (component) {
                        component.set(this.wireModel, [...this.selected], @js((bool) $live));
                    }
                }
            },
            async commitQuery() {
                const tokens = this.parseQueryTokens();
                if (!tokens.length) return;

                for (const token of tokens) {
                    const existing = this.findOptionByLabel(token);
                    if (existing) {
                        if (!this.selected.includes(existing.key)) {
                            this.selected = [...this.selected, existing.key];
                        }
                        continue;
                    }

                    if (this.createEndpoint) {
                        await this.createTag(token);
                    }
                }

                this.query = '';
                this.syncLivewire();
            },
            async createTag() {
                const name = (arguments[0] ?? this.query).trim();
                if (!name || !this.createEndpoint || this.creating) return;

                this.creating = true;

                try {
                    const response = await fetch(this.createEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ name }),
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload?.data?.key) {
                        throw new Error(payload?.message || 'Failed to create tag.');
                    }

                    const normalizedOption = {
                        key: String(payload?.data?.key ?? '').trim(),
                        label: String(payload?.data?.label ?? '').trim(),
                    };

                    if (!normalizedOption.key || !normalizedOption.label) {
                        throw new Error('Invalid tag payload.');
                    }

                    if (!this.options.some((option) => option.key === normalizedOption.key)) {
                        this.options = [normalizedOption, ...this.options];
                    }

                    if (!this.selected.includes(normalizedOption.key)) {
                        this.selected = [...this.selected, normalizedOption.key];
                    }
                } catch (error) {
                } finally {
                    this.creating = false;
                    this.syncLivewire();
                }
            },
        }"
        x-init="syncLivewire()"
        class="space-y-3"
    >
        <select
            x-ref="livewireSelect"
            x-model="selected"
            multiple
            class="hidden"
            @if ($wireModel && $live)
                wire:model.live="{{ $wireModel }}"
            @elseif ($wireModel)
                wire:model.defer="{{ $wireModel }}"
            @endif
        >
            <template x-for="option in options" :key="'select-'+option.key">
                <option x-bind:value="option.key" x-text="option.label"></option>
            </template>
        </select>

        <div class="rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);">
            <div class="flex flex-wrap gap-2">
                <template x-for="option in selectedOptions" :key="'selected-'+option.key">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition"
                        style="border-color: rgba(var(--theme-accent-rgb), 0.18); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);"
                        x-on:click="remove(option.key)"
                    >
                        <span x-text="option.label"></span>
                        <i class="fa-light fa-xmark text-xs" style="color: var(--theme-muted-text-color);"></i>
                    </button>
                </template>

                <div class="min-w-[12rem] flex-1">
                    <input
                        type="text"
                        x-model="query"
                        x-on:keydown.enter.prevent="commitQuery()"
                        class="h-10 w-full border-0 bg-transparent px-1 text-sm font-medium outline-none placeholder:font-normal"
                        style="color: var(--theme-input-text);"
                        placeholder="{{ $placeholder ?: __('Search tags...') }}"
                    >
                </div>
            </div>
        </div>

        <template x-if="createEndpoint && createCandidate !== '' && !findOptionByLabel(createCandidate)">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-[0.9rem] border px-3 py-2 text-sm font-medium transition hover:-translate-y-0.5"
                style="border-color: rgba(var(--theme-accent-rgb), 0.18); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);"
                x-on:click="commitQuery()"
                x-bind:disabled="creating"
            >
                <i class="fa-light fa-plus text-xs" style="color: var(--theme-accent);"></i>
                <span x-show="!creating">{{ $createLabel ?: __('Create tag') }}:</span>
                <span x-show="creating">{{ __('Creating...') }}</span>
                <span class="font-semibold" x-text="createCandidate"></span>
            </button>
        </template>

        <div class="max-h-52 overflow-y-auto rounded-[1rem] border p-2" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 60%, transparent);">
            <template x-if="filteredOptions.length">
                <div class="flex flex-wrap gap-2">
                    <template x-for="option in filteredOptions" :key="option.key">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition hover:-translate-y-0.5"
                            style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);"
                            x-on:click="toggle(option.key)"
                        >
                            <i class="fa-light fa-plus text-xs" style="color: var(--theme-muted-text-color);"></i>
                            <span x-text="option.label"></span>
                        </button>
                    </template>
                </div>
            </template>

            <template x-if="!filteredOptions.length">
                <div class="px-2 py-6 text-center text-sm" style="color: var(--theme-muted-text-color);">
                    {{ $emptyLabel ?: __('No matching tags found.') }}
                </div>
            </template>
        </div>

        <template x-for="key in selected" :key="'input-'+key">
            <input type="hidden" name="{{ $name }}[]" x-bind:value="key">
        </template>
    </div>
</x-ui.field>
