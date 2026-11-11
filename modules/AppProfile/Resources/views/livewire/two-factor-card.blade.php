<x-ui.card class="space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Security') }}</p>
            <h3 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                {{ __('Two-factor authentication') }}
            </h3>
        </div>

        <x-ui.badge :variant="$twoFactorEnabled ? 'success' : 'neutral'">
            {{ $twoFactorEnabled ? __('Enabled') : __('Off') }}
        </x-ui.badge>
    </div>

    @if (! $canManageTwoFactor)
        <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
            <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                {{ $workspaceAllowsTwoFactor
                    ? __('Two-factor authentication is currently unavailable for this workspace.')
                    : __('Two-factor authentication has been turned off in admin authentication rules, so users cannot enable it from the portal right now.') }}
            </p>
        </div>
    @else
        <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
            <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                @if ($twoFactorEnabled)
                    {{ __('A one-time code from your authenticator app is now required when signing in.') }}
                @else
                    {{ __('Add an authenticator app checkpoint to reduce the risk of unauthorized access to this account.') }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3" wire:cloak>
            @if ($twoFactorEnabled)
                <x-ui.button variant="danger" wire:click="disable">
                    {{ __('Disable 2FA') }}
                </x-ui.button>
            @else
                <x-ui.button wire:click="enable">
                    {{ __('Enable 2FA') }}
                </x-ui.button>
            @endif
        </div>

        @if ($twoFactorEnabled)
            @livewire(\Modules\AdminSettings\Livewire\TwoFactor\RecoveryCodes::class)
        @endif

        <div x-data="{ open: $wire.entangle('showModal') }" x-on:keydown.escape.window="$wire.closeModal()">
            <template x-teleport="body">
                <div
                    x-cloak
                    x-show="open"
                    class="fixed inset-0 z-[220] flex items-center justify-center bg-slate-950/60 p-4"
                >
                    <div class="w-full max-w-4xl overflow-hidden rounded-[1.9rem] border shadow-[0_40px_120px_-40px_rgba(15,23,42,0.45)]" style="border-color: rgba(var(--theme-accent-rgb), 0.12); background: var(--theme-surface-base);">
                        <div class="grid min-h-[560px] lg:grid-cols-[380px_minmax(0,1fr)]">
                            <div class="relative overflow-hidden px-7 py-7" style="background:
                                radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.22), transparent 36%),
                                linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.08) 0%, rgba(var(--theme-accent-rgb), 0.03) 100%);">
                                <div class="relative flex h-full flex-col">
                                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-[1.4rem] border shadow-[0_18px_40px_-26px_rgba(var(--theme-accent-rgb),0.4)]" style="border-color: rgba(var(--theme-accent-rgb), 0.16); background: rgba(255,255,255,0.6); color: var(--theme-accent);">
                                        <i class="fa-light fa-shield-check text-2xl"></i>
                                    </div>

                                    <div class="mt-7 space-y-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Security') }}</p>
                                        <h4 class="text-[1.7rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ $this->modalConfig['title'] }}</h4>
                                        <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $this->modalConfig['description'] }}</p>
                                    </div>

                                    <div class="mt-8 grid gap-3">
                                        <div class="rounded-[1.1rem] border px-4 py-4" style="border-color: rgba(var(--theme-accent-rgb), 0.12); background: rgba(255,255,255,0.55);">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('What you need') }}</p>
                                            <p class="mt-2 text-sm leading-7" style="color: var(--theme-header-text-color);">
                                                {{ __('Use Google Authenticator, 1Password, Authy, or any TOTP-compatible authenticator app.') }}
                                            </p>
                                        </div>

                                        <div class="rounded-[1.1rem] border px-4 py-4" style="border-color: rgba(var(--theme-accent-rgb), 0.12); background: rgba(255,255,255,0.55);">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Why it matters') }}</p>
                                            <p class="mt-2 text-sm leading-7" style="color: var(--theme-header-text-color);">
                                                {{ __('A second factor helps protect billing, invoices, and account access even if your password is exposed.') }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="mt-auto inline-flex items-center gap-2 pt-6 text-sm font-medium transition hover:opacity-80"
                                        style="color: var(--theme-muted-text-color);"
                                        wire:click="closeModal"
                                    >
                                        <i class="fa-light fa-arrow-left"></i>
                                        <span>{{ __('Close setup') }}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="px-7 py-7">
                                <div class="space-y-6">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">
                                                {{ $showVerificationStep ? __('Verification') : __('Setup') }}
                                            </p>
                                            <h5 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                                {{ $showVerificationStep ? __('Confirm your authenticator code') : __('Scan the QR code to pair your device') }}
                                            </h5>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex h-11 w-11 items-center justify-center rounded-[0.95rem] border transition hover:opacity-80"
                                            style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color); background: var(--theme-surface-soft);"
                                            wire:click="closeModal"
                                        >
                                            <i class="fa-light fa-xmark"></i>
                                        </button>
                                    </div>

                                    @if ($showVerificationStep)
                                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_260px]">
                                            <div class="rounded-[1.3rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                                <x-ui.input
                                                    name="code"
                                                    wire:model="code"
                                                    maxlength="6"
                                                    inputmode="numeric"
                                                    :label="__('Authentication code')"
                                                    :error="$errors->first('code')"
                                                    :help="__('Open your authenticator app and enter the current 6-digit code.')"
                                                />

                                                <div class="mt-5 flex items-center gap-3">
                                                    <x-ui.button variant="outline" class="flex-1" wire:click="resetVerification">
                                                        {{ __('Back') }}
                                                    </x-ui.button>

                                                    <x-ui.button class="flex-1" wire:click="confirmTwoFactor" x-bind:disabled="$wire.code.length < 6">
                                                        {{ __('Confirm') }}
                                                    </x-ui.button>
                                                </div>
                                            </div>

                                            <div class="rounded-[1.3rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Tips') }}</p>
                                                <ul class="mt-3 space-y-3 text-sm leading-7" style="color: var(--theme-header-text-color);">
                                                    <li>{{ __('Make sure your device time is set automatically.') }}</li>
                                                    <li>{{ __('Use the newest code shown in the app.') }}</li>
                                                    <li>{{ __('After confirmation, recovery codes will be available in your profile.') }}</li>
                                                </ul>
                                            </div>
                                        </div>
                                    @else
                                        @error('setupData')
                                            <div class="rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(225, 29, 72, 0.24); background: rgba(225, 29, 72, 0.08); color: rgb(190, 24, 93);">
                                                {{ $message }}
                                            </div>
                                        @enderror

                                        <div class="grid gap-5 xl:grid-cols-[300px_minmax(0,1fr)]">
                                            <div class="rounded-[1.35rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div>
                                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('QR code') }}</p>
                                                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Point your authenticator app camera here.') }}</p>
                                                    </div>
                                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full" style="background: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                                                        <i class="fa-light fa-qrcode"></i>
                                                    </span>
                                                </div>

                                                <div class="mt-5 flex justify-center">
                                                    <div class="relative aspect-square w-[220px] overflow-hidden rounded-[1.15rem] border bg-white p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.65)]" style="border-color: rgba(var(--theme-accent-rgb), 0.12);">
                                                        @empty($qrCodeSvg)
                                                            <div class="absolute inset-0 flex items-center justify-center">
                                                                <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Loading...') }}</span>
                                                            </div>
                                                        @else
                                                            <div class="flex h-full items-center justify-center">
                                                                {!! $qrCodeSvg !!}
                                                            </div>
                                                        @endempty
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-1">
                                                <div class="rounded-[1.3rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Next step') }}</p>
                                                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-header-text-color);">
                                                        {{ __('After scanning, click continue and enter the 6-digit code generated by your authenticator app.') }}
                                                    </p>

                                                    <div class="mt-5">
                                                        <x-ui.button :disabled="$errors->has('setupData')" class="w-full" wire:click="showVerificationIfNecessary">
                                                            {{ __('Continue to verification') }}
                                                        </x-ui.button>
                                                    </div>
                                                </div>

                                                <div class="rounded-[1.3rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <div>
                                                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Manual setup key') }}</p>
                                                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use this if your authenticator app cannot scan QR codes.') }}</p>
                                                        </div>
                                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full" style="background: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">
                                                            <i class="fa-light fa-key-skeleton"></i>
                                                        </span>
                                                    </div>

                                                    <div
                                                        class="mt-4 flex items-center gap-2"
                                                        x-data="{
                                                            copied: false,
                                                            async copy() {
                                                                try {
                                                                    await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                                                    this.copied = true;
                                                                    setTimeout(() => this.copied = false, 1500);
                                                                } catch (e) {}
                                                            }
                                                        }"
                                                    >
                                                        <div class="flex w-full items-stretch rounded-[1rem] border" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                                            @empty($manualSetupKey)
                                                                <div class="flex w-full items-center justify-center p-3">
                                                                    <span class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Loading...') }}</span>
                                                                </div>
                                                            @else
                                                                <input
                                                                    type="text"
                                                                    readonly
                                                                    value="{{ $manualSetupKey }}"
                                                                    class="w-full bg-transparent p-3 text-sm font-medium outline-none"
                                                                    style="color: var(--theme-header-text-color);"
                                                                />

                                                                <button
                                                                    type="button"
                                                                    @click="copy()"
                                                                    class="border-l px-4 text-sm font-medium transition hover:opacity-80"
                                                                    style="border-color: var(--theme-border-color); color: var(--theme-header-text-color);"
                                                                >
                                                                    <span x-show="!copied">{{ __('Copy') }}</span>
                                                                    <span x-show="copied" style="color: var(--theme-accent);">{{ __('Copied') }}</span>
                                                                </button>
                                                            @endempty
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif
</x-ui.card>
