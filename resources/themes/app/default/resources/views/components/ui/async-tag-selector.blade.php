@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'description' => null,
    'placeholder' => null,
    'emptyLabel' => null,
    'createLabel' => null,
    'searchEndpoint',
    'createEndpoint' => null,
    'selected' => [],
    'wireModel' => null,
    'livewireId' => null,
])

@php
    $normalizedSelected = collect($selected)
        ->map(fn ($option) => [
            'key' => trim((string) ($option['key'] ?? '')),
            'label' => trim((string) ($option['label'] ?? '')),
        ])
        ->filter(fn ($option) => $option['key'] !== '' && $option['label'] !== '')
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
            loading: false,
            creating: false,
            results: [],
            selectedOptions: @js($normalizedSelected),
            searchEndpoint: @js($searchEndpoint),
            createEndpoint: @js($createEndpoint),
            wireModel: @js($wireModel),
            livewireId: @js($livewireId),
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
            timer: null,
            normalizeLabel(value) {
                return String(value ?? '').trim().toLowerCase();
            },
            get selectedIds() {
                return this.selectedOptions.map((option) => option.key);
            },
            get createCandidate() {
                return String(this.query ?? '').trim();
            },
            get canCreate() {
                if (!this.createEndpoint || this.createCandidate === '') return false;
                return !this.selectedOptions.some((option) => this.normalizeLabel(option.label) === this.normalizeLabel(this.createCandidate))
                    && !this.results.some((option) => this.normalizeLabel(option.label) === this.normalizeLabel(this.createCandidate));
            },
            syncLivewire() {
                if (this.wireModel && this.$wire) {
                    this.$wire.set(this.wireModel, [...this.selectedIds], true);
                    return;
                }

                if (this.wireModel && this.livewireId && window.Livewire) {
                    const component = window.Livewire.find(this.livewireId);
                    if (component) {
                        component.set(this.wireModel, [...this.selectedIds], true);
                    }
                }
            },
            remove(key) {
                this.selectedOptions = this.selectedOptions.filter((option) => option.key !== key);
                this.syncLivewire();
                this.search();
            },
            addOption(option) {
                const normalized = {
                    key: String(option?.key ?? '').trim(),
                    label: String(option?.label ?? '').trim(),
                };

                if (!normalized.key || !normalized.label) {
                    return;
                }

                if (!this.selectedOptions.some((item) => item.key === normalized.key)) {
                    this.selectedOptions = [...this.selectedOptions, normalized];
                }

                this.query = '';
                this.results = [];
                this.syncLivewire();
            },
            scheduleSearch() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.search(), 220);
            },
            async search() {
                const q = this.createCandidate;

                if (!this.searchEndpoint) {
                    this.results = [];
                    return;
                }

                this.loading = true;

                try {
                    const url = new URL(this.searchEndpoint, window.location.origin);
                    if (q !== '') {
                        url.searchParams.set('q', q);
                    }

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    const payload = await response.json();
                    const options = Array.isArray(payload?.data) ? payload.data : [];

                    this.results = options
                        .map((option) => ({
                            key: String(option?.key ?? '').trim(),
                            label: String(option?.label ?? '').trim(),
                        }))
                        .filter((option) => option.key !== '' && option.label !== '')
                        .filter((option) => !this.selectedIds.includes(option.key));
                } catch (error) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            async createTag() {
                const name = this.createCandidate;
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

                    this.addOption(payload.data);
                } catch (error) {
                } finally {
                    this.creating = false;
                }
            },
        }"
        x-init="syncLivewire(); search()"
        class="space-y-3"
    >
        <div class="rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);">
            <div class="flex flex-wrap gap-2">
                <template x-for="option in selectedOptions" :key="'selected-'+option.key">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition"
                        style="border-color: rgba(var(--theme-accent-rgb), 0.18); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);"
                        x-on:mousedown.prevent
                        x-on:click.prevent.stop="remove(option.key)"
                    >
                        <span x-text="option.label"></span>
                        <i class="fa-light fa-xmark text-xs" style="color: var(--theme-muted-text-color);"></i>
                    </button>
                </template>

                <div class="min-w-[12rem] flex-1">
                    <input
                        type="text"
                        x-model="query"
                        x-on:input="scheduleSearch()"
                        x-on:keydown.enter.prevent="canCreate ? createTag() : (results[0] ? addOption(results[0]) : null)"
                        class="h-10 w-full border-0 bg-transparent px-1 text-sm font-medium outline-none placeholder:font-normal"
                        style="color: var(--theme-input-text);"
                        placeholder="{{ $placeholder ?: __('Search or pick tags...') }}"
                    >
                </div>
            </div>
        </div>

        <template x-if="canCreate">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-[0.9rem] border px-3 py-2 text-sm font-medium transition hover:-translate-y-0.5"
                style="border-color: rgba(var(--theme-accent-rgb), 0.18); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);"
                x-on:mousedown.prevent
                x-on:click.prevent.stop="createTag()"
                x-bind:disabled="creating"
            >
                <i class="fa-light fa-plus text-xs" style="color: var(--theme-accent);"></i>
                <span x-show="!creating">{{ $createLabel ?: __('Create tag') }}:</span>
                <span x-show="creating">{{ __('Creating...') }}</span>
                <span class="font-semibold" x-text="createCandidate"></span>
            </button>
        </template>

        <div class="max-h-52 overflow-y-auto rounded-[1rem] border p-2" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 60%, transparent);">
            <template x-if="loading">
                <div class="px-2 py-6 text-center text-sm" style="color: var(--theme-muted-text-color);">
                    {{ __('Searching tags...') }}
                </div>
            </template>

            <template x-if="!loading && results.length">
                <div class="flex flex-wrap gap-2">
                    <template x-for="option in results" :key="'result-'+option.key">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition hover:-translate-y-0.5"
                            style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);"
                            x-on:mousedown.prevent
                            x-on:click.prevent.stop="addOption(option)"
                        >
                            <i class="fa-light fa-plus text-xs" style="color: var(--theme-muted-text-color);"></i>
                            <span x-text="option.label"></span>
                        </button>
                    </template>
                </div>
            </template>

            <template x-if="!loading && !results.length">
                <div class="px-2 py-6 text-center text-sm" style="color: var(--theme-muted-text-color);">
                    {{ $emptyLabel ?: __('No matching tags found.') }}
                </div>
            </template>
        </div>
    </div>
</x-ui.field>
