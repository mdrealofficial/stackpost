<div
    x-data="{
        open: false,
        key: 'localboost-gdpr-consent-v1',
        init() {
            this.open = localStorage.getItem(this.key) !== 'accepted' && localStorage.getItem(this.key) !== 'declined';
        },
        accept() {
            localStorage.setItem(this.key, 'accepted');
            this.open = false;
            window.dispatchEvent(new CustomEvent('gdpr-consent-updated', { detail: { consent: 'accepted' } }));
        },
        decline() {
            localStorage.setItem(this.key, 'declined');
            this.open = false;
            window.dispatchEvent(new CustomEvent('gdpr-consent-updated', { detail: { consent: 'declined' } }));
        }
    }"
    x-cloak
    x-show="open"
    x-transition.opacity
    class="fixed inset-x-0 bottom-0 z-[70] px-4 pb-4 sm:px-6 sm:pb-6"
>
    <div class="localboost-cookie-banner mx-auto max-w-5xl rounded-[1.1rem] border p-4 shadow-[0_24px_80px_-56px_rgba(15,23,42,0.45)] backdrop-blur-xl sm:p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-bg);">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 gap-3">
                <span class="localboost-cookie-icon inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.85rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                    <i class="fa-light fa-cookie-bite"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-extrabold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ __('Cookie Policy') }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6" style="color: var(--theme-muted-text-color);">
                        {{ __('We use cookies to improve your experience, measure traffic, and support analytics features.') }}
                        <a href="{{ route('guest.privacy-policy') }}" class="font-bold hover:opacity-80" style="color: var(--theme-accent);">{{ __('Read our cookie policy') }}</a>
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                <button type="button" x-on:click="decline()" class="localboost-cookie-decline inline-flex h-11 items-center justify-center rounded-[var(--theme-button-radius)] border px-4 text-sm font-bold transition hover:-translate-y-px" style="border-color: var(--theme-border-color); color: var(--theme-header-text-color); background-color: var(--theme-body-bg);">
                    {{ __('Decline') }}
                </button>
                <button type="button" x-on:click="accept()" class="localboost-cookie-accept inline-flex h-11 items-center justify-center rounded-[var(--theme-button-radius)] border px-4 text-sm font-bold text-white shadow-[0_16px_30px_-18px_rgba(var(--theme-accent-rgb),0.48)] transition hover:-translate-y-px" style="border-color: var(--theme-accent); background-color: var(--theme-accent);">
                    {{ __('Allow') }}
                </button>
            </div>
        </div>
    </div>
</div>
