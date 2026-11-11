@php
    $isCreditPack = ($checkout->meta['checkout_kind'] ?? null) === 'credit_pack';
    $statusConfig = match ($status) {
        'success' => [
            'label' => __('Payment completed'),
            'badge' => 'success',
            'icon' => 'fa-light fa-check',
            'surface' => 'rgba(16,185,129,0.08)',
            'icon_color' => '#059669',
        ],
        'pending' => [
            'label' => __('Payment pending'),
            'badge' => 'primary',
            'icon' => 'fa-light fa-clock',
            'surface' => 'rgba(var(--theme-accent-rgb),0.08)',
            'icon_color' => 'var(--theme-accent-color)',
        ],
        default => [
            'label' => __('Payment failed'),
            'badge' => 'danger',
            'icon' => 'fa-light fa-circle-exclamation',
            'surface' => 'rgba(244,63,94,0.08)',
            'icon_color' => '#e11d48',
        ],
    };

    $detailCards = [
        [
            'label' => __('Gateway'),
            'value' => strtoupper($checkout->gateway),
            'muted' => $checkout->gatewayType === 'recurring' ? __('Recurring charge') : __('One-time charge'),
        ],
        [
            'label' => __('Amount'),
            'value' => strtoupper($checkout->currency).' '.number_format((float) $checkout->amount, 2),
            'muted' => __('Captured for this checkout'),
        ],
        [
            'label' => $isCreditPack ? __('Credit pack') : __('Plan'),
            'value' => $isCreditPack ? ($checkout->meta['credit_pack_name'] ?? __('Credit pack')) : ($checkout->meta['plan_name'] ?? (string) $checkout->planId),
            'muted' => $isCreditPack ? __('Added to your AI credit balance after confirmation') : __('Activated after confirmation'),
        ],
        [
            'label' => __('Invoice'),
            'value' => $paymentHistory?->id_secure ?: __('Pending'),
            'muted' => __('Internal billing reference'),
            'mono' => true,
        ],
        [
            'label' => __('Transaction'),
            'value' => $paymentHistory?->transaction_id ?: ($transactionId ?: __('Pending')),
            'muted' => __('Gateway confirmation reference'),
            'mono' => true,
        ],
    ];
@endphp

