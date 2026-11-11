<section class="w-full space-y-6">
    <x-ui.card
        class="overflow-hidden border-none shadow-[0_38px_100px_-58px_rgba(15,23,42,0.4)]"
        style="background:
            radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.18), transparent 24%),
            linear-gradient(135deg, color-mix(in srgb, var(--theme-surface-base) 80%, #0f172a 20%) 0%, var(--theme-surface-base) 54%, color-mix(in srgb, var(--theme-surface-base) 90%, var(--theme-header-text-color) 10%) 100%);
        "
    >
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_21rem]">
            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-[1.45rem] text-2xl shadow-[0_24px_44px_-28px_rgba(var(--theme-accent-rgb),0.48)]" style="background-color: rgba(var(--theme-accent-rgb), 0.14); color: var(--theme-accent);">
                        <i class="fa-light fa-plug-circle-bolt"></i>
                    </span>

                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Backend workspace') }}</p>
                        <h1 class="mt-2 text-[2.1rem] font-semibold tracking-[-0.06em]" style="color: var(--theme-header-text-color);">{{ __('Integration Command Center') }}</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-7" style="color: var(--theme-muted-text-color);">
                            {{ __('Standardize provider credentials, callback endpoints, and readiness rules before operators begin connecting channels in the workspace dashboard.') }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-[1.2rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Live providers') }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($liveProviders) }}</p>
                    </div>

                    <div class="rounded-[1.2rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Configured') }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($configuredProviders) }}/{{ number_format(count($providers)) }}</p>
                    </div>

                    <div class="rounded-[1.2rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Disabled') }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($disabledProviders) }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-4 rounded-[1.5rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.72);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Current provider') }}</p>
                @if ($currentProvider)
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-[1rem] text-lg" style="background-color: color-mix(in srgb, {{ $currentProvider['color'] }} 14%, white); color: {{ $currentProvider['color'] }};">
                            <i class="{{ $currentProvider['icon'] }}"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $currentProvider['label'] }}</h3>
                            <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $currentProvider['description'] }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.6); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('State') }}</p>
                            <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">
                                {{ $currentProviderState && $currentProviderState['ready'] ? __('Ready') : (($currentProviderState && $currentProviderState['enabled']) ? __('Config gap') : __('Disabled')) }}
                            </p>
                        </div>

                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.6); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Fields') }}</p>
                            <p class="mt-2 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ count($currentProvider['fields']) }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-ui.card>

    @if (blank($providers))
        <x-ui.section-card
            :title="__('No channel modules registered')"
            :description="__('App Channels starts empty. Social providers only appear here after a module registers an integration item.')"
            body-class="p-6"
        >
            <div class="rounded-[1.35rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.07), rgba(var(--theme-border-color-rgb), 0.03));">
                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    {{ __('Register one or more AppChannel modules to expose provider credentials here. Until then, this workspace intentionally stays empty.') }}
                </p>
            </div>
        </x-ui.section-card>
    @else
        <div class="grid gap-6 xl:grid-cols-[19rem_minmax(0,1fr)]">
            <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
                <x-ui.section-card
                    :title="__('Provider directory')"
                    :description="__('Move through providers the same way an ops team audits integrations.')"
                    body-class="space-y-3 p-4"
                >
                    @foreach ($providers as $providerKey => $provider)
                        @php
                            $isActive = $activeProvider === $providerKey;
                            $state = $providerStates[$providerKey] ?? ['ready' => false, 'enabled' => false, 'configured' => false];
                        @endphp
                        <button
                            type="button"
                            wire:click="switchProvider('{{ $providerKey }}')"
                            class="w-full rounded-[1.1rem] border px-4 py-4 text-left transition"
                            @if ($isActive)
                                style="border-color: rgba(var(--theme-accent-rgb), 0.35); background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.14), rgba(var(--theme-accent-rgb), 0.04)); box-shadow: 0 16px 34px -24px rgba(var(--theme-accent-rgb), 0.55);"
                            @else
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);"
                            @endif
                        >
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[1rem] text-base" style="background-color: color-mix(in srgb, {{ $provider['color'] }} 12%, white); color: {{ $provider['color'] }};">
                                    <i class="{{ $provider['icon'] }}"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $provider['label'] }}</p>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em]" style="background-color: {{ $state['ready'] ? 'rgba(16,185,129,0.12)' : (($state['enabled'] && ! $state['configured']) ? 'rgba(245,158,11,0.12)' : 'rgba(148,163,184,0.12)') }}; color: {{ $state['ready'] ? '#059669' : (($state['enabled'] && ! $state['configured']) ? '#d97706' : '#64748b') }};">
                                            {{ $state['ready'] ? __('Ready') : (($state['enabled'] && ! $state['configured']) ? __('Gap') : __('Off')) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $provider['description'] }}</p>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </x-ui.section-card>
            </aside>

            <div class="min-w-0 space-y-6">
                <x-ui.section-card body-class="p-0">
                    <div class="overflow-hidden rounded-[calc(var(--theme-card-radius,1rem)-1px)]">
                        <div class="grid gap-0 xl:grid-cols-[minmax(0,1.2fr)_18rem]">
                            <div class="border-b p-6 xl:border-b-0 xl:border-r" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.16), transparent 42%), linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.06), rgba(var(--theme-border-color-rgb), 0.03));">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-[1.3rem] text-xl" style="background-color: color-mix(in srgb, {{ $currentProvider['color'] }} 14%, white); color: {{ $currentProvider['color'] }};">
                                        <i class="{{ $currentProvider['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Integration workspace') }}</p>
                                        <h3 class="mt-1 text-[1.45rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $currentProvider['label'] }}</h3>
                                    </div>
                                </div>
                                <p class="mt-4 max-w-2xl text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $currentProvider['description'] }}</p>

                                <div class="mt-5 flex flex-wrap gap-3 text-sm">
                                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-badge-check text-xs"></i>
                                        {{ count($currentProvider['fields']) }} {{ __('config fields') }}
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-link text-xs"></i>
                                        {{ implode(' / ', collect($currentProvider['account_types'])->map(fn ($type) => ucfirst(str_replace('_', ' ', $type)))->all()) }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid gap-3 p-6 md:grid-cols-3 xl:grid-cols-1" style="background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Availability') }}</p>
                                    <p class="mt-2 text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                                        {{ $currentProviderState && $currentProviderState['ready'] ? __('Ready') : (($currentProviderState && $currentProviderState['enabled']) ? __('Gap') : __('Off')) }}
                                    </p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Operational status for this workspace.') }}</p>
                                </div>
                                <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Categories') }}</p>
                                    <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ implode(', ', $currentProvider['categories']) }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Profile types this provider can host.') }}</p>
                                </div>
                                <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Connection model') }}</p>
                                    <p class="mt-2 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ ucfirst(str_replace('_', ' ', $currentProvider['account_types'][0])) }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Users will manage connected accounts from the channel dashboard.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.section-card>

                <form wire:submit="saveProvider" class="space-y-6">
                    <x-theme.section-card
                        :title="__('Provider configuration')"
                        :description="__('Set credentials, callback targets, permissions, and readiness toggles for this provider.')"
                        body-class="space-y-5 p-6"
                    >
                        @if ($currentProviderState && ! $currentProviderState['ready'])
                            <div class="rounded-[1rem] border p-4" style="border-color: rgba(245,158,11,0.28); background-color: rgba(245,158,11,0.06);">
                                <p class="text-sm font-semibold" style="color: #92400e;">{{ __('Readiness checklist') }}</p>
                                <div class="mt-3 grid gap-2 md:grid-cols-2">
                                    @foreach ($currentProvider['required_fields'] ?? [] as $field)
                                        <div class="flex items-center gap-2 text-sm" style="color: {{ in_array($field, $currentProviderState['missing_fields'] ?? [], true) ? '#b45309' : '#059669' }};">
                                            <i class="fa-light {{ in_array($field, $currentProviderState['missing_fields'] ?? [], true) ? 'fa-circle-dot' : 'fa-circle-check' }}"></i>
                                            <span>{{ str($field)->replace('_', ' ')->headline() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($currentProvider['fields'] as $field)
                                @php
                                    $fieldKey = $field['key'];
                                    $wireKey = "providerState.{$fieldKey}";
                                @endphp

                                @if ($field['type'] === 'toggle')
                                    <div class="rounded-[1rem] border p-4 lg:col-span-2" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                        <x-ui.radio-group
                                            name="{{ $wireKey }}"
                                            wire:model.live="{{ $wireKey }}"
                                            :label="__($field['label'])"
                                            :options="$statusOptions"
                                            :value="$providerState[$fieldKey] ?? ($field['default'] ?? '0')"
                                        />
                                    </div>
                                @elseif ($field['type'] === 'select')
                                    <x-ui.select wire:model="{{ $wireKey }}" :label="__($field['label'])" :error="$errors->first($wireKey)">
                                        @foreach (($field['options'] ?? []) as $value => $label)
                                            <option value="{{ $value }}">{{ __($label) }}</option>
                                        @endforeach
                                    </x-ui.select>
                                @elseif ($field['type'] === 'textarea')
                                    <div class="lg:col-span-2">
                                        <x-ui.textarea wire:model="{{ $wireKey }}" :label="__($field['label'])" :error="$errors->first($wireKey)">{{ $providerState[$fieldKey] ?? '' }}</x-ui.textarea>
                                    </div>
                                @else
                                    <x-ui.input
                                        wire:model="{{ $wireKey }}"
                                        :label="__($field['label'])"
                                        :type="$field['type'] === 'secret' ? 'password' : 'text'"
                                        :error="$errors->first($wireKey)"
                                        :readonly="(bool) ($field['readonly'] ?? false)"
                                    />
                                @endif
                            @endforeach
                        </div>

                        <div class="flex items-center justify-between gap-4 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <div class="space-y-1">
                                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="integration-config-saved">
                                    {{ __('Integration settings saved.') }}
                                </x-action-message>
                                <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Store provider credentials here. Once ready, operators can connect channels from the user dashboard.') }}</p>
                            </div>
                            <x-ui.button type="submit">{{ __('Save configuration') }}</x-ui.button>
                        </div>
                    </x-theme.section-card>
                </form>
            </div>
        </div>
    @endif
</section>
