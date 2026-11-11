<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Settings')"
        :title="__('Frequently Asked Questions')"
        :description="__('Manage common customer questions and answers shown on your public-facing pages.')"
        :count="$summary['total']"
        icon="fa-light fa-messages-question"
    >
        <x-slot:actions>
            <x-ui.modal
                width="lg"
                :close-on-backdrop="false"
                :title="$isEditing ? __('Edit FAQ') : __('Create FAQ')"
                :description="$isEditing ? __('Update the selected FAQ entry.') : __('Create a new FAQ entry for your public pages.')"
                x-on:faq-form-saved.window="open = false"
            >
                <x-slot:trigger>
                    <x-ui.button type="button" wire:click="startCreate">
                        <i class="fa-light fa-plus"></i>
                        {{ __('Create FAQ') }}
                    </x-ui.button>
                </x-slot:trigger>

                <form
                    id="faq-modal-form"
                    class="space-y-4"
                    x-data="{
                        defaultLocale: @js($defaultLocale),
                        titleTranslations: @js($titleTranslations),
                        contentTranslations: @js($contentTranslations),
                        slug: @js($slug),
                        status: @js($status),
                        autoTranslateMissing: @js($autoTranslateMissing),
                        slugEdited: @js($slug !== ''),
                        slugify(value) {
                            return String(value ?? '')
                                .normalize('NFD')
                                .replace(/[\u0300-\u036f]/g, '')
                                .toLowerCase()
                                .trim()
                                .replace(/[^a-z0-9]+/g, '-')
                                .replace(/^-+|-+$/g, '');
                        },
                        syncFromTitle() {
                            if (!this.slugEdited) {
                                this.slug = this.slugify(this.titleTranslations[this.defaultLocale] || '');
                            }
                        },
                        onSlugInput() {
                            this.slug = this.slugify(this.slug);
                            this.slugEdited = this.slug !== '';
                        },
                        slugNotice() {
                            const candidate = this.slugify(this.slug || this.titleTranslations[this.defaultLocale] || '');

                            if (candidate === '') {
                                return @js(__('Slug will be generated automatically from the title.'));
                            }

                            return @js(__('Slug will be saved as ":slug".', ['slug' => '__slug__'])).replace('__slug__', candidate);
                        },
                        hydrate(form = {}) {
                            this.titleTranslations = form.titleTranslations ?? {};
                            this.slug = form.slug ?? '';
                            this.contentTranslations = form.contentTranslations ?? {};
                            this.status = form.status ?? '1';
                            this.autoTranslateMissing = form.autoTranslateMissing ?? true;
                            this.slugEdited = this.slug !== '';
                        }
                    }"
                    x-on:submit.prevent="$wire.saveForm({ titleTranslations, slug, contentTranslations, status, autoTranslateMissing })"
                    x-on:faq-form-hydrated.window="hydrate($event.detail.form)"
                >
                    <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="inline-flex cursor-pointer items-start gap-3">
                                <input
                                    type="radio"
                                    name="faq-status-create"
                                    value="1"
                                    x-model="status"
                                    x-bind:checked="status === '1'"
                                    class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                                    style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                                >
                                <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Enable') }}</span>
                            </label>
                            <label class="inline-flex cursor-pointer items-start gap-3">
                                <input
                                    type="radio"
                                    name="faq-status-create"
                                    value="0"
                                    x-model="status"
                                    x-bind:checked="status === '0'"
                                    class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                                    style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                                >
                                <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Disable') }}</span>
                            </label>
                        </div>
                    </x-ui.field>

                    <div class="space-y-2.5">
                        <x-ui.input x-model="slug" x-on:input="onSlugInput()" :label="__('Slug')" :error="$errors->first('slug')" />
                        @if (! $errors->first('slug'))
                            <p class="text-sm" style="color: var(--theme-muted-text-color);" x-text="slugNotice()"></p>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Localized content') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Provide translated FAQ titles and content for each active language. The default locale is required.') }}</p>
                        </div>

                        <x-ui.checkbox
                            x-model="autoTranslateMissing"
                            name="faq-auto-translate-create"
                            value="1"
                            :checked="$autoTranslateMissing"
                            :label="__('Auto-fill missing translations')"
                            :description="__('When enabled, active languages with auto-translate turned on will be generated for any empty FAQ fields when you save.')"
                        />

                        @foreach ($languages as $language)
                            @php
                                $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                                $name = data_get($language, 'name', strtoupper($code));
                            @endphp

                            <x-ui.card class="space-y-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $name }}</p>
                                        <p class="text-sm uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ $code }}</p>
                                    </div>
                                    @if ($code === $defaultLocale)
                                        <x-ui.badge variant="primary">{{ __('Default') }}</x-ui.badge>
                                    @endif
                                </div>

                                <x-ui.input
                                    x-model="titleTranslations['{{ $code }}']"
                                    x-on:input="{{ $code === $defaultLocale ? 'syncFromTitle()' : '' }}"
                                    :label="__('Title')"
                                    :error="$errors->first('titleTranslations.'.$code)"
                                    :required="$code === $defaultLocale"
                                />

                                <x-ui.emoji-textarea
                                    x-model="contentTranslations['{{ $code }}']"
                                    :label="__('Content')"
                                    :error="$errors->first('contentTranslations.'.$code)"
                                    rows="8"
                                    picker-position="top"
                                    picker-align="right"
                                    trigger-position="inside-top-right"
                                ></x-ui.emoji-textarea>
                            </x-ui.card>
                        @endforeach
                    </div>
                </form>

                <x-slot:footer>
                    <x-ui.button type="button" variant="outline" x-on:click="open = false">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="button" x-on:click.prevent="document.getElementById('faq-modal-form')?.requestSubmit()" wire:loading.attr="disabled" wire:target="saveForm">
                        <i class="fa-light fa-floppy-disk" wire:loading.remove wire:target="saveForm"></i>
                        <i class="fa-light fa-spinner-third animate-spin" wire:loading wire:target="saveForm"></i>
                        <span wire:loading.remove wire:target="saveForm">{{ __('Save changes') }}</span>
                        <span wire:loading wire:target="saveForm">{{ __('Saving...') }}</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </x-slot:actions>
    </x-ui.page-hero>

    @php
        $enabledPercent = max(8, $summary['total'] > 0 ? round(($summary['enabled'] / $summary['total']) * 100) : 8);
        $disabledPercent = max(8, $summary['total'] > 0 ? round(($summary['disabled'] / $summary['total']) * 100) : 8);
        $faqStatCards = [
            [
                'label' => __('Total'),
                'value' => number_format($summary['total']),
                'suffix' => __('entries'),
                'description' => __('All FAQ entries currently stored.'),
                'icon' => 'fa-layer-group',
                'tone' => 'var(--theme-accent)',
                'soft' => 'rgba(var(--theme-accent-rgb), 0.08)',
                'strong' => 'rgba(var(--theme-accent-rgb), 0.92)',
                'progress' => 100,
            ],
            [
                'label' => __('Enabled'),
                'value' => number_format($summary['enabled']),
                'suffix' => __('live'),
                'description' => __('FAQs visible for public consumption.'),
                'icon' => 'fa-circle-check',
                'tone' => '#10b981',
                'soft' => 'rgba(16, 185, 129, 0.10)',
                'strong' => 'rgba(5, 150, 105, 0.92)',
                'progress' => $enabledPercent,
            ],
            [
                'label' => __('Disabled'),
                'value' => number_format($summary['disabled']),
                'suffix' => __('hidden'),
                'description' => __('Draft or hidden FAQ entries.'),
                'icon' => 'fa-eye-slash',
                'tone' => '#64748b',
                'soft' => 'rgba(100, 116, 139, 0.10)',
                'strong' => 'rgba(71, 85, 105, 0.92)',
                'progress' => $disabledPercent,
            ],
        ];
    @endphp

    <x-ui.metric-strip :items="$faqStatCards" />

    <div class="space-y-5">
        <x-ui.filter-panel fields-class="lg:grid-cols-[minmax(0,1.5fr)_180px_180px_180px_160px_auto]">
            <x-slot:fields>
                <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Search...')" />
                <x-ui.select wire:model.live="statusFilter" :label="__('Status')">
                    <option value="all">{{ __('All') }}</option>
                    <option value="1">{{ __('Enabled') }}</option>
                    <option value="0">{{ __('Disabled') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="dateFilter" :label="__('Range')">
                    @foreach ($filterOptions['dateRanges'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="sortFilter" :label="__('Sort')">
                    @foreach ($filterOptions['sorts'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model.live="perPage" :label="__('Show')">
                    @foreach ($filterOptions['perPage'] as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-slot:fields>

            <x-slot:actions>
                <x-ui.button type="button" wire:click="$refresh"><i class="fa-light fa-filter"></i></x-ui.button>
                <x-ui.button type="button" variant="outline" wire:click="resetFilters"><i class="fa-light fa-rotate-right"></i></x-ui.button>
            </x-slot:actions>

            <x-slot:chips>
                @if ($search !== '')
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
                @endif
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ __('Rows') }}: {{ $perPage }}</span>
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ __('Sort') }}: {{ collect($filterOptions['sorts'])->firstWhere('value', $sortFilter)['label'] ?? $sortFilter }}</span>
            </x-slot:chips>
        </x-ui.filter-panel>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($faqs as $faq)
            <x-ui.card class="space-y-4 transition-transform duration-200 hover:-translate-y-1" wire:key="faq-card-{{ $faq->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-base" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                            <i class="fa-light fa-circle-question"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $faq->titleForLocale() }}</p>
                            <p class="truncate text-xs" style="color: var(--theme-muted-text-color);">/{{ $faq->slug }}</p>
                        </div>
                    </div>
                    <x-ui.badge :variant="$faq->statusVariant()">{{ $faq->statusLabel() }}</x-ui.badge>
                </div>

                <p class="min-h-[4.5rem] rounded-[1rem] border px-4 py-3 text-sm leading-6" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 76%, transparent); color: var(--theme-muted-text-color);">
                    {{ $faq->contentPreview() }}
                </p>

                <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                    <p class="inline-flex items-center gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                        <i class="fa-light fa-clock-rotate-left"></i>
                        {{ $faq->createdAtFormatted() ?: __('N/A') }}
                    </p>
                    <div class="flex items-center gap-2">
                        <x-ui.modal
                            width="lg"
                            :close-on-backdrop="false"
                            :title="__('Edit FAQ')"
                            :description="__('Update the selected FAQ entry.')"
                            x-on:faq-form-saved.window="open = false"
                            wire:key="faq-edit-modal-{{ $faq->id }}"
                        >
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="startEdit({{ $faq->id }})">
                                    <i class="fa-light fa-pen-to-square"></i>
                                    {{ __('Edit') }}
                                </x-ui.button>
                            </x-slot:trigger>

                            <form
                                id="faq-edit-modal-form-{{ $faq->id }}"
                                class="space-y-4"
                                x-data="{
                                    defaultLocale: @js($defaultLocale),
                                    titleTranslations: @js($titleTranslations),
                                    contentTranslations: @js($contentTranslations),
                                    slug: @js($slug),
                                    status: @js($status),
                                    autoTranslateMissing: @js($autoTranslateMissing),
                                    slugEdited: @js($slug !== ''),
                                    slugify(value) {
                                        return String(value ?? '')
                                            .normalize('NFD')
                                            .replace(/[\u0300-\u036f]/g, '')
                                            .toLowerCase()
                                            .trim()
                                            .replace(/[^a-z0-9]+/g, '-')
                                            .replace(/^-+|-+$/g, '');
                                    },
                                    syncFromTitle() {
                                        if (!this.slugEdited) {
                                            this.slug = this.slugify(this.titleTranslations[this.defaultLocale] || '');
                                        }
                                    },
                                    onSlugInput() {
                                        this.slug = this.slugify(this.slug);
                                        this.slugEdited = this.slug !== '';
                                    },
                                    slugNotice() {
                                        const candidate = this.slugify(this.slug || this.titleTranslations[this.defaultLocale] || '');

                                        if (candidate === '') {
                                            return @js(__('Slug will be generated automatically from the title.'));
                                        }

                                        return @js(__('Slug will be saved as ":slug".', ['slug' => '__slug__'])).replace('__slug__', candidate);
                                    },
                                    hydrate(form = {}) {
                                        this.titleTranslations = form.titleTranslations ?? {};
                                        this.slug = form.slug ?? '';
                                        this.contentTranslations = form.contentTranslations ?? {};
                                        this.status = form.status ?? '1';
                                        this.autoTranslateMissing = form.autoTranslateMissing ?? true;
                                        this.slugEdited = this.slug !== '';
                                    }
                                }"
                                x-on:submit.prevent="$wire.saveForm({ titleTranslations, slug, contentTranslations, status, autoTranslateMissing })"
                                x-on:faq-form-hydrated.window="hydrate($event.detail.form)"
                            >
                                <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <label class="inline-flex cursor-pointer items-start gap-3">
                                            <input
                                                type="radio"
                                                name="faq-status-{{ $faq->id }}"
                                                value="1"
                                                x-model="status"
                                                x-bind:checked="status === '1'"
                                                class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                                                style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                                            >
                                            <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Enable') }}</span>
                                        </label>
                                        <label class="inline-flex cursor-pointer items-start gap-3">
                                            <input
                                                type="radio"
                                                name="faq-status-{{ $faq->id }}"
                                                value="0"
                                                x-model="status"
                                                x-bind:checked="status === '0'"
                                                class="mt-0.5 h-5 w-5 shrink-0 border shadow-[0_1px_2px_rgba(15,23,42,0.04)] focus:outline-none focus:ring-0 focus-visible:ring-4"
                                                style="accent-color: var(--theme-accent); border-color: color-mix(in srgb, var(--theme-border-color) 82%, rgba(var(--theme-accent-rgb), 0.18)); background-color: var(--theme-input-surface); color: var(--theme-accent); outline: none; --tw-ring-color: rgba(var(--theme-accent-rgb), 0.14);"
                                            >
                                            <span class="block text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Disable') }}</span>
                                        </label>
                                    </div>
                                </x-ui.field>

                                <div class="space-y-2.5">
                                    <x-ui.input x-model="slug" x-on:input="onSlugInput()" :label="__('Slug')" :error="$errors->first('slug')" />
                                    @if (! $errors->first('slug'))
                                        <p class="text-sm" style="color: var(--theme-muted-text-color);" x-text="slugNotice()"></p>
                                    @endif
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Localized content') }}</p>
                                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Provide translated FAQ titles and content for each active language. The default locale is required.') }}</p>
                                    </div>

                                    <x-ui.checkbox
                                        x-model="autoTranslateMissing"
                                        name="faq-auto-translate-edit-{{ $faq->id }}"
                                        value="1"
                                        :checked="$autoTranslateMissing"
                                        :label="__('Auto-fill missing translations')"
                                        :description="__('When enabled, active languages with auto-translate turned on will be generated for any empty FAQ fields when you save.')"
                                    />

                                    @foreach ($languages as $language)
                                        @php
                                            $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                                            $name = data_get($language, 'name', strtoupper($code));
                                        @endphp

                                        <x-ui.card class="space-y-4">
                                            <div class="flex items-center justify-between gap-4">
                                                <div>
                                                    <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $name }}</p>
                                                    <p class="text-sm uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ $code }}</p>
                                                </div>
                                                @if ($code === $defaultLocale)
                                                    <x-ui.badge variant="primary">{{ __('Default') }}</x-ui.badge>
                                                @endif
                                            </div>

                                            <x-ui.input
                                                x-model="titleTranslations['{{ $code }}']"
                                                x-on:input="{{ $code === $defaultLocale ? 'syncFromTitle()' : '' }}"
                                                :label="__('Title')"
                                                :error="$errors->first('titleTranslations.'.$code)"
                                                :required="$code === $defaultLocale"
                                            />

                                            <x-ui.emoji-textarea
                                                x-model="contentTranslations['{{ $code }}']"
                                                :label="__('Content')"
                                                :error="$errors->first('contentTranslations.'.$code)"
                                                rows="8"
                                                picker-position="top"
                                                picker-align="right"
                                                trigger-position="inside-top-right"
                                            ></x-ui.emoji-textarea>
                                        </x-ui.card>
                                    @endforeach
                                </div>
                            </form>

                            <x-slot:footer>
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                    {{ __('Cancel') }}
                                </x-ui.button>
                                <x-ui.button type="button" x-on:click.prevent="document.getElementById('faq-edit-modal-form-{{ $faq->id }}')?.requestSubmit()" wire:loading.attr="disabled" wire:target="saveForm">
                                    <i class="fa-light fa-floppy-disk" wire:loading.remove wire:target="saveForm"></i>
                                    <i class="fa-light fa-spinner-third animate-spin" wire:loading wire:target="saveForm"></i>
                                    <span wire:loading.remove wire:target="saveForm">{{ __('Save changes') }}</span>
                                    <span wire:loading wire:target="saveForm">{{ __('Saving...') }}</span>
                                </x-ui.button>
                            </x-slot:footer>
                        </x-ui.modal>

                        <x-ui.dialog :title="__('Delete FAQ?')" :description="__('This permanently removes the selected FAQ entry.')" width="sm" dismissible>
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger" size="sm">
                                    <i class="fa-light fa-trash-can"></i>
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </x-slot:trigger>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="button" variant="danger" wire:click="delete({{ $faq->id }})" x-on:click="open = false">
                                        {{ __('Delete') }}
                                    </x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card class="md:col-span-2 xl:col-span-3">
                <x-ui.empty
                    class="py-12"
                    icon="fa-light fa-messages-question"
                    :title="__('No FAQs found.')"
                    :description="__('Try broadening your filters or create your first FAQ entry.')"
                />
            </x-ui.card>
        @endforelse
    </div>

    @if ($faqs->hasPages())
        <div class="rounded-[0.85rem] border px-6 py-4" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
            {{ $faqs->links() }}
        </div>
    @endif
</div>
