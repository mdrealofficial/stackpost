@component(theme_view('layouts.marketing', 'guest'), ['pageTitle' => $pageTitle])
    @include(theme_view('partials.marketing-hero', 'guest'), [
        'eyebrow' => __('FAQ'),
        'title' => __('Answers for teams that want clarity before they commit.'),
        'description' => __('Keep public support content aligned with the product narrative and easy to scan in the same dark guest shell.'),
    ])

    <section class="guest-marketing-shell guest-section-space mx-auto px-6 pt-0 lg:px-12">
        <div class="nova-card-surface rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] p-6 shadow-[0_20px_60px_-40px_rgba(0,0,0,0.62)] backdrop-blur-xl">
            <form method="GET" action="{{ route('guest.faqs') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
                <input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search answers...') }}" class="nova-soft-surface w-full rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 text-sm text-white outline-none ring-0 transition placeholder:text-white/28 focus:border-violet-400/40">
                <button type="submit" class="inline-flex items-center justify-center rounded-full px-6 py-4 text-sm font-semibold text-white transition hover:scale-[1.01]" style="background: linear-gradient(135deg, #8b5cf6 0%, #4f46e5 48%, #22d3ee 100%);">{{ __('Search') }}</button>
            </form>
        </div>

        <div x-data="{ openFaq: 0 }" class="mt-8 grid gap-4">
            @forelse ($faqs as $faq)
                <div class="group nova-card-surface rounded-[1.9rem] border border-white/8 p-6 transition duration-300" x-bind:class="openFaq === {{ $loop->index }} ? 'border-emerald-400/20 shadow-[0_20px_60px_-40px_rgba(16,185,129,0.22)]' : 'shadow-[0_18px_50px_-42px_rgba(0,0,0,0.62)]'">
                    <button type="button" x-on:click="openFaq = openFaq === {{ $loop->index }} ? -1 : {{ $loop->index }}" class="flex w-full items-start justify-between gap-4 text-left">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-100/72">
                                {{ __('FAQ') }} {{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}
                            </p>
                            <h3 class="mt-2 text-[1.16rem] font-semibold leading-[1.18] tracking-[-0.015em] text-white md:text-[1.28rem]">
                                {{ $faq->titleForLocale() }}
                            </h3>
                        </div>
                        <span class="nova-soft-surface inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-emerald-100 transition" x-bind:class="openFaq === {{ $loop->index }} ? 'rotate-45' : ''">
                            <i class="fa-light fa-plus text-base"></i>
                        </span>
                    </button>

                    <div
                        class="grid overflow-hidden transition-all duration-300 ease-out"
                        style="grid-template-rows: 0fr; opacity: 0;"
                        x-bind:style="openFaq === {{ $loop->index }} ? 'grid-template-rows: 1fr; opacity: 1; margin-top: 1.25rem;' : 'grid-template-rows: 0fr; opacity: 0; margin-top: 0;'"
                    >
                        <div class="min-h-0 overflow-hidden">
                            <div class="nova-soft-surface rounded-[1.5rem] border border-white/8 px-5 py-5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-100/66">{{ __('Answer') }}</p>
                                <div class="mt-3 max-w-3xl text-sm leading-8 text-white/58 whitespace-pre-line">
                                    {{ $faq->contentPreview(240) !== '' ? $faq->contentPreview(240) : $faq->contentForLocale() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="nova-card-surface rounded-[2rem] border border-dashed border-white/12 px-6 py-12 text-center text-sm text-white/46">{{ __('No FAQs found.') }}</div>
            @endforelse
        </div>

        @if ($faqs->hasPages())
            <div class="mt-8">{{ $faqs->links() }}</div>
        @endif
    </section>
@endcomponent
