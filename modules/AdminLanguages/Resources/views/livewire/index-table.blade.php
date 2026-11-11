@php
    $languageMetricCards = [
        [
            'label' => __('Total'),
            'value' => number_format($summary['total']),
            'description' => __('All locale records currently registered.'),
            'tone' => 'var(--theme-accent)',
            'progress' => 100,
        ],
        [
            'label' => __('Active'),
            'value' => number_format($summary['active']),
            'description' => __('Languages currently available to users.'),
            'tone' => 'var(--theme-success-color)',
            'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['active'] / $summary['total']) * 100) : 8),
        ],
        [
            'label' => __('Auto Translate'),
            'value' => number_format($summary['auto_translate']),
            'description' => __('Languages enabled for machine-assisted translation.'),
            'tone' => 'var(--theme-warning-color)',
            'progress' => max(8, $summary['total'] > 0 ? (int) round(($summary['auto_translate'] / $summary['total']) * 100) : 8),
        ],
    ];

    $statusLabels = [
        'all' => __('All'),
        'active' => __('Active'),
        'inactive' => __('Inactive'),
        'default' => __('Default'),
    ];

@endphp

<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    @if ($errorMessage)
        <x-ui.alert variant="danger" :title="__('Action failed')" :description="$errorMessage" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Translation workspace')"
        :title="__('Languages')"
        :description="__('Manage the locales available across guest pages, authentication screens, and the backend dashboard.')"
        :count="$summary['total']"
        icon="fa-light fa-language"
    >
        <x-slot:actions>
            <x-ui.button type="button" wire:click="openCreateModal">{{ __('Add new') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$languageMetricCards" :show-icons="false" />

    <x-ui.datatable-shell
        :title="__('Language directory')"
        :info="__('Locale setup, text direction, activation state, and translation tooling from one table.')"
        header-class="py-4"
        eyebrow-class="text-[10px] tracking-[0.2em]"
        title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
        description-class="mt-1 leading-6"
    >
        <div class="space-y-4 border-b px-6 py-5" style="border-color: var(--theme-border-color);">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_160px_auto]">
                <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name, native name, code...')" />
                <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="perPage" :label="__('Per page')">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end gap-2">
                    <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
                </div>
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    @if ($search !== '')
                        <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
                    @endif
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                        {{ __('Status') }}: {{ $statusLabels[$statusFilter] ?? __('All') }}
                    </span>
                </div>

                <div class="flex min-w-0 items-center gap-3">
                    @if (count($selected) > 0)
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                            {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selected), ['count' => count($selected)]) }}
                        </span>
                    @else
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                            {{ trans_choice('{1} :count language|[2,*] :count languages', $summary['total'], ['count' => $summary['total']]) }}
                        </span>
                        <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Active') }}: {{ number_format($summary['active']) }}</span>
                    @endif

                    @if (count($selected) > 0)
                        <div class="flex items-center gap-2">
                            <x-ui.button type="button" variant="outline" size="sm" wire:click="bulkEnable">{{ __('Enable') }}</x-ui.button>
                            <x-ui.button type="button" variant="outline" size="sm" wire:click="bulkDisable">{{ __('Disable') }}</x-ui.button>
                            <x-ui.dialog width="sm" dismissible :title="__('Delete selected languages?')" :description="__('This permanently removes the selected language records.')">
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete selected') }}</x-ui.button>
                                </x-slot:trigger>
                                <x-slot:footer>
                                    <div class="flex justify-end gap-3">
                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                        <x-ui.button type="button" variant="danger" x-on:click="$wire.bulkDelete(); open = false">{{ __('Delete') }}</x-ui.button>
                                    </div>
                                </x-slot:footer>
                            </x-ui.dialog>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head class="w-12">
                    <button type="button" wire:click="toggleSelectAllCurrentPage">
                        <span @class([
                            'inline-flex h-5 w-5 items-center justify-center rounded-md border transition',
                            'border-[var(--theme-accent)] bg-[var(--theme-accent)] text-white' => $allSelectedOnPage || $someSelectedOnPage,
                            'border-slate-300 bg-white text-transparent dark:border-slate-700 dark:bg-slate-900' => ! $allSelectedOnPage && ! $someSelectedOnPage,
                        ])>
                            @if ($allSelectedOnPage)
                                <i class="fa-light fa-check text-[11px] leading-none"></i>
                            @elseif ($someSelectedOnPage)
                                <i class="fa-light fa-minus text-[11px] leading-none"></i>
                            @endif
                        </span>
                    </button>
                </x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Language') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Direction') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Default') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Auto Translate') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Status') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>

            <x-ui.table-body>
                @forelse ($languages as $language)
                    <x-ui.table-row wire:key="language-row-{{ $language->id }}">
                        <x-ui.table-cell>
                            <x-ui.checkbox value="{{ $language->id }}" wire:model.live="selected" minimal />
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border text-xs font-semibold uppercase" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: var(--theme-surface-subtle);">
                                        {{ $language->icon ?: $language->code }}
                                    </div>
                                    <div>
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $language->name }}</p>
                                        <p class="text-xs uppercase" style="color: var(--theme-muted-text-color);">{{ $language->code }}</p>
                                    </div>
                                </div>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="strtoupper($language->direction) === 'RTL' ? 'primary' : 'neutral'">{{ strtoupper($language->direction) }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$language->is_default ? 'success' : 'neutral'">{{ $language->is_default ? __('Default') : __('No') }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$language->auto_translate ? 'primary' : 'neutral'">{{ $language->auto_translate ? __('Yes') : __('No') }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge :variant="$language->is_active ? 'success' : 'neutral'">{{ $language->is_active ? __('Enabled') : __('Disabled') }}</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="openEditModal({{ $language->id }})">{{ __('Edit') }}</x-ui.button>
                                <x-ui.button :href="route('admin-languages.translations', $language)" variant="outline" size="sm" wire:navigate>{{ __('Translations') }}</x-ui.button>
                                <x-ui.dropdown-menu>
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="outline" size="sm">{{ __('More') }}</x-ui.button>
                                    </x-slot:trigger>

                                    <x-ui.dropdown-menu-item type="button" icon="fa-light fa-file-import" wire:click="openImportModal({{ $language->id }})">
                                        {{ __('Import') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item type="button" icon="fa-light fa-file-export" wire:click="exportLanguage({{ $language->id }})">
                                        {{ __('Export') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item type="button" icon="fa-light fa-rotate" wire:click="updateMissing({{ $language->id }})">
                                        {{ __('Update languages') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item type="button" icon="fa-light fa-wand-magic-sparkles" wire:click="autoTranslate({{ $language->id }})">
                                        {{ __('Auto translate') }}
                                    </x-ui.dropdown-menu-item>
                                    @if (! $language->is_default)
                                        <x-ui.dropdown-menu-item type="button" icon="fa-light fa-star" wire:click="setDefault({{ $language->id }})">
                                            {{ __('Set default') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    <x-ui.dropdown-menu-item type="button" icon="fa-light fa-power-off" wire:click="toggleActive({{ $language->id }})">
                                        {{ $language->is_active ? __('Disable') : __('Enable') }}
                                    </x-ui.dropdown-menu-item>
                                    @if (! $language->is_default)
                                        <x-ui.dropdown-menu-divider />
                                        <x-ui.dialog width="sm" dismissible :title="__('Delete this language?')" :description="__('This permanently removes the selected language from the locale registry.')">
                                            <x-slot:trigger>
                                                <button type="button" class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30">
                                                    <span>{{ __('Delete') }}</span>
                                                    <i class="fa-light fa-trash-can text-[12px]"></i>
                                                </button>
                                            </x-slot:trigger>
                                            <x-slot:footer>
                                                <div class="flex justify-end gap-3">
                                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                    <x-ui.button type="button" variant="danger" x-on:click="$wire.deleteLanguage({{ $language->id }}); open = false">{{ __('Delete') }}</x-ui.button>
                                                </div>
                                            </x-slot:footer>
                                        </x-ui.dialog>
                                    @endif
                                </x-ui.dropdown-menu>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="7" class="py-10">
                            <x-ui.empty icon="fa-light fa-language" :title="__('No languages found.')" :description="__('Try broadening your filters or add a new language to the registry.')">
                                <x-ui.button type="button" wire:click="openCreateModal">{{ __('Add new') }}</x-ui.button>
                            </x-ui.empty>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($languages->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">
                {{ $languages->links() }}
            </div>
        @endif
    </x-ui.datatable-shell>

    <x-ui.modal
        width="xl"
        open-event="language-modal-open"
        close-event="language-modal-close"
        :initially-open="$errors->hasAny(['name', 'selectedLanguage', 'native_name', 'code', 'icon', 'direction', 'is_active', 'auto_translate', 'is_default'])"
        :title="$isEditing ? __('Edit language') : __('Create language')"
        :description="$isEditing ? __('Update locale details, activation state, and translation defaults.') : __('Add a new locale with text direction, activation state, and translation defaults.')"
        body-class="space-y-6 overflow-visible max-h-none px-6 py-6"
    >
        <form id="language-modal-form" wire:submit="saveLanguage" class="space-y-6">
            <section class="space-y-5">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Language details') }}</h3>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Follow the classic setup flow: choose a language first, then configure direction and translation behavior.') }}</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <x-ui.input wire:model="name" :label="__('Name')" :error="$errors->first('name')" />
                    <x-ui.language-select
                        wire:model.live="selectedLanguage"
                        :label="__('Language')"
                        :error="$errors->first('selectedLanguage') ?: $errors->first('code')"
                    />
                </div>

                @if ($selectedLanguage !== '')
                    @php
                        $selectedLanguageMeta = collect($languageCatalog)->firstWhere('code', $selectedLanguage);
                    @endphp
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-[1rem] border px-4 py-3.5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Locale code') }}</p>
                            <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ strtoupper($code) }}</p>
                        </div>
                        <div class="rounded-[1rem] border px-4 py-3.5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Native name') }}</p>
                            <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ data_get($selectedLanguageMeta, 'native_name', '...') }}</p>
                        </div>
                        <div class="rounded-[1rem] border px-4 py-3.5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Flag') }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="{{ language_flag_class((object) ['code' => $code, 'icon' => $icon]) }}"></span>
                                <span class="text-sm font-semibold uppercase" style="color: var(--theme-header-text-color);">{{ $icon ?: __('Auto') }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <section class="space-y-5 border-t pt-5" style="border-color: var(--theme-border-color);">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Behavior') }}</h3>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Control direction, availability, auto-translation, and whether this locale should become the default.') }}</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.select wire:model="direction" :label="__('Text direction')">
                        <option value="ltr">LTR</option>
                        <option value="rtl">RTL</option>
                    </x-ui.select>

                    <x-ui.select wire:model="is_active" :label="__('Status')">
                        <option value="1">{{ __('Enable') }}</option>
                        <option value="0">{{ __('Inactive') }}</option>
                    </x-ui.select>

                    <x-ui.select wire:model="auto_translate" :label="__('Auto translate')">
                        <option value="1">{{ __('Yes') }}</option>
                        <option value="0">{{ __('No') }}</option>
                    </x-ui.select>

                    <x-ui.select wire:model="is_default" :label="__('Default')">
                        <option value="0">{{ __('No') }}</option>
                        <option value="1">{{ __('Yes') }}</option>
                    </x-ui.select>
                </div>
            </section>
        </form>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3">
                <x-ui.button type="button" variant="outline" x-on:click="$dispatch('language-modal-close')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="language-modal-form">{{ $isEditing ? __('Save changes') : __('Create language') }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal
        width="md"
        open-event="language-import-modal-open"
        close-event="language-import-modal-close"
        :initially-open="$errors->has('catalogImportFile')"
        :title="__('Import language file')"
        :description="__('Import a JSON file into the selected language using the same structure produced by Export.')"
    >
        <form id="language-import-form" wire:submit="importCatalog" enctype="multipart/form-data" class="space-y-4">
            <div class="space-y-2">
                <label class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Translation file') }}</label>
                <input
                    type="file"
                    wire:model="catalogImportFile"
                    accept=".json,application/json"
                    class="block w-full rounded-[var(--theme-input-radius,0.75rem)] border px-4 py-3 text-sm"
                    style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                >
                @if ($errors->first('catalogImportFile'))
                    <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('catalogImportFile') }}</p>
                @else
                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Use a JSON file previously exported from this language.') }}</p>
                @endif
            </div>
        </form>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3">
                <x-ui.button type="button" variant="outline" x-on:click="$dispatch('language-import-modal-close')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="language-import-form">{{ __('Import') }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
