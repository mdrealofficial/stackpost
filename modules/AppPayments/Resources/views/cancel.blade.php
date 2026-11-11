@component(theme_view('layouts.app', 'app'), ['title' => __('Payment cancelled')])
    <div class="mx-auto w-full max-w-3xl px-6 py-10 lg:px-8">
        <div class="space-y-6 text-center">
            <div class="flex items-center justify-center">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full border text-xl shadow-[0_18px_34px_-24px_rgba(15,23,42,0.28)]" style="border-color: var(--theme-border-color); background: rgba(244,63,94,0.08); color: #e11d48;">
                    <i class="fa-light fa-ban"></i>
                </span>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-center">
                    <x-ui.badge variant="danger">{{ __('Payment cancelled') }}</x-ui.badge>
                </div>

                <h1 class="text-[2.5rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">
                    {{ __('Checkout was not completed') }}
                </h1>

                <p class="mx-auto max-w-xl text-[15px] leading-7" style="color: var(--theme-muted-text-color);">
                    {{ __('The checkout was cancelled before the payment provider confirmed the charge.') }}
                </p>
            </div>

            <x-ui.card padding="lg" class="mx-auto max-w-2xl space-y-5">
                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    {{ __('You can return to pricing and start the checkout again whenever you are ready.') }}
                </p>

                <div class="flex flex-col justify-center gap-3 sm:flex-row">
                    <x-ui.button href="{{ route('guest.pricing') }}">
                        {{ __('Back to pricing') }}
                    </x-ui.button>

                    <x-ui.button href="{{ route('portal.billing') }}" variant="outline">
                        {{ __('Open billing') }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </div>
@endcomponent
