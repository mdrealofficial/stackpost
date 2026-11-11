<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    @if ($errorMessage)
        <x-ui.alert :title="__('Error')" :description="$errorMessage" variant="danger" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Blogs')"
        :title="__('RSS Sources')"
        :description="__('Import external RSS feeds into blog posts, with optional AI rewriting before publishing.')"
        :count="$sources->count()"
        icon="fa-light fa-rss"
    >
        <x-slot:actions>
            <x-ui.modal
                width="xl"
                :title="$editingSourceId ? __('Edit RSS source') : __('Add RSS source')"
                :description="__('Configure the feed source, import cadence, category mapping, and AI options in one place.')"
                :initially-open="$errors->any() || $editingSourceId !== null"
                open-event="rss-source-form-open"
                close-event="rss-source-form-close"
                x-on:rss-source-form-saved.window="open = false"
            >
                <x-slot:trigger>
                    <x-ui.button type="button" variant="outline" wire:click="startCreate">
                        <i class="fa-light fa-plus"></i>
                        {{ __('Add RSS source') }}
                    </x-ui.button>
                </x-slot:trigger>

                <form id="rss-source-modal-form" wire:submit="save" class="grid gap-4 lg:grid-cols-2">
                    <x-ui.input wire:model="name" name="name" :label="__('Name')" :error="$errors->first('name')" required />
                    <x-ui.input wire:model="feed_url" name="feed_url" :label="__('Feed URL')" :error="$errors->first('feed_url')" required />

                    <x-ui.select wire:model="blog_category_id" name="blog_category_id" :label="__('Category')" :error="$errors->first('blog_category_id')">
                        <option value="">{{ __('No category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->nameForLocale() }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input wire:model="sync_interval_minutes" name="sync_interval_minutes" type="number" :label="__('Sync interval minutes')" :error="$errors->first('sync_interval_minutes')" min="5" max="10080" required />

                    <div class="lg:col-span-2">
                        <x-ui.async-tag-selector
                            wire-model="tag_ids"
                            :livewire-id="$this->getId()"
                            :label="__('Default tags')"
                            :description="__('Choose the tags that should be attached to imported posts from this feed.')"
                            :selected="$selectedTagOptions"
                            :search-endpoint="route('admin-blogs.tags.search')"
                            :create-endpoint="route('admin-blogs.tags.store')"
                            :placeholder="__('Search or pick tags...')"
                            :empty-label="__('No matching tags found.')"
                            :create-label="__('Create tag')"
                            :error="$errors->first('tag_ids') ?: $errors->first('tag_ids.*')"
                        />
                    </div>

                    <x-ui.input wire:model="max_items_per_run" name="max_items_per_run" type="number" :label="__('Max items per run')" :error="$errors->first('max_items_per_run')" min="1" max="50" required />

                    <div class="lg:col-span-2">
                        <div class="mb-3 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('AI prompt presets') }}</p>
                            <x-ui.dropdown-menu align="left" width="full" class="w-full">
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="outline" class="w-full justify-between">
                                        <span>{{ __('Choose AI prompt preset') }}</span>
                                        <i class="fa-light fa-chevron-down text-xs"></i>
                                    </x-ui.button>
                                </x-slot:trigger>

                                @foreach ($promptPresets as $index => $preset)
                                    <x-ui.dropdown-menu-item wire:click="applyPromptPreset({{ $index }})">
                                        {{ $preset }}
                                    </x-ui.dropdown-menu-item>
                                @endforeach
                            </x-ui.dropdown-menu>
                        </div>
                        <x-ui.textarea wire:model="ai_prompt" name="ai_prompt" :label="__('AI improve prompt')" :error="$errors->first('ai_prompt')" :help="__('Optional. Extra instruction used when AI rewrite is enabled.')" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:col-span-2">
                        <x-ui.checkbox wire:model="status" name="status" value="1" :label="__('Active')" />
                        <x-ui.checkbox wire:model="auto_publish" name="auto_publish" value="1" :label="__('Auto publish')" />
                        <x-ui.checkbox wire:model="ai_improve" name="ai_improve" value="1" :label="__('AI auto improve')" :description="__('Rewrite content so it differs from the original source.')" />
                        <x-ui.checkbox wire:model="ai_auto_translate" name="ai_auto_translate" value="1" :label="__('AI auto translate')" :description="__('Auto-fill missing locales after import. This increases translation usage, import time, and operating cost.')"/>
                    </div>

                    @if ($ai_auto_translate)
                        <div class="lg:col-span-2 rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(245, 158, 11, 0.24); color: #92400e; background-color: rgba(245, 158, 11, 0.08);">
                            <strong>{{ __('Cost warning:') }}</strong>
                            {{ __('Auto-translating every imported post can significantly increase translation/API usage and make each RSS run slower. Enable it only when you really need multilingual output.') }}
                        </div>
                    @endif

                </form>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3">
                        <x-ui.button type="button" variant="outline" wire:click="cancelEdit" x-on:click="open = false">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" form="rss-source-modal-form" wire:loading.attr="disabled">
                            {{ $editingSourceId ? __('Update source') : __('Save source') }}
                        </x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.modal>

            <x-ui.button type="button" wire:click="runAll" wire:loading.attr="disabled">
                {{ __('Run all now') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="space-y-5">
        <x-ui.bulk-toolbar compact>
            <x-slot:chips>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Sources') }}: {{ $sources->count() }}
                </span>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('Active') }}: {{ $sources->where('status', true)->count() }}
                </span>
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                    {{ __('AI translate') }}: {{ $sources->where('ai_auto_translate', true)->count() }}
                </span>
            </x-slot:chips>

            <x-slot:selection>
                <div class="flex min-w-0 items-center gap-3">
                    <x-ui.checkbox
                        wire:model.live="selectAllSources"
                        :label="__('Check all')"
                        minimal
                    />

                    @if (count($selectedSourceIds) > 0)
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">
                            {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selectedSourceIds), ['count' => count($selectedSourceIds)]) }}
                        </span>
                    @else
                        <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('No sources selected') }}</span>
                    @endif
                </div>

                @if (count($selectedSourceIds) > 0)
                    <x-ui.dropdown-menu align="right" width="auto">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="outline" size="sm">
                                <i class="fa-light fa-bolt"></i>
                                {{ __('Actions') }}
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </x-ui.button>
                        </x-slot:trigger>

                        <x-ui.dropdown-menu-item icon="fa-light fa-toggle-on" wire:click="activateSelected">
                            {{ __('Activate') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-toggle-off" wire:click="pauseSelected">
                            {{ __('Pause') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-play" wire:click="runSelected">
                            {{ __('Run selected') }}
                        </x-ui.dropdown-menu-item>
                    </x-ui.dropdown-menu>

                    <x-ui.dialog :title="__('Delete selected RSS sources?')" :description="__('This removes all selected sources and their import history. Imported blog posts will remain.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger" size="sm">
                                <i class="fa-light fa-trash-can"></i>
                            </x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="deleteSelected" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                @endif
            </x-slot:selection>
        </x-ui.bulk-toolbar>

        <div class="grid gap-5 xl:grid-cols-2">

        @forelse ($sources as $source)
            <x-ui.card class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <x-ui.checkbox
                            value="{{ $source->id }}"
                            wire:model.live="selectedSourceIds"
                            minimal
                            class="mt-1"
                        />
                        <div>
                            <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $source->name }}</p>
                            <p class="mt-1 break-all text-xs font-mono" style="color: var(--theme-muted-text-color);">{{ $source->feed_url }}</p>
                        </div>
                    </div>
                    <x-ui.badge :variant="$source->status ? 'success' : 'neutral'">{{ $source->status ? __('Active') : __('Disabled') }}</x-ui.badge>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color);">
                        <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Interval') }}</p>
                        <p class="mt-2 font-semibold" style="color: var(--theme-header-text-color);">{{ $source->sync_interval_minutes }} {{ __('min') }}</p>
                    </div>
                    <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color);">
                        <p class="text-xs uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Imported') }}</p>
                        <p class="mt-2 font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($source->imports_count) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($source->category)
                        <x-ui.badge>{{ $source->category->nameForLocale() }}</x-ui.badge>
                    @endif
                    @if ($source->auto_publish)
                        <x-ui.badge variant="success">{{ __('Auto publish') }}</x-ui.badge>
                    @endif
                    @if ($source->ai_improve)
                        <x-ui.badge variant="primary">{{ __('AI improve') }}</x-ui.badge>
                    @endif
                    @if ($source->ai_auto_translate)
                        <x-ui.badge variant="warning">{{ __('AI auto translate') }}</x-ui.badge>
                    @endif
                </div>

                <div class="text-sm" style="color: var(--theme-muted-text-color);">
                    {{ __('Last checked: :time', ['time' => $source->last_checked_at ? date('Y-m-d H:i', (int) $source->last_checked_at) : __('Never')]) }}
                </div>

                @if ($source->last_error)
                    <div class="rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(239, 68, 68, 0.24); color: #b91c1c; background-color: rgba(239, 68, 68, 0.06);">
                        {{ $source->last_error }}
                    </div>
                @endif

                <div class="flex items-center gap-2">
                    <x-ui.button type="button" variant="outline" wire:click="editSource({{ $source->id }})" wire:loading.attr="disabled">
                        {{ __('Edit') }}
                    </x-ui.button>
                    @if ($source->status)
                        <x-ui.button type="button" variant="outline" wire:click="pauseSource({{ $source->id }})" wire:loading.attr="disabled">
                            {{ __('Pause') }}
                        </x-ui.button>
                    @else
                        <x-ui.button type="button" variant="outline" wire:click="activateSource({{ $source->id }})" wire:loading.attr="disabled">
                            {{ __('Activate') }}
                        </x-ui.button>
                    @endif
                    <x-ui.button type="button" variant="outline" wire:click="runSource({{ $source->id }})" wire:loading.attr="disabled">
                        {{ __('Run now') }}
                    </x-ui.button>
                    <x-ui.dialog :title="__('Delete this RSS source?')" :description="__('This removes the source and its import history. Imported blog posts will remain.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger">{{ __('Delete') }}</x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="delete({{ $source->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card class="xl:col-span-2">
                <x-ui.empty
                    class="py-12"
                    icon="fa-light fa-rss"
                    :title="__('No RSS sources found.')"
                    :description="__('Add your first feed source to start importing blog content automatically.')"
                />
            </x-ui.card>
        @endforelse
        </div>

        <x-ui.card class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('RSS import logs') }}</p>
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Open the dedicated logs page to review imported items, inspect source links, and clear import history.') }}</p>
            </div>
            <x-ui.button :href="route('admin-blogs.rss.logs')" variant="outline" wire:navigate>
                <i class="fa-light fa-clock-rotate-left"></i>
                {{ __('View import logs') }}
            </x-ui.button>
        </x-ui.card>
    </div>
</div>
