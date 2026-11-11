<div class="portal-tools caption-library" x-data="{ editorOpen: @entangle('editorOpen') }">
    @include('partials.portal-tools-style')
    @include('appcaptions::livewire.partials.library-styles')
    <x-ui.sub-header compact :title="__('Captions')" :description="__('Save your best captions, find the right words and reuse them in your next post.')"><x-slot:actions>@if($canManage)<x-ui.button type="button" size="lg" wire:click="openCreateEditor"><i class="fa-light fa-plus" aria-hidden="true"></i>{{ __('Create caption') }}</x-ui.button>@endif</x-slot:actions></x-ui.sub-header>
    <div class="cap-summary"><span><strong>{{ $stats['total'] }}</strong>{{ __('Saved captions') }}</span><span><i class="cap-dot"></i><strong>{{ $stats['active'] }}</strong>{{ __('Ready to use') }}</span><span><i class="fa-light fa-sparkles"></i><strong>{{ $stats['ai'] }}</strong>{{ __('AI assisted') }}</span><span><i class="fa-light fa-pen-nib"></i><strong>{{ $stats['manual'] }}</strong>{{ __('Manually written') }}</span></div>
    <section class="cap-toolbar" aria-label="{{ __('Caption filters') }}">
        <div class="cap-toolbar-top"><div class="cap-search"><i class="fa-light fa-magnifying-glass" aria-hidden="true"></i><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search captions, tags or notes…') }}" aria-label="{{ __('Search captions') }}"></div><x-ui.select wire:model.live="sourceFilter" aria-label="{{ __('Caption source') }}"><option value="">{{ __('All sources') }}</option><option value="manual">{{ __('Manual') }}</option><option value="ai">{{ __('AI assisted') }}</option></x-ui.select></div>
        <div class="cap-toolbar-bottom"><nav class="cap-tabs" aria-label="{{ __('Caption status') }}">@foreach([''=>__('All captions'),'active'=>__('Active'),'draft'=>__('Drafts'),'archived'=>__('Archived')] as $key=>$label)<button type="button" wire:click="$set('statusFilter', '{{ $key }}')" aria-pressed="{{ $statusFilter===$key ? 'true':'false' }}">{{ $label }}</button>@endforeach</nav><span class="cap-result" role="status">{{ __(':count results', ['count'=>$captions->total()]) }}@if($search!=='' || $sourceFilter!=='' || $statusFilter!=='')<button type="button" wire:click="resetFilters">{{ __('Clear filters') }}</button>@endif</span></div>
    </section>
    @if($captions->isNotEmpty())
    <div class="cap-grid">
        @foreach($captions as $caption)
        <article class="cap-card" wire:key="caption-card-{{ $caption->id }}">
            <div class="cap-card-top"><span class="cap-source"><i class="fa-light {{ $caption->source_type==='ai' ? 'fa-sparkles':'fa-pen-nib' }}"></i>{{ $caption->source_type==='ai' ? __('AI assisted') : __('Manual') }}</span><span class="cap-status" data-status="{{ $caption->status }}">{{ __(['active'=>'Active','draft'=>'Draft','archived'=>'Archived'][$caption->status] ?? $caption->status) }}</span></div>
            <h2>{{ $caption->name }}</h2><p class="cap-excerpt">{{ $caption->content }}</p>
            <div class="cap-tags">@foreach(collect($caption->tags ?? [])->filter()->take(3) as $tag)<span>#{{ $tag }}</span>@endforeach @if(count($caption->tags ?? [])>3)<span>+{{ count($caption->tags)-3 }}</span>@endif</div>
            <div class="cap-card-meta"><span>{{ __(':count characters', ['count'=>mb_strlen($caption->content)]) }}</span><time title="{{ $caption->updated_at?->format('Y-m-d H:i') }}">{{ $caption->updated_at?->diffForHumans() }}</time></div>
            <div class="cap-card-actions">
                <x-ui.modal width="lg" :title="$caption->name" :description="__('Review the full caption before reusing it.')"><x-slot:trigger><button type="button" class="cap-text-button" aria-label="{{ __('Preview :name', ['name'=>$caption->name]) }}"><i class="fa-light fa-expand"></i>{{ __('Preview') }}</button></x-slot:trigger><div class="cap-full-copy">{{ $caption->content }}</div>@if($caption->notes)<div class="cap-preview-notes"><strong>{{ __('Internal notes') }}</strong><p>{{ $caption->notes }}</p></div>@endif<x-slot:footer><x-ui.button type="button" variant="outline" :data-copy="$caption->content" data-copied="{{ __('Caption copied.') }}" data-copy-error="{{ __('Could not copy. Open Preview to select the text.') }}" x-on:click="navigator.clipboard.writeText($el.dataset.copy).then(()=>window.dispatchEvent(new CustomEvent('app-toast',{detail:{type:'success',message:$el.dataset.copied}}))).catch(()=>window.dispatchEvent(new CustomEvent('app-toast',{detail:{type:'error',message:$el.dataset.copyError}})))">{{ __('Copy caption') }}</x-ui.button></x-slot:footer></x-ui.modal>
                <button class="cap-text-button" type="button" data-copy="{{ $caption->content }}" data-copied="{{ __('Caption copied.') }}" data-copy-error="{{ __('Could not copy. Open Preview to select the text.') }}" x-on:click="navigator.clipboard.writeText($el.dataset.copy).then(()=>window.dispatchEvent(new CustomEvent('app-toast',{detail:{type:'success',message:$el.dataset.copied}}))).catch(()=>window.dispatchEvent(new CustomEvent('app-toast',{detail:{type:'error',message:$el.dataset.copyError}})))"><i class="fa-light fa-copy"></i>{{ __('Copy') }}</button>
                @if($canManage && $caption->status==='active' && Route::has('portal.publishing.calendar'))<a class="cap-use" href="{{ route('portal.publishing.calendar',['caption'=>$caption->id]) }}" wire:navigate>{{ __('Use caption') }} <i class="fa-light fa-arrow-up-right"></i></a>@endif
            </div>
            @if($canManage)<div class="cap-manage"><button type="button" wire:click="editCaption({{ $caption->id }})"><i class="fa-light fa-pen-to-square"></i>{{ __('Edit') }}</button><button type="button" wire:click="deleteCaption({{ $caption->id }})" wire:confirm="{{ __('Permanently delete this caption? This cannot be undone.') }}">{{ __('Delete') }}</button></div>@endif
        </article>
        @endforeach
    </div>
    <div class="tool-pagination">{{ $captions->links() }}</div>
    @else
    <section class="cap-empty"><div class="cap-empty-art" aria-hidden="true"><div><i class="fa-light fa-quote-left"></i><span></span><span></span><span></span></div><i class="fa-light fa-sparkles"></i></div>
        <span class="cap-eyebrow">{{ $stats['total'] ? __('Try another search') : __('Your words, organized') }}</span><h2>{{ $stats['total'] ? __('No captions match these filters') : __('Build a library worth coming back to') }}</h2><p>{{ $stats['total'] ? __('Try another keyword or clear your filters to see more captions.') : __('Keep product introductions, announcements and signature messages in one place. Write once, make it your own every time.') }}</p>
        @if($stats['total'])<x-ui.button variant="outline" type="button" wire:click="resetFilters">{{ __('Clear filters') }}</x-ui.button>@elseif($canManage)<x-ui.button type="button" wire:click="openCreateEditor"><i class="fa-light fa-plus"></i>{{ __('Write your first caption') }}</x-ui.button>@else<p>{{ __('Captions shared with your workspace will appear here.') }}</p>@endif
        @if(!$stats['total'])<div class="cap-guide"><span><i class="fa-light fa-pen-line"></i>{{ __('Write or paste') }}</span><i class="fa-light fa-arrow-right"></i><span><i class="fa-light fa-tags"></i>{{ __('Organize with tags') }}</span><i class="fa-light fa-arrow-right"></i><span><i class="fa-light fa-paper-plane"></i>{{ __('Reuse in a post') }}</span></div>@endif
    </section>
    @endif
    <template x-teleport="body">
        <div
            x-cloak
            x-show="editorOpen"
            role="dialog" aria-modal="true" aria-label="{{ __('Caption editor') }}"
            class="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-6"
            x-on:keydown.escape.window="$wire.resetEditor()"
        >
            <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-[4px]" x-on:click="$wire.resetEditor()"></div>

            <div x-show="editorOpen" x-transition.opacity.scale.95 class="relative w-full max-w-[46rem]">
                <div class="max-h-[92vh] overflow-y-auto rounded-t-[1.4rem] border px-5 py-5 shadow-[0_-24px_70px_-30px_rgba(15,23,42,0.45)] sm:rounded-[1.4rem] sm:px-6 sm:py-6 sm:shadow-[0_32px_90px_-38px_rgba(15,23,42,0.42)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                    @include('appcaptions::livewire.partials.editor-form')
                </div>
            </div>
        </div>
    </template>
</div>
