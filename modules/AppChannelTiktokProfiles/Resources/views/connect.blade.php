@component(theme_view('layouts.app', 'app'), ['title' => $isReconnect ? __('Reconnect TikTok Profile') : __('Connect TikTok Profile')])
    <div class="px-4 py-4">
        <div class="mx-auto max-w-3xl">
            <div class="mb-5">
                <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-accent);">
                    <i class="fa-brands fa-tiktok"></i>
                    <span>{{ __('TikTok profile') }}</span>
                </div>
                <h1 class="mt-2 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                    {{ $isReconnect ? __('Reconnect TikTok profile') : __('Connect TikTok profile') }}
                </h1>
                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                    {{ __('Add your TikTok profile details manually so it appears in Channels. You can update token, avatar, and profile metadata here at any time.') }}
                </p>
            </div>

            <form method="POST" action="{{ route('portal.channels.tiktok.profiles.store', ['reconnect' => $isReconnect, 'account' => $account?->id]) }}" class="overflow-hidden rounded-[1.3rem] border shadow-[0_28px_70px_-36px_rgba(15,23,42,0.36)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                @csrf

                <div class="grid gap-5 px-5 py-5 md:grid-cols-2">
                    <x-ui.input
                        name="display_name"
                        :label="__('Display name')"
                        :value="old('display_name', $account?->display_name)"
                        :error="$errors->first('display_name')"
                        :placeholder="__('TikTok Shop Vietnam')"
                    />

                    <x-ui.input
                        name="username"
                        :label="__('Username')"
                        :value="old('username', $account?->username)"
                        :error="$errors->first('username')"
                        :placeholder="__('stackposts')"
                    />

                    <x-ui.input
                        name="external_id"
                        :label="__('Profile ID')"
                        :value="old('external_id', $account?->external_id)"
                        :error="$errors->first('external_id')"
                        :placeholder="__('7123456789012345678')"
                    />

                    <x-ui.input
                        name="profile_url"
                        :label="__('Profile URL')"
                        :value="old('profile_url', $account?->profile_url)"
                        :error="$errors->first('profile_url')"
                        :placeholder="__('https://www.tiktok.com/@stackposts')"
                    />

                    <x-ui.input
                        name="avatar_url"
                        :label="__('Avatar URL')"
                        :value="old('avatar_url', $account?->avatar_url)"
                        :error="$errors->first('avatar_url')"
                        :placeholder="__('https://...')"
                    />

                    <x-ui.input
                        name="access_token"
                        :label="__('Access token')"
                        :value="old('access_token', $account?->access_token)"
                        :error="$errors->first('access_token')"
                        :placeholder="__('Paste your TikTok token if you have one')"
                    />
                </div>

                <div class="border-t px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                            {{ __('Publishing is not enabled for TikTok yet in this module scaffold. This step only adds the profile into Channels.') }}
                        </p>

                        <div class="flex items-center gap-3">
                            <x-ui.button type="button" variant="outline" href="{{ route('portal.channels', ['provider' => 'tiktok']) }}">
                                {{ __('Cancel') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary">
                                {{ $isReconnect ? __('Save changes') : __('Connect profile') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endcomponent
