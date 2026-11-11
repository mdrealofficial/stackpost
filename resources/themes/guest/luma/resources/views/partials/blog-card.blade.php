@php
    /** @var \Modules\AdminBlogs\Models\Blog $blog */
    $compact = (bool) ($compact ?? false);
@endphp

<article data-reveal class="nova-tilt-hover nova-card-surface group overflow-hidden rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] shadow-[0_20px_60px_-42px_rgba(0,0,0,0.62)] transition hover:-translate-y-1">
    @if ($blog->thumbnailUrl())
        <a href="{{ route('guest.blog-show', $blog->slug) }}" class="block overflow-hidden">
            <div class="relative h-56 w-full overflow-hidden">
                <img
                    src="{{ $blog->thumbnailUrl() }}"
                    alt="{{ $blog->titleForLocale() }}"
                    class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >
                <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(7,9,20,0.04)_0%,rgba(7,9,20,0.18)_48%,rgba(7,9,20,0.78)_100%)]"></div>
                <div class="absolute inset-x-0 bottom-0 flex items-end justify-between px-5 py-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/42">{{ __('Editorial note') }}</p>
                        <p class="mt-2 max-w-[16rem] text-base font-semibold leading-6 text-white/92">{{ $blog->titleForLocale() }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/[0.08] text-cyan-100 backdrop-blur-sm">
                        <i class="fa-light fa-newspaper text-lg"></i>
                    </span>
                </div>
            </div>
        </a>
    @else
        <a href="{{ route('guest.blog-show', $blog->slug) }}" class="block">
            <div class="relative flex h-56 items-end overflow-hidden bg-[linear-gradient(135deg,rgba(34,211,238,0.10)_0%,rgba(139,92,246,0.14)_44%,rgba(17,24,39,0.92)_100%)] px-5 py-5">
                <div class="absolute inset-0 opacity-80" style="background-image: radial-gradient(circle at top left, rgba(34,211,238,0.18), transparent 28%), radial-gradient(circle at bottom right, rgba(139,92,246,0.18), transparent 26%);"></div>
                <div class="relative flex w-full items-end justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/42">{{ __('Blog post') }}</p>
                        <p class="mt-3 max-w-[16rem] text-lg font-semibold leading-7 text-white">{{ $blog->titleForLocale() }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/[0.08] text-cyan-100 backdrop-blur-sm">
                        <i class="fa-light fa-newspaper text-lg"></i>
                    </span>
                </div>
            </div>
        </a>
    @endif

    <div class="space-y-5 p-6">
        <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/36">
            @if ($blog->category)
                <span class="inline-flex items-center rounded-full border border-white/10 px-2.5 py-1">{{ $blog->category->nameForLocale() }}</span>
            @endif
            <span>{{ $blog->publishedAtFormatted('M d, Y') ?: $blog->createdAtFormatted('M d, Y') }}</span>
        </div>

        <div class="space-y-3">
            <h3 class="{{ $compact ? 'text-[1.45rem] leading-[1.18]' : 'text-3xl leading-[1.12]' }} font-semibold tracking-[-0.03em] text-white">
                <a href="{{ route('guest.blog-show', $blog->slug) }}" class="no-theme-link text-white transition hover:text-cyan-200">{{ $blog->titleForLocale() }}</a>
            </h3>
            <p class="text-sm leading-7 text-white/58">{{ $blog->contentPreview($compact ? 118 : 170) }}</p>
        </div>

        <a href="{{ route('guest.blog-show', $blog->slug) }}" class="nova-sheen inline-flex items-center rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white/78 transition hover:bg-white/[0.05] hover:text-white">
            {{ __('Read article') }}
            <i class="fa-light fa-arrow-right ml-2"></i>
        </a>
    </div>
</article>
