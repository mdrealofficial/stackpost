@auth
    @php($user = auth()->user())
    @php($isPortal = request()->routeIs('portal.*'))
    @php($hasBillingAccess = $user?->hasActivePlan())
    @php($plan = $user?->plan)
    @php($creditSummary = $user?->creditSummary() ?? ['limit' => null, 'used' => 0, 'remaining' => null, 'unlimited' => true])
    @php($creditLimit = is_numeric($creditSummary['limit'] ?? null) ? max(0, (int) $creditSummary['limit']) : null)
    @php($creditsUsed = max(0, (int) ($creditSummary['used'] ?? 0)))
    @php($creditsRemaining = $creditSummary['remaining'] ?? null)
    @php($creditsUsedPercent = $creditLimit && $creditLimit > 0 ? min(100, (int) round(($creditsUsed / $creditLimit) * 100)) : null)
    @php($planActionLabel = $hasBillingAccess ? __('Upgrade') : __('Choose plan'))

    <x-ui.dropdown-menu align="right" width="auto" panel-class="min-w-[16rem]">
        <x-slot:trigger>
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-[0.85rem] border border-slate-200/80 shadow-[0_12px_24px_-20px_rgba(15,23,42,0.22)] transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/80"
                style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);"
                aria-label="{{ __('User menu') }}"
            >
                <x-ui.avatar :src="$user?->avatar_url" :name="$user?->name" size="sm" />
            </button>
        </x-slot:trigger>

        <div class="px-3 py-2">
            <p class="max-w-[13rem] truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $user?->name }}</p>
            <p class="mt-0.5 max-w-[13rem] truncate text-xs" style="color: var(--theme-muted-text-color);">{{ $user?->email }}</p>
        </div>

        <div class="my-1 h-px" style="background-color: color-mix(in srgb, var(--theme-border-color) 65%, transparent);"></div>

        @if ($isPortal)
            <x-ui.dropdown-menu-item :href="route('portal.profile')" icon="fa-light fa-user-pen" wire:navigate>
                {{ __('Profile') }}
            </x-ui.dropdown-menu-item>

            @if ($hasBillingAccess)
                <x-ui.dropdown-menu-item :href="route('portal.packages')" icon="fa-light fa-box-open" wire:navigate>
                    {{ __('Packages') }}
                </x-ui.dropdown-menu-item>

                <x-ui.dropdown-menu-item :href="route('portal.credits')" icon="fa-light fa-bolt" wire:navigate>
                    {{ __('Credits') }}
                </x-ui.dropdown-menu-item>

                <x-ui.dropdown-menu-item :href="route('portal.billing')" icon="fa-light fa-credit-card" wire:navigate>
                    {{ __('Billing') }}
                </x-ui.dropdown-menu-item>

                <x-ui.dropdown-menu-item :href="route('portal.invoices')" icon="fa-light fa-receipt" wire:navigate>
                    {{ __('Invoices') }}
                </x-ui.dropdown-menu-item>
            @endif

            <x-ui.dropdown-menu-item :href="route('portal.activity')" icon="fa-light fa-clipboard-list-check" wire:navigate>
                {{ __('Activity Log') }}
            </x-ui.dropdown-menu-item>

            @if ($user?->canUsePlanFeature('ai_studio'))
                <x-ui.dropdown-menu-item :href="route('portal.ai-studio.settings')" icon="fa-light fa-sparkles" wire:navigate>
                    {{ __('AI Settings') }}
                </x-ui.dropdown-menu-item>
            @endif
        @else
            <x-ui.dropdown-menu-item :href="route('admin-users.edit', $user)" icon="fa-light fa-user-pen" wire:navigate>
                {{ __('Profile') }}
            </x-ui.dropdown-menu-item>

            <x-ui.dropdown-menu-item :href="route('admin-user-logs.index')" icon="fa-light fa-clipboard-list-check" wire:navigate>
                {{ __('User Logs') }}
            </x-ui.dropdown-menu-item>

            <x-ui.dropdown-menu-item :href="route('settings.general')" icon="fa-light fa-gear" wire:navigate>
                {{ __('Settings') }}
            </x-ui.dropdown-menu-item>
        @endif

        <div class="mx-1.5 my-2 rounded-[1rem] border px-3 py-3.5" style="border-color: color-mix(in srgb, var(--theme-border-color) 72%, transparent 28%); background: linear-gradient(180deg, color-mix(in srgb, var(--theme-accent,#2563eb) 7%, var(--theme-surface-base) 93%) 0%, var(--theme-surface-base) 100%);">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $plan?->name ?? __('No plan assigned') }}</p>
                    <p class="mt-1 text-xs font-medium {{ $creditSummary['unlimited'] ? 'text-emerald-500' : 'text-slate-500 dark:text-slate-400' }}">
                        {{ $creditSummary['unlimited']
                            ? __('Unlimited')
                            : ($creditsRemaining !== null
                                ? __(':credits credits left', ['credits' => number_format((int) $creditsRemaining)])
                                : __('Credits unavailable')) }}
                    </p>
                </div>

                <x-ui.button href="{{ route('portal.packages') }}" variant="primary" size="sm" class="shrink-0" wire:navigate>
                    {{ $planActionLabel }}
                </x-ui.button>
            </div>

            @if (! $creditSummary['unlimited'] && $creditLimit)
                <div class="mt-3 space-y-2">
                    <div class="flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">
                        <span>{{ __('Credits used') }}</span>
                        <span>{{ $creditsUsedPercent }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-800">
                        <div class="h-full rounded-full" style="width: {{ $creditsUsedPercent }}%; background: linear-gradient(90deg, var(--theme-accent,#2563eb) 0%, color-mix(in srgb, var(--theme-accent,#2563eb) 72%, #8b5cf6 28%) 100%);"></div>
                    </div>
                    <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);">
                        {{ __('You have :credits credits left in this quota period.', ['credits' => number_format((int) $creditsRemaining)]) }}
                    </p>
                </div>
            @elseif ($creditSummary['unlimited'])
                <p class="mt-3 text-xs leading-5" style="color: var(--theme-muted-text-color);">
                    {{ __('This plan has no credit cap for the current period.') }}
                </p>
            @endif
        </div>

        <div class="my-1 h-px" style="background-color: color-mix(in srgb, var(--theme-border-color) 65%, transparent);"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.dropdown-menu-item type="submit" icon="fa-light fa-arrow-right-from-bracket" class="w-full">
                {{ __('Logout') }}
            </x-ui.dropdown-menu-item>
        </form>
    </x-ui.dropdown-menu>
@endauth
