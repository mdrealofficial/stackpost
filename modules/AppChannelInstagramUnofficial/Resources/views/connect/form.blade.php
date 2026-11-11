@component(theme_view('layouts.app', 'app'), ['title' => $isReconnect ? __('Reconnect Instagram Unofficial Profile') : __('Connect Instagram Unofficial Profile')])
    <div class="px-4 py-4">
        <div class="mx-auto max-w-3xl">
            <div class="mb-5">
                <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-accent);">
                    <i class="fa-brands fa-instagram"></i>
                    <span>{{ __('Instagram Unofficial') }}</span>
                </div>
                <h1 class="mt-2 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                    {{ $isReconnect ? __('Reconnect Instagram profile') : __('Connect Instagram profile') }}
                </h1>
                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                    {{ __('This flow signs in directly with Instagram credentials and may require two-factor verification. Use it only when Meta Graph API is not suitable for the target account.') }}
                </p>
            </div>

            <form id="ig-unofficial-form" method="POST" action="{{ route('portal.channels.instagram-unofficial.process', ['reconnect' => $isReconnect, 'account' => $account?->id]) }}" class="overflow-hidden rounded-[1.3rem] border shadow-[0_28px_70px_-36px_rgba(15,23,42,0.36)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                @csrf

                <div class="grid gap-5 px-5 py-5">
                    <div id="ig-unofficial-alert" class="hidden rounded-2xl border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);"></div>

                    <x-ui.input
                        name="ig_username"
                        :label="__('Instagram username')"
                        :value="old('ig_username', $account?->username)"
                        :placeholder="__('yourusername')"
                    />

                    <x-ui.input
                        name="ig_password"
                        type="password"
                        :label="__('Instagram password')"
                        :placeholder="__('Enter the Instagram password')"
                    />

                    <div id="ig-2fa-box" class="hidden">
                        <x-ui.input
                            name="ig_verification_code"
                            :label="__('Two-factor code')"
                            :placeholder="__('Enter the verification code')"
                        />
                    </div>

                    <div id="ig-security-box" class="hidden">
                        <x-ui.input
                            name="ig_security_code"
                            :label="__('Security code')"
                            :placeholder="__('Enter the security code')"
                        />
                    </div>

                    <input type="hidden" name="ig_options" id="ig_options" value="">
                    <input type="hidden" name="ig_type" id="ig_type" value="1">
                </div>

                <div class="border-t px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                            {{ __('If Instagram prompts for 2FA, this form will continue in-place without leaving the screen.') }}
                        </p>

                        <div class="flex items-center gap-3">
                            <x-ui.button type="button" variant="outline" href="{{ route('portal.channels', ['provider' => 'instagram_unofficial']) }}">
                                {{ __('Cancel') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" id="ig-submit-btn">
                                {{ $isReconnect ? __('Reconnect profile') : __('Connect profile') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('ig-unofficial-form');
                if (!form) return;

                const alertBox = document.getElementById('ig-unofficial-alert');
                const twoFaBox = document.getElementById('ig-2fa-box');
                const securityBox = document.getElementById('ig-security-box');
                const typeInput = document.getElementById('ig_type');
                const optionsInput = document.getElementById('ig_options');
                const submitButton = document.getElementById('ig-submit-btn');

                const showAlert = (message, tone = 'warning') => {
                    alertBox.classList.remove('hidden');
                    alertBox.textContent = message;
                    alertBox.style.backgroundColor = tone === 'success' ? 'rgba(34,197,94,0.08)' : 'rgba(245,158,11,0.08)';
                    alertBox.style.color = tone === 'success' ? '#15803d' : '#b45309';
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    submitButton.disabled = true;

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: new FormData(form),
                        });

                        const result = await response.json();

                        if (result.status === 1 && result.redirect) {
                            showAlert(result.message || 'Success', 'success');
                            window.location.href = result.redirect;
                            return;
                        }

                        showAlert(result.message || 'Instagram login failed.');

                        if (result.options) {
                            optionsInput.value = JSON.stringify(result.options);
                        }

                        if (result.type === '2FA') {
                            twoFaBox.classList.remove('hidden');
                            securityBox.classList.add('hidden');
                            typeInput.value = '2';
                        } else if (result.type === 'challenge') {
                            securityBox.classList.remove('hidden');
                            twoFaBox.classList.add('hidden');
                            typeInput.value = '3';
                        } else {
                            typeInput.value = '1';
                        }
                    } catch (error) {
                        showAlert('Instagram login failed. Please try again.');
                    } finally {
                        submitButton.disabled = false;
                    }
                });
            })();
        </script>
    @endpush
@endcomponent
