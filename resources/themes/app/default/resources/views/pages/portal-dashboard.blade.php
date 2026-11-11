@component(theme_view('layouts.app', 'app'), ['title' => __('User Dashboard')])
    @php
        $user = auth()->user();
        $plan = $user?->plan;
    @endphp
    <div class="space-y-6">
        <x-ui.sub-header
            :eyebrow="__('User portal')"
            :title="__('User Dashboard')"
            :description="__('Default post-login workspace for end users, teams, reports, and account operations.')"
        >
            <x-slot:actions>
                @if ($user?->canAccessAdmin())
                    <x-ui.button href="{{ route('dashboard') }}" variant="outline" wire:navigate>
                        {{ __('Open admin') }}
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.sub-header>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Workspace') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Personal portal') }}</p>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('This area will host team management, reports, channels, files, and user-facing SaaS tools.') }}</p>
            </x-ui.card>

            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Status') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Ready') }}</p>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Login now lands here by default so the product can grow around a user-first portal experience.') }}</p>
            </x-ui.card>

            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Switch') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Admin available') }}</p>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Use the sidebar footer button to move between the user portal and admin workspace.') }}</p>
            </x-ui.card>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
            <x-ui.card class="space-y-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Current plan') }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $plan?->name ?? __('No plan assigned') }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {{ $plan
                            ? __('This plan will drive which user dashboard features become available as the portal grows.')
                            : __('Assign a plan from the admin finance area before rolling out gated user dashboard features.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-chart-surface);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Status') }}</p>
                        <p class="mt-3 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $user?->hasActivePlan() ? __('Active') : __('Inactive') }}</p>
                    </div>
                    <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-chart-surface);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Pricing') }}</p>
                        <p class="mt-3 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $plan ? $plan->currency_symbol.' '.number_format((float) $plan->price, 2) : __('—') }}</p>
                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $plan ? match((int) $plan->type) { 2 => __('Yearly'), 3 => __('Lifetime'), default => __('Monthly') } : __('No billing cycle') }}</p>
                    </div>
                    <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-chart-surface);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Expires') }}</p>
                        <p class="mt-3 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ $user?->plan_expires_at?->format('Y-m-d') ?? __('No expiry') }}</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="space-y-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Feature bundle') }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Plan capabilities') }}</p>
                </div>

                <div class="space-y-3">
                    @forelse (collect($plan?->permissions ?? [])->take(8) as $feature => $value)
                        <div class="flex items-center gap-3 rounded-[0.95rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-chart-surface);">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[rgba(var(--theme-accent-rgb,37_99_235),0.12)] text-[var(--theme-accent,#2563eb)]">
                                <i class="fa-light fa-check text-sm"></i>
                            </span>
                            <div>
                                <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ str($feature)->replace(['-', '_'], ' ')->title() }}</span>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ is_bool($value) ? ($value ? __('Enabled') : __('Disabled')) : $value }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('No plan permissions are defined yet.') }}</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>
@endcomponent
