<div class="aic-library">
    @if ((string) $selectedCategoryId === '')
        <div class="aic-library-heading"><div><span class="aic-output-kicker">{{ __('THE PROMPT LIBRARY') }}</span><h3>{{ __('Find your next angle.') }}</h3><p>{{ __('Explore a category. Make any prompt your own.') }}</p></div><span class="aic-library-count">{{ count($categoryOptions) }}<small>{{ __('categories') }}</small></span></div>
        <div class="aic-library-search"><x-ui.input wire:model.live.debounce.300ms="categorySearch" :label="__('Search categories')" :placeholder="__('Search by industry, platform or writing goal...')" /></div>
        <div class="aic-category-grid">
        @forelse ($categoryOptions as $category)
            <button type="button" class="aic-category" wire:click="selectCategory({{ $category->id }})"><span class="aic-category-icon"><i class="{{ $category->icon ?: 'fa-light fa-folder-tree' }}"></i></span><strong>{{ $category->name }}</strong><span>{{ number_format((int) $category->templates_count) }} {{ __('prompts') }}</span><i class="fa-light fa-arrow-up-right aic-category-arrow"></i></button>
        @empty
            <p class="aic-library-empty">{{ __('No categories match your search. Try another keyword.') }}</p>
        @endforelse
        </div>
    @else
        @php($category = collect($categoryOptions)->firstWhere('id', (int) $selectedCategoryId))
        <div class="aic-library-breadcrumb"><x-ui.button type="button" variant="outline" size="sm" wire:click="backToCategories"><i class="fa-light fa-arrow-left"></i> {{ __('All categories') }}</x-ui.button><span>{{ __(':count prompts', ['count' => $templates->count()]) }}</span></div>
        <div class="aic-library-heading"><div><span class="aic-output-kicker">{{ __('EXPLORE & CREATE') }}</span><h3>{{ $category?->name ?: __('Writing prompts') }}</h3><p>{{ __('Choose a starting point for your next post.') }}</p></div><span class="aic-library-symbol"><i class="{{ $category?->icon ?: 'fa-light fa-pen-nib' }}"></i></span></div>
        <div class="aic-library-search"><x-ui.input wire:model.live.debounce.300ms="templateSearch" :label="__('Search prompts')" :placeholder="__('Find an idea in this category...')" /></div>
        <div class="aic-template-grid">
        @forelse ($templates as $template)
            <article class="aic-template-card {{ (int) $selectedTemplateId === (int) $template->id ? 'is-selected' : '' }}" wire:key="aic-template-{{ $template->id }}">
                <div class="aic-template-top"><span>{{ __('PROMPT') }} {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>@if ((int) $selectedTemplateId === (int) $template->id)<span><i class="fa-light fa-check"></i> {{ __('Selected') }}</span>@else<i class="fa-light fa-sparkles"></i>@endif</div>
                <p>{{ $template->contentPreview(180) }}</p>
                @if (mb_strlen($template->cleanContent()) > 180)<details class="aic-template-full"><summary>{{ __('Read full prompt') }}</summary><p>{{ $template->cleanContent() }}</p></details>@endif
                <button type="button" class="aic-template-use" x-on:click="$wire.selectTemplate({{ $template->id }}).then(() => $dispatch('close-ai-templates'))" wire:loading.attr="disabled" wire:target="selectTemplate"><span>{{ __('Use prompt') }}</span><i class="fa-light fa-arrow-right"></i></button>
            </article>
        @empty
            <p class="aic-library-empty">{{ __('No prompts match your search. Try another keyword or category.') }}</p>
        @endforelse
        </div>
    @endif
</div>