@component(theme_view('layouts.app', 'app'), ['title' => __('Payment status')])
    <style>
        @keyframes payment-success-pop {
            0% { opacity: 0; transform: scale(0.88); }
            65% { opacity: 1; transform: scale(1.04); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes payment-success-ring {
            0% { opacity: 0.55; transform: scale(0.82); }
            100% { opacity: 0; transform: scale(1.5); }
        }

        @keyframes payment-success-rise {
            0% { opacity: 0; transform: translateY(16px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .payment-success-reveal {
            opacity: 0;
            animation: payment-success-rise 620ms cubic-bezier(.2,.8,.2,1) forwards;
        }

        .payment-success-reveal[data-delay="1"] { animation-delay: 90ms; }
        .payment-success-reveal[data-delay="2"] { animation-delay: 160ms; }
        .payment-success-reveal[data-delay="3"] { animation-delay: 240ms; }

        .payment-success-seal {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 5.5rem;
            width: 5.5rem;
            border-radius: 9999px;
            border: 1px solid var(--theme-border-color);
            background: linear-gradient(180deg, rgba(255,255,255,0.94), {{ $statusConfig['surface'] }});
            color: {{ $statusConfig['icon_color'] }};
            box-shadow: 0 26px 50px -34px rgba(15, 23, 42, 0.34);
            animation: payment-success-pop 560ms cubic-bezier(.2,.8,.2,1) both;
        }

        .payment-success-seal::before,
        .payment-success-seal::after {
            content: "";
            position: absolute;
            inset: -0.55rem;
            border-radius: inherit;
            border: 1px solid rgba(16, 185, 129, 0.16);
            animation: payment-success-ring 2.4s ease-out infinite;
        }

        .payment-success-seal::after {
            animation-delay: 1.2s;
        }
    </style>

    <div class="mx-auto w-full max-w-5xl px-6 py-10 lg:px-8">
        <div class="space-y-8">
            <div class="space-y-5 text-center payment-success-reveal" data-delay="1">
                <div class="flex items-center justify-center">
                    <span class="payment-success-seal text-[1.55rem]">
                        <i class="{{ $statusConfig['icon'] }}"></i>
                    </span>
                </div>

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <x-ui.badge :variant="$statusConfig['badge']">{{ __('Payment status') }}</x-ui.badge>

                        @if ($paymentHistory?->status === 1)
                            <x-ui.badge variant="primary">{{ __('Billing updated') }}</x-ui.badge>
                        @endif

                        <x-ui.badge variant="neutral">{{ __('Receipt ready') }}</x-ui.badge>
                    </div>

                    <h1 class="text-[3rem] font-semibold tracking-[-0.06em] sm:text-[3.4rem]" style="color: var(--theme-header-text-color);">
                        {{ $statusConfig['label'] }}
                    </h1>

                    <p class="mx-auto max-w-2xl text-[15px] leading-7" style="color: var(--theme-muted-text-color);">
                        {{ $message ?: __('The payment provider returned a status update for your checkout.') }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(21rem,0.85fr)]">
                <div class="space-y-6 payment-success-reveal" data-delay="2">
                    <section class="overflow-hidden rounded-[1.5rem] border" style="border-color: var(--theme-border-color); background: linear-gradient(180deg, rgba(255,255,255,0.98), var(--theme-surface-soft)); box-shadow: 0 30px 80px -48px rgba(15,23,42,0.22);">
                        <div class="flex flex-col gap-5 border-b px-7 py-6 sm:flex-row sm:items-end sm:justify-between" style="border-color: var(--theme-border-color);">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                                    {{ __('Transaction receipt') }}
                                </p>
                                <div>
                                    <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                                        {{ $isCreditPack ? ($checkout->meta['credit_pack_name'] ?? __('Credit pack')) : ($checkout->meta['plan_name'] ?? (string) $checkout->planId) }}
                                    </p>
                                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                        {{ $status === 'success'
                                            ? ($isCreditPack
                                                ? __('The purchased credits have been attached to your account and the payment reference is now stored in billing.')
                                                : __('Access has been updated and the payment reference is now attached to your billing history.'))
                                            : __('The checkout response was recorded and is available for follow-up inside billing.') }}
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-[1.15rem] border px-5 py-4 text-left sm:min-w-[15rem] sm:text-right" style="border-color: var(--theme-border-color); background: rgba(var(--theme-accent-rgb),0.05);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">
                                    {{ __('Charged total') }}
                                </p>
                                <p class="mt-3 text-[2.15rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">
                                    {{ strtoupper($checkout->currency) }} {{ number_format((float) $checkout->amount, 2) }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 px-7 py-6 md:grid-cols-3">
                            <div class="rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">
                                    {{ __('Step 1') }}
                                </p>
                                <p class="mt-3 text-base font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Gateway confirmed') }}
                                </p>
                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ __('The provider returned a valid payment response for this checkout.') }}
                                </p>
                            </div>

                            <div class="rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">
                                    {{ __('Step 2') }}
                                </p>
                                <p class="mt-3 text-base font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Billing recorded') }}
                                </p>
                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ __('An invoice and transaction reference were stored in your billing history.') }}
                                </p>
                            </div>

                            <div class="rounded-[1rem] border px-4 py-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">
                                    {{ __('Step 3') }}
                                </p>
                                <p class="mt-3 text-base font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">
                                    {{ $isCreditPack ? __('Credits available') : __('Plan activated') }}
                                </p>
                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ $isCreditPack ? __('Your AI credit balance was increased and can be used right away in AI Studio.') : __('Your workspace can continue from the billing or dashboard area immediately.') }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($detailCards as $item)
                            <div class="rounded-[1.2rem] border px-5 py-5 payment-success-reveal" data-delay="3" style="border-color: var(--theme-border-color); background: var(--theme-surface-base); box-shadow: 0 20px 40px -36px rgba(15,23,42,0.2);">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">
                                    {{ $item['label'] }}
                                </p>
                                <p class="mt-3 break-all font-semibold tracking-[-0.03em] {{ ! empty($item['mono']) ? 'font-mono text-[15px]' : 'text-[1.15rem]' }}" style="color: var(--theme-header-text-color);">
                                    {{ $item['value'] }}
                                </p>
                                <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ $item['muted'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <aside class="space-y-4 payment-success-reveal" data-delay="3">
                    <div class="rounded-[1.4rem] border px-6 py-6" style="border-color: var(--theme-border-color); background: var(--theme-surface-base); box-shadow: 0 24px 60px -46px rgba(15,23,42,0.28);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                            {{ __('Next step') }}
                        </p>
                        <h2 class="mt-3 text-[1.45rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                            {{ $isCreditPack ? __('Open AI credits') : __('Continue in billing') }}
                        </h2>
                        <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                            {{ $isCreditPack
                                ? __('Review the updated credit balance, inspect the ledger, or continue back to AI Studio.')
                                : __('Review the recorded invoice, confirm the payment history entry, or return to your workspace dashboard.') }}
                        </p>

                        <div class="mt-6 space-y-3">
                            <x-ui.button href="{{ $isCreditPack ? route('portal.credits') : route('portal.billing') }}" class="w-full justify-center">
                                {{ $isCreditPack ? __('Open AI credits') : __('Open billing') }}
                            </x-ui.button>

                            <x-ui.button href="{{ route('portal.dashboard') }}" variant="outline" class="w-full justify-center">
                                {{ __('Go to dashboard') }}
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="rounded-[1.4rem] border px-6 py-6" style="border-color: var(--theme-border-color); background: linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.045), rgba(255,255,255,0.94));">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                            {{ __('Recorded now') }}
                        </p>
                        <dl class="mt-4 space-y-4">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Gateway') }}</dt>
                                <dd class="text-sm font-semibold uppercase" style="color: var(--theme-header-text-color);">{{ $checkout->gateway }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Charge type') }}</dt>
                                <dd class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                    {{ $checkout->gatewayType === 'recurring' ? __('Recurring') : __('One-time') }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Invoice') }}</dt>
                                <dd class="max-w-[13rem] break-all text-right font-mono text-[13px]" style="color: var(--theme-header-text-color);">
                                    {{ $paymentHistory?->id_secure ?: __('Pending') }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>
        </div>
    </div>
@endcomponent
