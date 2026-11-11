@php
    $oneTimeGateways = collect($gateways)->filter(fn ($gateway) => $gateway->key !== 'manual' && $gateway->type === 'one_time')->values();
    $recurringGateways = collect($gateways)->filter(fn ($gateway) => $gateway->type === 'recurring')->values();
    $planTypeLabel = match ((int) $plan->type) {
        2 => __('Yearly'),
        3 => __('Lifetime'),
        default => __('Monthly'),
    };
    $hasTrial = ($transitionPreview['mode'] ?? null) === 'trial';
    $trialEndsAt = ! empty($transitionPreview['expires_at']) ? \Carbon\Carbon::parse($transitionPreview['expires_at']) : null;
@endphp

<div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="space-y-5 text-center">
            <div class="flex items-center justify-center gap-3">
                <x-ui.badge variant="primary">{{ $planTypeLabel }}</x-ui.badge>
                @if ($hasTrial)
                    <x-ui.badge variant="warning">{{ __(':days-day trial', ['days' => (int) $plan->trial_day]) }}</x-ui.badge>
                @endif

                <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] shadow-[0_10px_24px_-20px_rgba(var(--theme-accent-rgb),0.38)]" style="border-color: rgba(var(--theme-accent-rgb), 0.18); color: var(--theme-muted-text-color); background: rgba(var(--theme-accent-rgb), 0.05);">
                    {{ __('Total today') }} {{ $plan->currency_symbol }}{{ number_format((float) $pricing['total'], 2) }}
                </span>
            </div>

            <div class="space-y-3">
                <h1 class="text-[3rem] font-semibold leading-none tracking-[-0.055em]" style="color: var(--theme-header-text-color);">
                    {{ $plan->name }}
                </h1>

                @if ($plan->desc)
                    @php
                        $accentedPlanDescription = preg_replace(
                            '/\b(Facebook|Instagram)\b/u',
                            '<span style="color: var(--theme-accent-color); font-weight: 600;">$1</span>',
                            e((string) $plan->desc)
                        );
                    @endphp
                    <p class="mx-auto max-w-2xl text-[15px] leading-7" style="color: var(--theme-muted-text-color);">
                        {!! $accentedPlanDescription !!}
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3 text-sm">
                <div class="inline-flex items-center gap-2 rounded-[0.75rem] border px-3 py-2 shadow-[0_10px_24px_-22px_rgba(var(--theme-accent-rgb),0.32)]" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background: rgba(var(--theme-accent-rgb), 0.04); color: var(--theme-header-text-color);">
                    <i class="fa-light fa-credit-card text-xs" style="color: var(--theme-accent-color);"></i>
                    <span class="font-medium">{{ __('Secure checkout') }}</span>
                </div>

                <div class="inline-flex items-center gap-2 rounded-[0.75rem] border px-3 py-2 shadow-[0_10px_24px_-22px_rgba(var(--theme-accent-rgb),0.32)]" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background: rgba(var(--theme-accent-rgb), 0.04); color: var(--theme-header-text-color);">
                    <i class="fa-light fa-bolt text-xs" style="color: var(--theme-accent-color);"></i>
                    <span class="font-medium">{{ __('Instant access after payment') }}</span>
                </div>
            </div>
        </div>

        <div class="mt-8 space-y-6">
            @if ($hasTrial)
                <x-payments.status-notice
                    variant="info"
                    :title="__('Trial starts after checkout')"
                    :message="__('This is your first paid plan purchase, so this checkout will start a :days-day trial. Full billing access will remain active until :date, then the regular :cycle cycle takes over on future renewals or plan changes.', ['days' => (int) $plan->trial_day, 'date' => $trialEndsAt?->format('Y-m-d H:i') ?: __('the trial end date'), 'cycle' => strtolower($planTypeLabel)])"
                />
            @endif

            @if ($isSameRecurringPlan)
                <x-payments.status-notice
                    variant="danger"
                    :title="__('Active recurring subscription detected')"
                    :message="__('You already have an active recurring subscription for :plan. Repurchasing the same recurring plan is blocked. Cancel the current subscription from billing or choose a different plan to change plan.', ['plan' => $activeRecurringSubscription?->plan?->name ?: $plan->name])"
                />
            @elseif ($isRecurringChangeFlow && $activeRecurringSubscription)
                <x-payments.status-notice
                    variant="info"
                    :title="__('Change plan flow')"
                    :message="__('You already have an active recurring subscription for :current. Completing a recurring checkout here will change your plan to :target and cancel the old recurring agreement after the new one is confirmed.', ['current' => $activeRecurringSubscription->plan?->name ?: __('your current plan'), 'target' => $plan->name])"
                />
            @endif

            <x-payments.coupon-card :errors="$errors" />

            <x-payments.total-summary-card :plan="$plan" :pricing="$pricing" />

            <div class="space-y-5">
                <div class="space-y-1">
                    <h2 class="text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                        {{ __('Payment methods') }}
                    </h2>
                </div>

                @if ($oneTimeGateways->isNotEmpty())
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                {{ __('One-time Payment') }}
                            </h3>
                            <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                {{ __('Pay one time, no auto-renewal.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($oneTimeGateways as $gateway)
                                <x-payments.gateway-card :gateway="$gateway" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($recurringGateways->isNotEmpty())
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                {{ __('Recurring Payment') }}
                            </h3>
                            <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                {{ __('Subscription will auto-renew until you cancel.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($recurringGateways as $gateway)
                                <x-payments.gateway-card :gateway="$gateway" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($oneTimeGateways->isEmpty() && $recurringGateways->isEmpty())
                    <x-ui.card class="space-y-3" style="background: var(--theme-surface-soft);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                            {{ __('No payment methods available for this checkout') }}
                        </p>
                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                            {{ $isSameRecurringPlan
                                ? __('This plan already has an active recurring subscription on your account. Choose a different plan to change plan, or cancel the current recurring subscription from billing.')
                                : __('No gateway is currently available for this payment scenario.') }}
                        </p>
                    </x-ui.card>
                @endif
            </div>

            @if ($manualEnabled)
                <x-payments.manual-payment-card
                    :plan="$plan"
                    :manual-info="$manualInfo"
                    :manual-reference="$manualReference"
                    :errors="$errors"
                />
            @endif
        </div>
    </div>
