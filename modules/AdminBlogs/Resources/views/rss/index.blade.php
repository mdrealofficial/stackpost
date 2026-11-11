@component(theme_view('layouts.app', 'app'), ['title' => __('Blog RSS')])
    <div class="space-y-6">
        

        <x-ui.page-hero
            :eyebrow="__('Blogs')"
            :title="__('RSS Sources')"
            :description="__('Import external RSS feeds into blog posts, with optional AI rewriting before publishing.')"
            :count="$sources->count()"
            icon="fa-light fa-rss"
        >
            <x-slot:actions>
                <form method="POST" action="{{ route('admin-blogs.rss.run-all') }}">
                    @csrf
                    <x-ui.button type="submit">{{ __('Run all now') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-hero>

        <x-ui.card class="space-y-5">
            <div>
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Add RSS source') }}</p>
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use `php artisan blogs:rss-import` in cron to run automatic imports based on each source interval.') }}</p>
            </div>

            <form method="POST" action="{{ route('admin-blogs.rss.store') }}" class="grid gap-4 lg:grid-cols-2">
                @csrf
                <x-ui.input name="name" :label="__('Name')" :value="old('name')" :error="$errors->first('name')" required />
                <x-ui.input name="feed_url" :label="__('Feed URL')" :value="old('feed_url')" :error="$errors->first('feed_url')" required />
                <x-ui.select name="blog_category_id" :label="__('Category')" :error="$errors->first('blog_category_id')">
                    <option value="">{{ __('No category') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('blog_category_id') === (string) $category->id)>{{ $category->nameForLocale() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="sync_interval_minutes" type="number" :label="__('Sync interval minutes')" :value="old('sync_interval_minutes', 60)" :error="$errors->first('sync_interval_minutes')" min="5" max="10080" required />

                <div class="lg:col-span-2">
                    <x-ui.tag-selector
                        name="tag_ids"
                        :label="__('Default tags')"
                        :description="__('Choose the tags that should be attached to imported posts from this feed.')"
                        :error="$errors->first('tag_ids') ?: $errors->first('tag_ids.*')"
                        :selected="old('tag_ids', [])"
                        :options="$tags->map(fn ($tag) => ['key' => (string) $tag->id, 'label' => $tag->nameForLocale()])->all()"
                        :placeholder="__('Search or pick tags...')"
                        :empty-label="__('No matching tags found.')"
                    />
                </div>

                <x-ui.input name="max_items_per_run" type="number" :label="__('Max items per run')" :value="old('max_items_per_run', 5)" :error="$errors->first('max_items_per_run')" min="1" max="50" required />
                <div class="lg:col-span-2">
                    <x-ui.textarea name="ai_prompt" :label="__('AI improve prompt')" :error="$errors->first('ai_prompt')" :help="__('Optional. Extra instruction used when AI rewrite is enabled.')">{{ old('ai_prompt') }}</x-ui.textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 lg:col-span-2">
                    <x-ui.checkbox name="status" value="1" :checked="old('status', '1') === '1'" :label="__('Active')" />
                    <x-ui.checkbox name="auto_publish" value="1" :checked="old('auto_publish', '1') === '1'" :label="__('Auto publish')" />
                    <x-ui.checkbox name="ai_improve" value="1" :checked="old('ai_improve') === '1'" :label="__('AI auto improve')" :description="__('Rewrite content so it differs from the original source.')"/>
                </div>
                <div class="lg:col-span-2">
                    <x-ui.button type="submit">{{ __('Save source') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <div class="grid gap-5 xl:grid-cols-2">
            @forelse ($sources as $source)
                <x-ui.card class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $source->name }}</p>
                            <p class="mt-1 break-all text-xs font-mono" style="color: var(--theme-muted-text-color);">{{ $source->feed_url }}</p>
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
                        <form method="POST" action="{{ route('admin-blogs.rss.run', $source) }}">
                            @csrf
                            <x-ui.button type="submit" variant="outline">{{ __('Run now') }}</x-ui.button>
                        </form>
                        <x-ui.confirm-action
                            :action="route('admin-blogs.rss.destroy', $source)"
                            :title="__('Delete this RSS source?')"
                            :description="__('This removes the source and its import history. Imported blog posts will remain.')"
                            :submit-label="__('Delete')"
                        >
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger">{{ __('Delete') }}</x-ui.button>
                            </x-slot:trigger>
                        </x-ui.confirm-action>
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
    </div>
@endcomponent
