@php
    use Illuminate\Support\Str;

    $languages = $languages instanceof \Illuminate\Support\Collection ? $languages : collect($languages);

    if ($languages->isEmpty()) {
        $languages = collect([
            (object) [
                'code' => $defaultLocale,
                'name' => strtoupper($defaultLocale),
                'native_name' => strtoupper($defaultLocale),
            ],
        ]);
    }

    $activeLocale = strtolower((string) old('locale', $user->locale ?: $defaultLocale));
    $activeTimezone = (string) old('timezone', $user->timezone ?: config('app.timezone', 'UTC'));
    $activeTimezoneLabel = timezone_label($activeTimezone);
    $selectedLanguage = $languages->first(function ($language) use ($activeLocale) {
        return strtolower((string) data_get($language, 'code')) === $activeLocale;
    });
    $localeLabel = data_get($selectedLanguage, 'native_name')
        ? data_get($selectedLanguage, 'name').' / '.data_get($selectedLanguage, 'native_name')
        : (data_get($selectedLanguage, 'name') ?: strtoupper($activeLocale));
    $profileCompletion = collect([
        filled($user->name),
        filled($user->username),
        filled($user->email),
        filled($user->locale ?: $defaultLocale),
        filled($user->timezone ?: config('app.timezone', 'UTC')),
    ])->filter()->count();
    $profileCompletionPercent = (int) round(($profileCompletion / 5) * 100);
    $memberSince = $user->created_at?->format('M d, Y') ?: __('Unknown');
    $emailStatusLabel = $user->email_verified_at ? __('Verified') : __('Verification pending');
    $emailStatusVariant = $user->email_verified_at ? 'success' : 'neutral';
    $canChangeEmail = (bool) data_get($profilePolicies ?? [], 'can_change_email', false);
    $canChangeUsername = (bool) data_get($profilePolicies ?? [], 'can_change_username', false);
    $hasBillingAccess = $user->hasActivePlan();
@endphp

