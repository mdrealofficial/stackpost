@php
    $languages = function_exists('available_languages') ? available_languages() : collect();
    $defaultLocale = function_exists('locale_manager') ? locale_manager()->defaultCode() : (string) config('app.fallback_locale', 'en');
    $selectedCategory = old('blog_category_id', $blog->blog_category_id);
    $statusValue = (int) old('status', (int) ($blog->status ?? true));
    $thumbnailValue = old('thumbnail', $blog->thumbnail);
    $ogImageValue = old('og_image', $blog->og_image);
    $authorFolders = $authorFolders ?? collect();

    if ($languages->isEmpty()) {
        $languages = collect([(object) ['code' => $defaultLocale, 'name' => strtoupper($defaultLocale)]]);
    }

    $selectedTags = collect(old('tag_ids', $blog->exists ? $blog->tags->pluck('id')->all() : []))
        ->map(fn ($id) => (string) $id)
        ->all();
@endphp

<div
    x-data="{
        defaultTitle: @js(old('title_translations.'.$defaultLocale, data_get($blog->title_translations, $defaultLocale, $blog->title ?? ''))),
        slugValue: @js(old('slug', $blog->slug)),
        slugManual: @js(filled(old('slug', $blog->slug))),
        selectedCategory: @js((string) $selectedCategory),
        categoryName: '',
        categoryOptions: @js($categories->map(fn ($category) => ['key' => (string) $category->id, 'label' => $category->nameForLocale()])->values()->all()),
        categoryEndpoint: @js(route('admin-blogs.categories.store')),
        aiContentEndpoint: @js(route('admin-blogs.ai.content')),
        aiImageEndpoint: @js(route('admin-blogs.ai.image')),
        authorFolders: @js($authorFolders->map(fn ($folder) => ['key' => (string) $folder->id_secure, 'label' => $folder->name])->values()->all()),
        aiPrompt: '',
        aiImagePrompt: '',
        aiFolder: '',
        aiBusy: false,
        aiImageBusy: false,
        aiStatus: '',
        aiError: '',
        contentPromptPresets: [
            '{{ __('Write a practical how-to article with clear sections, examples, and a concise conclusion.') }}',
            '{{ __('Rewrite this topic as an SEO-friendly explainer with strong headings, concise paragraphs, and a helpful FAQ-style ending.') }}',
            '{{ __('Create an editorial-style blog post with a strong intro, balanced argument, and polished professional tone.') }}',
        ],
        imagePromptPresets: [
            '{{ __('Editorial blog cover image, clean composition, premium lighting, realistic detail, landscape format.') }}',
            '{{ __('Modern illustrative hero image for a blog article, bold focal point, soft gradient background, landscape format.') }}',
            '{{ __('Photorealistic editorial thumbnail, strong subject, balanced negative space for headlines, landscape format.') }}',
        ],
        categoryCreating: false,
        csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
        slugify(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
        syncSlugFromTitle(value) {
            this.defaultTitle = value;
            if (this.slugManual) return;
            this.slugValue = this.slugify(value);
        },
        syncSlugManualState(value) {
            this.slugValue = value;
            if (String(value || '').trim() === '') {
                this.slugManual = false;
                this.slugValue = this.slugify(this.defaultTitle);
                return;
            }
            this.slugManual = true;
        },
        async createCategory() {
            const name = this.categoryName.trim();
            if (!name || !this.categoryEndpoint || this.categoryCreating) return;
            this.categoryCreating = true;
            try {
                const response = await fetch(this.categoryEndpoint, {
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
                if (!response.ok || !payload?.data?.key) throw new Error(payload?.message || 'Failed');
                if (!this.categoryOptions.some((option) => option.key === payload.data.key)) {
                    this.categoryOptions = [...this.categoryOptions, payload.data];
                }
                this.selectedCategory = payload.data.key;
                this.categoryName = '';
            } finally {
                this.categoryCreating = false;
            }
        },
        setFieldValue(fieldName, value) {
            const field = Array.from(document.querySelectorAll('input, textarea, select')).find((element) => element.name === fieldName);
            if (!field) return;
            field.value = value ?? '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        },
        async generateAiContent() {
            const prompt = this.aiPrompt.trim();
            if (!prompt || this.aiBusy || !this.aiContentEndpoint) return;

            this.aiBusy = true;
            this.aiError = '';
            this.aiStatus = '';

            try {
                const response = await fetch(this.aiContentEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        prompt,
                        locale: @js($defaultLocale),
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload?.message || payload?.errors?.prompt?.[0] || 'AI content generation failed.');
                }

                const data = payload?.data || {};
                this.setFieldValue('title_translations[{{ $defaultLocale }}]', data.title || '');
                this.syncSlugFromTitle(data.title || '');
                this.setFieldValue('excerpt_translations[{{ $defaultLocale }}]', data.excerpt || '');
                window.dispatchEvent(new CustomEvent('html-editor:set', {
                    detail: {
                        name: 'content_translations[{{ $defaultLocale }}]',
                        content: data.content || '',
                    },
                }));
                this.aiStatus = '{{ __('AI content generated successfully.') }}';
            } catch (error) {
                this.aiError = error?.message || '{{ __('Could not generate AI content.') }}';
            } finally {
                this.aiBusy = false;
            }
        },
        async generateAiImage() {
            const prompt = (this.aiImagePrompt.trim() || this.aiPrompt.trim() || this.defaultTitle.trim());
            if (!prompt || this.aiImageBusy || !this.aiImageEndpoint) return;

            this.aiImageBusy = true;
            this.aiError = '';
            this.aiStatus = '';

            try {
                const response = await fetch(this.aiImageEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        prompt,
                        folder: this.aiFolder || null,
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload?.message || payload?.errors?.prompt?.[0] || 'AI image generation failed.');
                }

                window.dispatchEvent(new CustomEvent('image-picker:set', {
                    detail: {
                        name: 'thumbnail',
                        file: payload?.data || null,
                    },
                }));
                this.aiStatus = '{{ __('AI image generated and added to your file library.') }}';
            } catch (error) {
                this.aiError = error?.message || '{{ __('Could not generate AI image.') }}';
            } finally {
                this.aiImageBusy = false;
            }
        },
        applyAiPromptPreset(prompt) {
            this.aiPrompt = prompt;
        },
        applyAiImagePreset(prompt) {
            this.aiImagePrompt = prompt;
        },
    }"
    x-init="if (!slugManual) { slugValue = slugify(defaultTitle) }"
>
<x-ui.form-layout>
    <x-slot:main>
        <x-ui.settings-panel icon="fa-light fa-language">
            <x-ui.checkbox
                name="auto_translate_missing"
                value="1"
                :checked="old('auto_translate_missing', '1') === '1'"
                :label="__('Auto-fill missing translations')"
                :description="__('When enabled, active languages with auto-translate turned on will be generated for any empty post fields.')"
            />
        </x-ui.settings-panel>

        <div class="space-y-4">
            <div>
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Localized content') }}</p>
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Fill in translated titles, excerpts, and content for each active language. The default locale title and content are required.') }}</p>
            </div>

            @foreach ($languages as $language)
                @php
                    $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                    $name = data_get($language, 'name', strtoupper($code));
                    $titleValue = old("title_translations.$code", data_get($blog->title_translations, $code, $code === $defaultLocale ? $blog->title : ''));
                    $excerptValue = old("excerpt_translations.$code", data_get($blog->excerpt_translations, $code, $code === $defaultLocale ? $blog->excerpt : ''));
                    $contentValue = old("content_translations.$code", data_get($blog->content_translations, $code, $code === $defaultLocale ? $blog->content : ''));
                @endphp

                <x-ui.locale-card :title="$name" :locale="$code" :default="$code === $defaultLocale">
                    @if ($code === $defaultLocale)
                        <x-ui.input
                            :name="'title_translations['.$code.']'"
                            :label="__('Title')"
                            :value="$titleValue"
                            :error="$errors->first('title_translations.'.$code)"
                            required
                            x-model="defaultTitle"
                            x-on:input="syncSlugFromTitle($event.target.value)"
                        />
                    @else
                        <x-ui.input
                            :name="'title_translations['.$code.']'"
                            :label="__('Title')"
                            :value="$titleValue"
                            :error="$errors->first('title_translations.'.$code)"
                        />
                    @endif

                    <x-ui.emoji-textarea
                        :name="'excerpt_translations['.$code.']'"
                        :label="__('Excerpt')"
                        :error="$errors->first('excerpt_translations.'.$code)"
                        rows="4"
                        picker-position="top"
                        picker-align="right"
                        trigger-position="inside-top-right"
                    >{{ $excerptValue }}</x-ui.emoji-textarea>

                    <x-ui.html-editor
                        :name="'content_translations['.$code.']'"
                        :label="__('Content')"
                        :value="$contentValue"
                        :error="$errors->first('content_translations.'.$code)"
                        :required="$code === $defaultLocale"
                        rows="12"
                    />
                </x-ui.locale-card>
            @endforeach
        </div>
    </x-slot:main>

    <x-slot:sidebar>
        <x-ui.settings-panel
            hero
            icon="fa-light fa-sliders"
            :title="__('Settings')"
            :description="__('Secondary options for publishing, taxonomy, media, and slug generation.')"
        />

        <x-ui.settings-panel
            icon="fa-light fa-sparkles"
            :title="__('AI Studio')"
            :description="__('Generate a first draft with AI, then create a thumbnail image and save it straight into your file library.')"
            collapsible
            :open="false"
            class="space-y-4"
        >
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Content presets') }}</p>
                <x-ui.dropdown-menu width="full" class="w-full">
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="outline" size="sm">
                            <i class="fa-light fa-wand-magic-sparkles"></i>
                            {{ __('Choose content preset') }}
                            <i class="fa-light fa-chevron-down text-xs"></i>
                        </x-ui.button>
                    </x-slot:trigger>

                    <template x-for="preset in contentPromptPresets" :key="preset">
                        <x-ui.dropdown-menu-item x-on:click="applyAiPromptPreset(preset)">
                            <span class="whitespace-normal leading-6" x-text="preset"></span>
                        </x-ui.dropdown-menu-item>
                    </template>
                </x-ui.dropdown-menu>
            </div>

            <x-ui.textarea
                name="ai_prompt_helper"
                :label="__('Content brief')"
                :help="__('Describe the article topic, angle, audience, tone, and any key facts to include.')"
                rows="4"
                x-model="aiPrompt"
            />

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="outline" x-on:click="generateAiContent()" x-bind:disabled="aiBusy">
                    <i class="fa-light fa-pen-sparkle"></i>
                    <span x-show="!aiBusy">{{ __('Generate content') }}</span>
                    <span x-show="aiBusy">{{ __('Generating...') }}</span>
                </x-ui.button>
            </div>

            <x-ui.input
                name="ai_image_prompt_helper"
                :label="__('Thumbnail prompt')"
                :help="__('Optional. Leave empty to reuse the content brief or the default title.')"
                x-model="aiImagePrompt"
            />

            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Image presets') }}</p>
                <x-ui.dropdown-menu width="full" class="w-full">
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="outline" size="sm">
                            <i class="fa-light fa-image"></i>
                            {{ __('Choose image preset') }}
                            <i class="fa-light fa-chevron-down text-xs"></i>
                        </x-ui.button>
                    </x-slot:trigger>

                    <template x-for="preset in imagePromptPresets" :key="preset">
                        <x-ui.dropdown-menu-item x-on:click="applyAiImagePreset(preset)">
                            <span class="whitespace-normal leading-6" x-text="preset"></span>
                        </x-ui.dropdown-menu-item>
                    </template>
                </x-ui.dropdown-menu>
            </div>

            <x-ui.select x-model="aiFolder" :label="__('Save AI image to folder')">
                <option value="">{{ __('Root folder') }}</option>
                @foreach ($authorFolders as $folder)
                    <option value="{{ $folder->id_secure }}">{{ $folder->name }}</option>
                @endforeach
            </x-ui.select>

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="outline" x-on:click="generateAiImage()" x-bind:disabled="aiImageBusy">
                    <i class="fa-light fa-image"></i>
                    <span x-show="!aiImageBusy">{{ __('Generate image') }}</span>
                    <span x-show="aiImageBusy">{{ __('Generating...') }}</span>
                </x-ui.button>
            </div>

            <p class="text-sm" style="color: var(--theme-muted-text-color);">
                {{ __('You can also keep using the file picker below to choose any image from the author library, including folders you have already organized.') }}
            </p>

            <template x-if="aiStatus">
                <div class="rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(16, 185, 129, 0.24); color: #065f46; background-color: rgba(16, 185, 129, 0.08);" x-text="aiStatus"></div>
            </template>

            <template x-if="aiError">
                <div class="rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(239, 68, 68, 0.24); color: #b91c1c; background-color: rgba(239, 68, 68, 0.06);" x-text="aiError"></div>
            </template>
        </x-ui.settings-panel>

        <x-ui.settings-panel
            icon="fa-light fa-toggle-on"
            :title="__('Publish status')"
            :description="__('Choose whether this post is visible to readers or kept as a draft.')"
        >
            <x-ui.field :error="$errors->first('status')">
                <div x-data="{ statusChoice: @js((string) $statusValue) }" class="grid grid-cols-2 gap-3">
                    <div
                        class="rounded-[1rem] border px-4 py-3 transition"
                        x-bind:style="statusChoice === '1'
                            ? 'border-color: rgba(var(--theme-accent-rgb), 0.28); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);'
                            : 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);'"
                    >
                        <x-ui.radio
                            name="status"
                            value="1"
                            :checked="$statusValue === 1"
                            :label="__('Published')"
                            :description="__('Visible')"
                            x-model="statusChoice"
                            class="[&_label]:!flex [&_label]:!items-center [&_label]:!gap-3 [&_label]:!cursor-pointer [&_span]:!min-w-0"
                        />
                    </div>
                    <div
                        class="rounded-[1rem] border px-4 py-3 transition"
                        x-bind:style="statusChoice === '0'
                            ? 'border-color: rgba(148, 163, 184, 0.26); background-color: rgba(148, 163, 184, 0.08); color: var(--theme-header-text-color);'
                            : 'border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);'"
                    >
                        <x-ui.radio
                            name="status"
                            value="0"
                            :checked="$statusValue === 0"
                            :label="__('Draft')"
                            :description="__('Hidden')"
                            x-model="statusChoice"
                            class="[&_label]:!flex [&_label]:!items-center [&_label]:!gap-3 [&_label]:!cursor-pointer [&_span]:!min-w-0"
                        />
                    </div>
                </div>
            </x-ui.field>
        </x-ui.settings-panel>

        <x-ui.settings-panel
            icon="fa-light fa-diagram-project"
            :title="__('Taxonomy')"
            :description="__('Organize the post with categories and tags for discovery and navigation.')"
            class="space-y-4"
        >
            <x-ui.field :label="__('Category')" :error="$errors->first('blog_category_id')">
                <select name="blog_category_id" x-model="selectedCategory" class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200" style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);">
                    <option value="">{{ __('No category') }}</option>
                    <template x-for="option in categoryOptions" :key="option.key">
                        <option x-bind:value="option.key" x-text="option.label"></option>
                    </template>
                </select>

                <div class="rounded-[1rem] border p-3" style="border-color: color-mix(in srgb, var(--theme-border-color) 80%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 60%, transparent);">
                    <div class="flex gap-2">
                        <input x-model="categoryName" x-on:keydown.enter.prevent="createCategory()" type="text" class="flex h-10 w-full border px-3 text-sm outline-none" style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Create category...') }}">
                        <x-ui.button type="button" variant="outline" x-on:click="createCategory()" x-bind:disabled="categoryCreating">
                            <span x-show="!categoryCreating">{{ __('Add') }}</span>
                            <span x-show="categoryCreating">{{ __('Adding...') }}</span>
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.field>

            <x-ui.tag-selector
                name="tag_ids"
                :label="__('Tags')"
                :description="__('Assign one or more active tags to this post.')"
                :selected="$selectedTags"
                :options="$tags->map(fn ($tag) => ['key' => (string) $tag->id, 'label' => $tag->nameForLocale()])->all()"
                :placeholder="__('Search or pick tags...')"
                :create-endpoint="route('admin-blogs.tags.store')"
                :create-label="__('Create tag')"
            />
        </x-ui.settings-panel>

        <x-ui.settings-panel
            icon="fa-light fa-image"
            :title="__('Media & Slug')"
            :description="__('Set a thumbnail and the public URL slug for this post.')"
            class="space-y-4"
        >
            <x-ui.image-picker
                name="thumbnail"
                :value="$thumbnailValue"
                :label="__('Thumbnail')"
                :error="$errors->first('thumbnail')"
                :help="__('Choose an image from your file library. The selected file URL will be saved for this post.')"
                :button-label="__('Choose image')"
            />
            <x-ui.input
                name="slug"
                :label="__('Slug')"
                :value="old('slug', $blog->slug)"
                :error="$errors->first('slug')"
                :help="__('Leave empty to generate from the default language title.')"
                x-model="slugValue"
                x-on:input="syncSlugManualState($event.target.value)"
            />
        </x-ui.settings-panel>

        <x-ui.settings-panel
            icon="fa-light fa-magnifying-glass-chart"
            :title="__('SEO & Sharing')"
            :description="__('Control search metadata, canonical URL, and the social share image used for this post.')"
            collapsible
            :open="false"
            class="space-y-4"
        >
            <x-ui.input
                name="meta_title"
                :label="__('Meta title')"
                :value="old('meta_title', $blog->meta_title)"
                :error="$errors->first('meta_title')"
                :help="__('Optional. Leave empty to reuse the post title in search results.')"
            />

            <x-ui.textarea
                name="meta_description"
                :label="__('Meta description')"
                :error="$errors->first('meta_description')"
                :help="__('Optional. A concise summary for search and social previews.')"
                rows="4"
            >{{ old('meta_description', $blog->meta_description) }}</x-ui.textarea>

            <x-ui.input
                name="canonical_url"
                :label="__('Canonical URL')"
                :value="old('canonical_url', $blog->canonical_url)"
                :error="$errors->first('canonical_url')"
                :help="__('Optional. Use this when the source URL should be treated as the preferred SEO URL.')"
            />

            <x-ui.image-picker
                name="og_image"
                :value="$ogImageValue"
                :label="__('Open Graph image')"
                :error="$errors->first('og_image')"
                :help="__('Optional. Pick a dedicated social sharing image, or leave empty to rely on the thumbnail.')"
                :button-label="__('Choose OG image')"
            />
        </x-ui.settings-panel>
    </x-slot:sidebar>
</x-ui.form-layout>
</div>
