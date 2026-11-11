@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'actions' => null,
])

<section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-10 lg:px-12 lg:pt-14">
    <div class="nova-card-surface rounded-[2.8rem] border border-white/8 bg-[linear-gradient(180deg,rgba(15,17,30,0.88)_0%,rgba(10,13,25,0.72)_100%)] px-8 py-10 shadow-[0_28px_100px_-56px_rgba(0,0,0,0.7)] backdrop-blur-xl md:px-12 md:py-14">
        @if ($eyebrow)
            <span class="inline-flex items-center rounded-full border border-violet-400/14 bg-violet-400/10 px-4 py-2 text-sm font-semibold text-violet-200 shadow-[0_0_24px_rgba(139,92,246,0.12)]">{{ $eyebrow }}</span>
        @endif

        @if ($title)
            <h1 class="mt-6 max-w-4xl text-[2.2rem] font-semibold leading-[1.02] tracking-[-0.04em] text-white sm:text-[2.8rem] lg:text-[3.35rem]">{{ $title }}</h1>
        @endif

        @if ($description)
            <p class="mt-6 max-w-3xl text-lg leading-8 text-white/60">{{ $description }}</p>
        @endif

        @if ($actions)
            <div class="mt-10 flex flex-wrap gap-4">
                {{ $actions }}
            </div>
        @endif
    </div>
</section>