@component(theme_view('layouts.app', 'app'), ['title' => __('Profile')])
    <div class="space-y-6">
        

        <x-ui.sub-header :compact="true"
            
            :title="__('Profile')"
            :description="__('Manage your profile, language and account security.')"
        >
            <x-slot:actions>
                @if ($hasBillingAccess)
                    <x-ui.button href="{{ route('portal.billing') }}" variant="outline" wire:navigate>
                        {{ __('Billing') }}
                    </x-ui.button>
                    <x-ui.button href="{{ route('portal.invoices') }}" variant="outline" wire:navigate>
                        {{ __('Invoices') }}
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.sub-header>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_360px]">
            <div class="space-y-5">
                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="relative overflow-hidden px-6 py-6 sm:px-7">
                        <div class="absolute inset-0 opacity-100" style="background:
                            radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.18), transparent 36%),
                            radial-gradient(circle at bottom right, rgba(var(--theme-accent-rgb), 0.12), transparent 34%),
                            linear-gradient(135deg, var(--theme-surface-soft) 0%, var(--theme-surface-base) 55%, rgba(var(--theme-accent-rgb), 0.04) 100%);"></div>

                        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div class="flex items-start gap-4">
                                <x-ui.avatar :src="$user->avatar_url" :name="$user->name" size="xl" class="h-20 w-20 text-xl shadow-[0_24px_44px_-28px_rgba(var(--theme-accent-rgb),0.4)]" />

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.badge variant="primary">{{ __('Portal account') }}</x-ui.badge>
                                        <x-ui.badge :variant="$emailStatusVariant">{{ $emailStatusLabel }}</x-ui.badge>
                                    </div>

                                    <h2 class="mt-4 text-[1.9rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">
                                        {{ $user->name }}
                                    </h2>

                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm" style="color: var(--theme-muted-text-color);">
                                        <span>{{ '@'.$user->username }}</span>
                                        <span class="hidden h-1 w-1 rounded-full sm:inline-block" style="background: var(--theme-border-color);"></span>
                                        <span>{{ $user->email }}</span>
                                        <span class="hidden h-1 w-1 rounded-full sm:inline-block" style="background: var(--theme-border-color);"></span>
                                        <span>{{ __('Member since :date', ['date' => $memberSince]) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[360px]">
                                <div class="rounded-[1rem] border px-4 py-3" style="border-color: rgba(var(--theme-accent-rgb), 0.14); background: rgba(var(--theme-accent-rgb), 0.06);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Profile strength') }}</p>
                                    <p class="mt-3 text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $profileCompletionPercent }}%</p>
                                </div>

                                <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Locale') }}</p>
                                    <p class="mt-3 text-base font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ Str::upper($activeLocale) }}</p>
                                </div>

                                <div class="rounded-[1rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Timezone') }}</p>
                                    <p class="mt-3 line-clamp-1 text-base font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                        {{ $activeTimezone }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="border-b px-6 py-5 sm:px-7" style="border-color: var(--theme-border-color);">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Account editor') }}</p>
                                <h3 class="mt-2 text-[1.5rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Update public identity') }}
                                </h3>
                            </div>

                            <p class="max-w-xl text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                {{ __('Keep your name, handle, and locale aligned with how you want this account to appear across the portal experience.') }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" class="space-y-8 px-6 py-6 sm:px-7">
                        @csrf
                        @method('PUT')

                        <section class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Avatar') }}</p>
                                <h4 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Profile photo') }}
                                </h4>
                                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('Upload a photo to use as your avatar.') }}
                                </p>
                            </div>

                            <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)]">
                                <div class="relative overflow-hidden rounded-[1.3rem] border p-4" style="border-color: var(--theme-border-color); background:
                                    radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.12), transparent 34%),
                                    linear-gradient(180deg, var(--theme-surface-soft) 0%, var(--theme-surface-base) 100%);">
                                    <div class="absolute right-3 top-3 inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]" style="background: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                        {{ __('Preview') }}
                                    </div>

                                    <div class="flex min-h-[180px] flex-col items-center justify-center gap-4 pt-5">
                                        <div class="relative">
                                            <span class="absolute inset-[-8px] rounded-full border" style="border-color: rgba(var(--theme-accent-rgb), 0.12);"></span>
                                            <x-ui.avatar :src="$user->avatar_url" :name="$user->name" size="xl" class="relative h-32 w-32 text-2xl border-4 border-white shadow-[0_24px_44px_-28px_rgba(var(--theme-accent-rgb),0.45)]" />
                                        </div>

                                        <div class="text-center">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Current profile photo') }}</p>
                                            <p class="mt-1 text-xs leading-6" style="color: var(--theme-muted-text-color);">
                                                {{ __('Used in your header menu and account surfaces.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <label class="block text-sm font-medium" style="color: var(--theme-header-text-color);">
                                        {{ __('Upload photo') }}
                                    </label>
                                    <input
                                        type="file"
                                        name="avatar"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="block w-full rounded-[var(--theme-input-radius,0.75rem)] border px-4 py-3 text-sm font-medium file:mr-4 file:rounded-[0.75rem] file:border-0 file:bg-[rgba(var(--theme-accent-rgb),0.1)] file:px-4 file:py-2 file:font-semibold file:text-[var(--theme-accent)]"
                                        style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                                    >
                                    @if ($errors->first('avatar'))
                                        <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('avatar') }}</p>
                                    @else
                                        <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                            {{ __('PNG, JPG, or WEBP up to 2 MB. The new photo appears in your header menu and profile summary after save.') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Identity') }}</p>
                                <h4 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                    {{ __('How your account is recognized') }}
                                </h4>
                                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('This is the basic information shown on your account.') }}
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <x-ui.input
                                    name="name"
                                    :label="__('Display name')"
                                    :value="old('name', $user->name)"
                                    :error="$errors->first('name')"
                                    :help="__('Shown in the header menu and account views.')"
                                    required
                                    autofocus
                                />
                                <x-ui.input
                                    name="username"
                                    :label="__('Username')"
                                    :value="old('username', $user->username)"
                                    :error="$errors->first('username')"
                                    :help="$canChangeUsername
                                        ? __('Used as your public handle.')
                                        : __('This username is currently locked for changes on your account.')"
                                    required
                                    :disabled="! $canChangeUsername"
                                />
                            </div>
                        </section>

                        <section class="grid gap-6 border-t pt-8 lg:grid-cols-[220px_minmax(0,1fr)]" style="border-color: var(--theme-border-color);">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Communication') }}</p>
                                <h4 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Email, language, and timezone') }}
                                </h4>
                                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('Update your email, language, and timezone here.') }}
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <x-ui.input
                                        name="email"
                                        type="email"
                                        :label="__('Email')"
                                        :value="old('email', $user->email)"
                                        :error="$errors->first('email')"
                                        :help="$canChangeEmail
                                            ? __('Billing receipts and account notices are sent here.')
                                            : __('This email address is currently locked for changes on your account.')"
                                        required
                                        :disabled="! $canChangeEmail"
                                    />
                                </div>

                                <x-ui.select
                                    name="locale"
                                    :label="__('Preferred language')"
                                    :error="$errors->first('locale')"
                                    :help="__('Used as the default locale for your portal experience.')"
                                >
                                    <option value="">{{ __('Select language') }}</option>
                                    @foreach ($languages as $language)
                                        @php
                                            $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                                            $name = data_get($language, 'name', strtoupper($code));
                                            $nativeName = data_get($language, 'native_name');
                                        @endphp
                                        <option value="{{ $code }}" @selected((string) old('locale', $user->locale) === $code)>
                                            {{ $nativeName ? $name.' / '.$nativeName.' ('.$code.')' : $name.' ('.$code.')' }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Selected locale') }}</p>
                                    <p class="mt-3 text-base font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                        {{ $localeLabel }}
                                    </p>
                                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                        {{ __('The account will default to this language after save.') }}
                                    </p>
                                </div>

                                <x-ui.select
                                    name="timezone"
                                    :label="__('Timezone')"
                                    :error="$errors->first('timezone')"
                                    :help="__('Used for your personal scheduling and portal timestamps.')"
                                >
                                    <option value="">{{ __('Select timezone') }}</option>
                                    @foreach (($timezoneOptions ?? []) as $timezone)
                                        <option value="{{ $timezone['value'] }}" @selected((string) old('timezone', $user->timezone) === $timezone['value'])>
                                            {{ $timezone['label'] }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Selected timezone') }}</p>
                                    <p class="mt-3 text-base font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                        {{ $activeTimezoneLabel }}
                                    </p>
                                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                        {{ __('Scheduling and account timestamps can use this timezone.') }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-6" style="border-color: var(--theme-border-color);">
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">
                                {{ __('Profile updates are applied only to the account currently signed in.') }}
                            </p>

                            <div class="flex flex-wrap items-center gap-3">
                                @if ($hasBillingAccess)
                                    <x-ui.button href="{{ route('portal.invoices') }}" variant="outline" wire:navigate>
                                        {{ __('Review invoices') }}
                                    </x-ui.button>
                                @endif
                                <x-ui.button type="submit">
                                    {{ __('Save profile') }}
                                </x-ui.button>
                            </div>
                        </div>
                    </form>
                </x-ui.card>

                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="border-b px-6 py-5 sm:px-7" style="border-color: var(--theme-border-color);">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Security') }}</p>
                                <h3 class="mt-2 text-[1.5rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Change password') }}
                                </h3>
                            </div>

                            <p class="max-w-xl text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                {{ __('Use your current password to confirm the change, then set a fresh password that meets the workspace security policy.') }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('portal.profile.password.update') }}" class="space-y-8 px-6 py-6 sm:px-7">
                        @csrf
                        @method('PUT')

                        <section class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Verification') }}</p>
                                <h4 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Confirm account ownership') }}
                                </h4>
                                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('Enter the password currently tied to this account before setting a new one.') }}
                                </p>
                            </div>

                            <div class="grid gap-5">
                                <x-ui.input
                                    name="current_password"
                                    type="password"
                                    :label="__('Current password')"
                                    :error="$errors->first('current_password')"
                                    :help="__('Required to authorize this password change.')"
                                    autocomplete="current-password"
                                    required
                                />
                            </div>
                        </section>

                        <section class="grid gap-6 border-t pt-8 lg:grid-cols-[220px_minmax(0,1fr)]" style="border-color: var(--theme-border-color);">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('New secret') }}</p>
                                <h4 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                    {{ __('Set a stronger password') }}
                                </h4>
                                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('Use a unique password you do not reuse elsewhere. Confirmation must match exactly.') }}
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <x-ui.input
                                    name="password"
                                    type="password"
                                    :label="__('New password')"
                                    :error="$errors->first('password')"
                                    :help="__('Must satisfy the default password policy.')"
                                    autocomplete="new-password"
                                    required
                                />

                                <x-ui.input
                                    name="password_confirmation"
                                    type="password"
                                    :label="__('Confirm new password')"
                                    :error="$errors->first('password_confirmation')"
                                    :help="__('Repeat the new password to confirm.')"
                                    autocomplete="new-password"
                                    required
                                />
                            </div>
                        </section>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-6" style="border-color: var(--theme-border-color);">
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">
                                {{ __('After saving, the new password will apply immediately to this account.') }}
                            </p>

                            <x-ui.button type="submit">
                                {{ __('Update password') }}
                            </x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            <div class="space-y-5">
                @livewire(\Modules\AppProfile\Livewire\TwoFactorCard::class)

                <x-ui.card class="space-y-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Profile pulse') }}</p>
                        <h3 class="mt-2 text-[1.25rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                            {{ __('Account health snapshot') }}
                        </h3>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Email status') }}</p>
                                    <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $user->email }}</p>
                                </div>
                                <x-ui.badge :variant="$emailStatusVariant">{{ $emailStatusLabel }}</x-ui.badge>
                            </div>
                        </div>

                        <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Portal plan') }}</p>
                            <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $user->plan?->name ?: __('No plan assigned') }}</p>
                        </div>

                        <div class="rounded-[1rem] border p-4" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Member since') }}</p>
                            <p class="mt-2 text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $memberSince }}</p>
                        </div>
                    </div>
                </x-ui.card>

                @if ($hasBillingAccess)
                    <x-ui.card class="space-y-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Quick links') }}</p>
                            <h3 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                {{ __('Billing and account history') }}
                            </h3>
                        </div>

                        <div class="grid gap-3">
                            <a href="{{ route('portal.billing') }}" wire:navigate class="rounded-[1rem] border p-4 transition hover:-translate-y-px hover:shadow-[0_18px_32px_-26px_rgba(15,23,42,0.26)]" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Billing center') }}</p>
                                        <p class="mt-1 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                            {{ __('Review subscription state, plan details, and recent charges.') }}
                                        </p>
                                    </div>
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                                        <i class="fa-light fa-credit-card"></i>
                                    </span>
                                </div>
                            </a>

                            <a href="{{ route('portal.invoices') }}" wire:navigate class="rounded-[1rem] border p-4 transition hover:-translate-y-px hover:shadow-[0_18px_32px_-26px_rgba(15,23,42,0.26)]" style="border-color: var(--theme-border-color); background: var(--theme-surface-base);">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Invoices archive') }}</p>
                                        <p class="mt-1 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                            {{ __('Access payment history and download invoice PDFs when needed.') }}
                                        </p>
                                    </div>
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                                        <i class="fa-light fa-file-invoice"></i>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </x-ui.card>
                @endif

                <x-ui.card class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Guidance') }}</p>
                    <h3 class="text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                        {{ __('Keep this profile consistent') }}
                    </h3>
                    <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                        {{ __('Use the same email you expect to receive billing notifications on, and keep your locale aligned with the language you want the portal to default to after sign in.') }}
                    </p>
                </x-ui.card>
            </div>
        </section>
    </div>
@endcomponent
