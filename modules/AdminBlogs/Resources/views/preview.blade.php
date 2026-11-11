@component(theme_view('layouts.app', 'app'), ['title' => __('Preview: :title', ['title' => $blog->titleForLocale()])])
<div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Blog Preview') }}</p>
            <h1 class="text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $blog->titleForLocale() }}</h1>
            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('This preview uses the current saved blog content and SEO settings.') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button :href="route('admin-blogs.edit', $blog)" variant="outline">{{ __('Back to editor') }}</x-ui.button>
            <x-ui.button :href="route('admin-blogs.index')" variant="outline">{{ __('Back to posts') }}</x-ui.button>
        </div>
    </div>

    <x-ui.card class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('SEO title') }}</p>
                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $blog->seoTitle() ?: __('Not set') }}</p>
            </div>
            <div class="rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Canonical URL') }}</p>
                <p class="mt-2 break-all text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $blog->canonical_url ?: __('Not set') }}</p>
            </div>
        </div>

        <div class="rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('SEO description') }}</p>
            <p class="mt-2 text-sm leading-7" style="color: var(--theme-header-text-color);">{{ $blog->seoDescription() ?: __('Not set') }}</p>
        </div>
    </x-ui.card>

    <article class="overflow-hidden rounded-[1.5rem] border shadow-[0_16px_44px_-32px_rgba(15,23,42,0.24)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
        @if ($blog->thumbnailUrl())
            <div class="aspect-[16/8] overflow-hidden border-b" style="border-color: var(--theme-border-color);">
                <img src="{{ $blog->thumbnailUrl() }}" alt="{{ $blog->titleForLocale() }}" class="h-full w-full object-cover">
            </div>
        @endif

        <div class="space-y-6 px-6 py-8 lg:px-10 lg:py-10">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($blog->category)
                        <x-ui.badge>{{ $blog->category->nameForLocale() }}</x-ui.badge>
                    @endif
                    @foreach ($blog->tags as $tag)
                        <x-ui.badge variant="primary">{{ $tag->nameForLocale() }}</x-ui.badge>
                    @endforeach
                    <x-ui.badge :variant="$blog->status ? 'success' : 'neutral'">{{ $blog->statusLabel() }}</x-ui.badge>
                </div>

                <h2 class="text-3xl font-semibold tracking-[-0.035em]" style="color: var(--theme-header-text-color);">{{ $blog->titleForLocale() }}</h2>

                @if ($blog->excerptForLocale() !== '')
                    <p class="text-lg leading-8" style="color: var(--theme-muted-text-color);">{{ $blog->excerptForLocale() }}</p>
                @endif
            </div>

            <div class="blog-preview-content">
                {!! $blog->normalizedContentForLocale() !!}
            </div>
        </div>
    </article>
</div>

<style>
    .blog-preview-content {
        color: var(--theme-header-text-color);
        font-size: 1rem;
        line-height: 1.8;
    }
    .blog-preview-content > * + * {
        margin-top: 1rem;
    }
    .blog-preview-content h1,
    .blog-preview-content h2,
    .blog-preview-content h3,
    .blog-preview-content h4,
    .blog-preview-content h5,
    .blog-preview-content h6 {
        color: var(--theme-header-text-color);
        font-weight: 700;
        line-height: 1.2;
        margin-top: 1.6rem;
        margin-bottom: 0.85rem;
    }
    .blog-preview-content h1 { font-size: 2.2rem; }
    .blog-preview-content h2 { font-size: 1.85rem; }
    .blog-preview-content h3 { font-size: 1.55rem; }
    .blog-preview-content h4 { font-size: 1.3rem; }
    .blog-preview-content ul { list-style: disc; padding-left: 1.5rem; }
    .blog-preview-content ol { list-style: decimal; padding-left: 1.5rem; }
    .blog-preview-content li { display: list-item; margin: 0.25rem 0; }
    .blog-preview-content blockquote {
        margin: 1.5rem 0;
        padding: 1rem 1.25rem;
        border-left: 4px solid rgba(var(--theme-accent-rgb), 0.4);
        background: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);
        border-radius: 0 1rem 1rem 0;
        color: var(--theme-muted-text-color);
    }
    .blog-preview-content pre {
        overflow-x: auto;
        padding: 1rem 1.25rem;
        border-radius: 1rem;
        background: #0f172a;
        color: #e2e8f0;
    }
    .blog-preview-content code {
        font-family: Consolas, 'Courier New', monospace;
        font-size: 0.92em;
    }
    .blog-preview-content figure {
        overflow: hidden;
        max-width: 590px;
        margin: 1.5rem auto;
        border: 1px solid var(--theme-border-color);
        border-radius: 1.25rem;
        background: color-mix(in srgb, var(--theme-surface-base) 98%, transparent);
    }
    .blog-preview-content figure img,
    .blog-preview-content figure video,
    .blog-preview-content figure iframe {
        display: block;
        width: 100%;
        max-width: 590px;
        margin: 0 auto;
    }
    .blog-preview-content figure figcaption {
        padding: 0.85rem 1rem 1rem;
        border-top: 1px solid color-mix(in srgb, var(--theme-border-color) 84%, transparent);
        color: var(--theme-muted-text-color);
        font-size: 0.95rem;
        line-height: 1.7;
    }
    .blog-preview-content .embedded-video {
        position: relative;
        width: 100%;
        padding-top: 56.25%;
        overflow: hidden;
        background: #020617;
    }
    .blog-preview-content .embedded-video > iframe,
    .blog-preview-content .embedded-video > video {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
        object-fit: cover;
    }
</style>
@endcomponent
