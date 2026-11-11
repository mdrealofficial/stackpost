<div class="media-library" x-data="@include('appfiles::partials.media-search-state')" x-on:keydown.escape.window="closePreview()">
    @include('appfiles::partials.media-search-style')
    <header class="media-library-heading">
        <div><h1>{{ __('Search media') }}</h1><p>{{ __('Find images and videos for your next post. Save your picks to Files.') }}</p></div>
        <x-ui.button tag="a" :href="route('portal.files.index')" variant="outline"><i class="fa-light fa-folder-open"></i>{{ __('Open Files') }}</x-ui.button>
    </header>
    @if (empty($providers))
        <x-ui.empty icon="fa-light fa-images" :title="__('Media search is not available yet')" :description="__('Your workspace does not have a media source configured. Contact your workspace administrator to enable one.')" />
    @else
        <form class="media-search-toolbar" x-on:submit.prevent="searchMedia()">
            <div class="media-search-query"><x-ui.input x-model="keyword" maxlength="255" :label="__('Search')" :placeholder="__('Try nature, workspace, or city…')" x-bind:disabled="saving" /></div>
            <x-ui.select x-model="type" x-on:change="syncSource()" :label="__('Media type')" x-bind:disabled="searching || saving"><option value="image">{{ __('Images') }}</option><option value="video">{{ __('Videos') }}</option></x-ui.select>
            <x-ui.select x-model="source" :label="__('Source')" x-bind:disabled="searching || saving">
                <template x-for="[key, label] in sources()" :key="key"><option :value="key" :selected="source === key" x-text="label.replace(' Photo', '').replace(' Video', '')"></option></template>
            </x-ui.select>
            <div x-show="type === 'video'" x-cloak><x-ui.select x-model="quality" :label="__('Video quality')" x-bind:disabled="searching || saving"><option value="standard">{{ __('Standard · up to 1080p when available') }}</option><option value="high">{{ __('Highest available · larger files') }}</option></x-ui.select></div>
            <x-ui.button type="submit" x-bind:disabled="searching || saving || !keyword.trim() || !source"><i class="fa-light fa-magnifying-glass"></i><span x-text="searching ? 'Searching…' : 'Search'"></span></x-ui.button>
        </form>
        <p class="media-library-hint" x-show="!source" x-cloak>{{ __('No source is available for this media type. Choose another type.') }}</p>
        @unless ($canImport)<p class="media-library-hint">{{ __('You can browse media. Saving to Files is not available with your current workspace permissions or settings.') }}</p>@endunless
        <div class="media-search-error" role="alert" x-show="error" x-cloak><i class="fa-light fa-circle-exclamation"></i><span x-text="error"></span></div>
        <div class="media-results-heading" x-show="searched" x-cloak>
            <p><strong x-text="results.length"></strong> {{ __('results for') }} <strong x-text="searchLabel"></strong><span class="media-result-source" x-text="' · ' + (providers[lastSearch?.source] || '')"></span></p>
            <span>{{ __('Preview media to check details before saving.') }}</span>
        </div>
        <div class="media-search-empty" x-show="!searched && !searching && !error">
            <span class="media-empty-icon"><i class="fa-light fa-images"></i></span><h2>{{ __('What will you create next?') }}</h2>
            <p>{{ __('Search for a subject, mood, or setting to find your starting point.') }}</p>
            <div class="media-search-suggestions">@foreach (['Nature', 'Workspace', 'Travel', 'Food'] as $suggestion)<button type="button" x-on:click="keyword = $el.textContent.trim(); searchMedia()">{{ __($suggestion) }}</button>@endforeach</div>
        </div>
        <div class="media-search-empty" x-show="searched && !results.length && !searching && !error" x-cloak><span class="media-empty-icon"><i class="fa-light fa-magnifying-glass"></i></span><h2>{{ __('No media found') }}</h2><p>{{ __('Try a broader keyword or another source.') }}</p></div>
        <p class="media-search-loading" role="status" x-show="searching" x-cloak>{{ __('Searching for media…') }}</p>
        <div class="media-results-grid">
            <template x-for="media in results" :key="mediaKey(media)">
                <article class="media-result-card" :class="{'is-selected': isSelected(media), 'is-saved': saved[mediaKey(media)]}">
                    <div class="media-result-image">
                        <button type="button" class="media-preview-trigger" x-on:click="openPreview(media)" :aria-label="'Preview ' + (media.author || media.source)"><img :src="media.thumbnail" :alt="media.author || media.source" loading="lazy"><span class="media-video-play" x-show="media.mediaType === 'video'"><i class="fa-solid fa-play"></i></span></button>
                        <span class="media-source-badge" x-text="media.source"></span>
                        <div class="media-result-check" x-id="['media-check']" x-show="canImport && !saved[mediaKey(media)]">
                            <x-ui.checkbox minimal x-bind:id="$id('media-check')" x-effect="$el.closest('label').htmlFor = $id('media-check')" x-bind:aria-label="'Select media by ' + (media.author || media.source)" x-bind:checked="isSelected(media)" x-on:change="toggle(media)" x-bind:disabled="saving" />
                        </div>
                        <span class="media-saved-badge" x-show="saved[mediaKey(media)]"><i class="fa-light fa-check"></i>{{ __('Saved') }}</span>
                    </div>
                    <div class="media-result-info"><strong x-text="media.author || 'Untitled media'"></strong><span x-text="media.mediaType === 'video' ? ('Video' + (media.width ? ' · ' + media.width + ' × ' + media.height : '') + (media.duration ? ' · ' + media.duration + 's' : '')) : 'Image'"></span></div>
                    <p class="media-item-error" x-show="failures[mediaKey(media)]" x-text="failures[mediaKey(media)]"></p>
                </article>
            </template>
        </div>
        <div class="media-load-more" x-show="hasMore" x-cloak><x-ui.button variant="outline" x-on:click="searchMedia(true)" x-bind:disabled="searching || saving"><span x-text="searching ? 'Loading…' : 'Load more'"></span></x-ui.button></div>
        <section class="media-import-bar" x-show="selected.length || saving" x-cloak aria-label="Selected media">
            <div class="media-import-summary"><strong><span x-text="selected.length"></span> {{ __('selected') }}</strong><button type="button" x-on:click="clearSelection()" :disabled="saving">{{ __('Clear') }}</button><span role="status" x-show="saving" x-text="completed + ' / ' + total + ' processed'"></span></div>
            <div class="media-import-folder"><x-ui.select x-model="folder" :label="__('Save to')" x-bind:disabled="saving"><option value="">{{ __('Files · root folder') }}</option>@foreach ($folders as $folderOption)<option value="{{ $folderOption['id'] }}">{{ $folderOption['name'] }}</option>@endforeach</x-ui.select></div>
            <x-ui.button x-on:click="saveSelected()" x-bind:disabled="saving || !selected.length || !canImport"><i class="fa-light fa-folder-arrow-down"></i><span x-text="saving ? 'Saving…' : Object.keys(failures).length ? 'Retry remaining' : 'Save to Files'"></span></x-ui.button>
            <div class="media-import-failures" x-show="!saving && selected.some(item => failures[mediaKey(item)])"><template x-for="item in selected.filter(item => failures[mediaKey(item)])" :key="mediaKey(item)"><p><strong x-text="item.author || item.source"></strong>: <span x-text="failures[mediaKey(item)]"></span></p></template></div>
        </section>
    @endif
    <template x-if="preview">
        <div class="media-preview-overlay" x-on:click.self="closePreview()" x-trap.inert.noscroll="preview" role="dialog" aria-modal="true" aria-label="Media preview">
            <section class="media-preview-dialog"><header><div><h2>{{ __('Media preview') }}</h2><p x-text="(preview.author || 'Unknown author') + ' · ' + preview.source"></p></div><button type="button" x-on:click="closePreview()" aria-label="Close preview"><i class="fa-light fa-xmark"></i></button></header>
                <div class="media-preview-stage"><template x-if="preview.mediaType === 'video'"><video controls playsinline preload="metadata" :src="preview.full" :poster="preview.thumbnail" x-on:error="previewError = true"></video></template><template x-if="preview.mediaType !== 'video'"><img :src="preview.full" :alt="preview.author || 'Image preview'" x-on:error="previewError = true"></template></div>
                <p class="media-item-error" x-show="previewError">{{ __('Preview could not be loaded. Open the source to view this media.') }}</p>
                <footer><a :href="preview.link || preview.full" target="_blank" rel="noopener noreferrer">{{ __('View source & usage details') }} <i class="fa-light fa-arrow-up-right-from-square"></i></a><x-ui.button x-show="canImport" x-on:click="toggle(preview)" x-bind:disabled="saving || !!saved[mediaKey(preview)]"><span x-text="saved[mediaKey(preview)] ? 'Saved to Files' : isSelected(preview) ? 'Remove from selection' : 'Select media'"></span></x-ui.button></footer>
            </section>
        </div>
    </template>
</div>
