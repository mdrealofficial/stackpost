@php
    if ($languages->isEmpty()) {
        $languages = collect([
            (object) [
                'code' => $defaultLocale,
                'name' => strtoupper($defaultLocale),
                'native_name' => strtoupper($defaultLocale),
            ],
        ]);
    }

    $avatarPreview = $avatar ? $avatar->temporaryUrl() : $user->avatar_url;
@endphp

<div
    class="mx-auto max-w-[84rem] space-y-5"
    x-data="{
        passwordValue: $wire.entangle('password'),
        passwordConfirmationValue: $wire.entangle('password_confirmation'),
        generatePassword() {
            const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
            const length = 14;
            const values = new Uint32Array(length);

            window.crypto.getRandomValues(values);

            const password = Array.from(values, (value) => alphabet[value % alphabet.length]).join('');

            this.passwordValue = password;
            this.passwordConfirmationValue = password;

            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(password).catch(() => {});
            }
        },
    }"
>
    <x-ui.sub-header
        :eyebrow="__('User administration')"
        :title="$isEditing ? __('Edit user') : __('Create user')"
        :description="$isEditing ? __('Refine identity, access, and plan context for this backend account.') : __('Create a new backend user with a clean identity record and secure access details.')"
    >
        <x-slot:actions>
            @if ($isEditing && $authUser?->canImpersonate() && $user->canBeImpersonatedBy($authUser))
                <form method="POST" action="{{ route('admin-users.impersonate', $user) }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline">{{ __('View as user') }}</x-ui.button>
                </form>
            @endif
            <x-ui.button href="{{ route('admin-users.index') }}" variant="outline" wire:navigate>{{ __('Back to users') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.sub-header>

    <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
        <x-ui.card padding="none">
            <div class="border-b px-5 py-4" style="border-color: var(--theme-border-color);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('User form') }}</p>
                <h2 class="mt-2 text-[1.45rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                    {{ $isEditing ? __('Account details') : __('Create an account') }}
                </h2>
            </div>

            <form id="admin-user-form" wire:submit="save" class="space-y-7 p-5">
                <section class="space-y-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Identity') }}</p>
                        <h3 class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Core profile') }}</h3>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[100px_minmax(0,1fr)]">
                        <div class="flex items-center gap-3 sm:flex-col sm:items-start">
                            <x-ui.avatar :src="$avatarPreview" :name="$form['name'] ?: $user->name" size="xl" class="h-16 w-16 shrink-0 text-xl" />
                            <p class="text-xs" style="color:var(--theme-muted-text-color)">{{ __('Avatar preview') }}</p>
                        </div>

                        <div class="space-y-3">
                            <label for="user-avatar" class="block text-sm font-medium" style="color: var(--theme-header-text-color);">
                                {{ __('Upload avatar') }}
                            </label>
                            <input
                                type="file" id="user-avatar"
                                wire:model="avatar"
                                accept="image/png,image/jpeg,image/webp"
                                class="block w-full rounded-[var(--theme-input-radius,0.75rem)] border px-4 py-3 text-sm font-medium file:mr-4 file:rounded-[0.75rem] file:border-0 file:bg-[rgba(var(--theme-accent-rgb),0.1)] file:px-4 file:py-2 file:font-semibold file:text-[var(--theme-accent)]"
                                style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                            >
                            @error('avatar')
                                <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                            @else
                                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('PNG, JPG, or WEBP up to 2 MB. The new photo appears after save.') }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.input wire:model.defer="form.name" name="name" :label="__('Name')" :error="$errors->first('form.name')" required autofocus />
                        <x-ui.input wire:model.defer="form.username" name="username" :label="__('Username')" :error="$errors->first('form.username')" required />
                        <x-ui.input wire:model.defer="form.email" name="email" type="email" :label="__('Email')" :error="$errors->first('form.email')" required />
                        <x-ui.select wire:model.defer="form.locale" name="locale" :label="__('Locale')" :error="$errors->first('form.locale')">
                            <option value="">{{ __('Select language') }}</option>
                            @foreach ($languages as $language)
                                @php
                                    $code = strtolower((string) data_get($language, 'code', $defaultLocale));
                                    $name = data_get($language, 'name', strtoupper($code));
                                    $nativeName = data_get($language, 'native_name');
                                @endphp
                                <option value="{{ $code }}">
                                    {{ $nativeName ? $name.' / '.$nativeName.' ('.$code.')' : $name.' ('.$code.')' }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select wire:model.defer="form.timezone" name="timezone" :label="__('Timezone')" :error="$errors->first('form.timezone')" class="md:col-span-2">
                            <option value="">{{ __('Select timezone') }}</option>
                            @foreach (($timezoneOptions ?? []) as $timezone)
                                <option value="{{ $timezone['value'] }}">
                                    {{ $timezone['label'] }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </section>

                <section class="space-y-4 border-t pt-7" style="border-color: var(--theme-border-color);">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Access') }}</p>
                        <h3 class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Role mapping and admin scope') }}</h3>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2" x-data="{ isSuperAdmin: $wire.entangle('form.is_super_admin').live }">
                        @if (! $isEditing)
                            <x-ui.password-input
                                wire:model.defer="password"
                                name="password"
                                x-model="passwordValue"
                                :label="__('Password')"
                                :error="$errors->first('password')"
                                :placeholder="__('Use at least 8 characters')"
                                required
                            >
                                <x-slot:action>
                                    <x-ui.button type="button" variant="outline" size="sm" x-on:click="generatePassword()">
                                        {{ __('Generate password') }}
                                    </x-ui.button>
                                </x-slot:action>
                            </x-ui.password-input>

                            <x-ui.password-input
                                wire:model.defer="password_confirmation"
                                name="password_confirmation"
                                x-model="passwordConfirmationValue"
                                :label="__('Confirm password')"
                                :error="$errors->first('password_confirmation')"
                                :placeholder="__('Repeat the password')"
                                required
                            />
                        @endif

                        <div class="md:col-span-2">
                            <x-ui.field
                                :label="__('Admin access')"
                                :help="__('Use super admin only for accounts that should bypass role-based restrictions.')"
                                :error="$errors->first('form.is_super_admin')"
                            >
                                <x-ui.checkbox
                                    wire:model.defer="form.is_super_admin"
                                    name="is_super_admin"
                                    value="1"
                                    :label="__('Is super admin')"
                                    :description="__('Grants full access to the admin workspace regardless of role permissions.')"
                                />
                            </x-ui.field>
                        </div>

                        <div x-show="!isSuperAdmin" x-cloak class="md:col-span-2">
                            <x-ui.select wire:model.defer="form.role_id" name="role_id" :label="__('User role')" :error="$errors->first('form.role_id')">
                                <option value="">{{ __('No role assigned') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    </div>
                </section>

                <section class="space-y-4 border-t pt-7" style="border-color: var(--theme-border-color);">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Plan context') }}</p>
                        <h3 class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Billing and feature coverage') }}</h3>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.select wire:model.defer="form.plan_id" name="plan_id" :label="__('Plan')" :error="$errors->first('form.plan_id')">
                            <option value="">{{ __('No plan assigned') }}</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </x-ui.select>

                        <div>
                            <x-ui.date-picker
                                wire:model.defer="form.plan_expires_at"
                                name="plan_expires_at"
                                :label="__('Plan expiry date')"
                                :value="$form['plan_expires_at']"
                                :error="$errors->first('form.plan_expires_at')"
                                :placeholder="__('Select expiry date')"
                            />
                            <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Leave this empty to keep the plan unlimited.') }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-5" style="border-color: var(--theme-border-color);">
                    <div class="text-sm" style="color: var(--theme-muted-text-color);">
                        {{ $isEditing ? __('You are updating an existing backend user record.') : __('This account becomes available immediately after creation.') }}
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button href="{{ route('admin-users.index') }}" variant="outline" wire:navigate>{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit">{{ $isEditing ? __('Save changes') : __('Create user') }}</x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>

        <div class="grid content-start gap-5">
            @if ($isEditing)
                <x-ui.card>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Account security') }}</p>
                    <h3 class="mt-3 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Change new password') }}</h3>
                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Leave both fields empty if you do not want to rotate this user password now.') }}</p>

                    <div class="mt-5 space-y-4">
                        <x-ui.password-input
                            wire:model.defer="password"
                            name="password_edit"
                            x-model="passwordValue"
                            :label="__('New password')"
                            :error="$errors->first('password')"
                            :placeholder="__('Use at least 8 characters')"
                        >
                            <x-slot:action>
                                <x-ui.button type="button" variant="outline" size="sm" x-on:click="generatePassword()">
                                    {{ __('Generate password') }}
                                </x-ui.button>
                            </x-slot:action>
                        </x-ui.password-input>

                        <x-ui.password-input
                            wire:model.defer="password_confirmation"
                            name="password_confirmation_edit"
                            x-model="passwordConfirmationValue"
                            :label="__('Confirm new password')"
                            :error="$errors->first('password_confirmation')"
                            :placeholder="__('Repeat the new password')"
                        />
                    </div>

                    <div class="mt-5 flex justify-end">
                        <x-ui.button type="button" wire:click="save">
                            {{ __('Save changes') }}
                        </x-ui.button>
                    </div>
                </x-ui.card>
            @else
                <x-ui.card>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Readiness') }}</p>
                    <div class="mt-4 grid gap-3">
                        @foreach ([
                            ['label' => __('Name provided'), 'ready' => filled($form['name'] ?? null)],
                            ['label' => __('Username provided'), 'ready' => filled($form['username'] ?? null)],
                            ['label' => __('Email provided'), 'ready' => filled($form['email'] ?? null)],
                            ['label' => __('Role assigned'), 'ready' => filled($form['role_id'] ?? null)],
                            ['label' => __('Super admin enabled'), 'ready' => (bool) ($form['is_super_admin'] ?? false)],
                            ['label' => __('Plan attached'), 'ready' => filled($form['plan_id'] ?? null)],
                        ] as $item)
                            <div class="flex items-center justify-between rounded-[0.9rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
                                <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $item['label'] }}</p>
                                <x-ui.badge :variant="$item['ready'] ? 'success' : 'neutral'">{{ $item['ready'] ? __('Ready') : __('Open') }}</x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            @if ($isEditing)
                <x-ui.card>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Danger zone') }}</p>
                    <h3 class="mt-3 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Delete this user') }}</h3>
                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Remove this user record if it is no longer needed in the backend.') }}</p>

                    <x-ui.dialog :title="__('Delete this user?')" :description="__('This permanently removes the account from the system. This action cannot be undone.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger" class="mt-5">{{ __('Delete user') }}</x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="deleteUser">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </x-ui.card>
            @endif
        </div>
    </section>
</div>
