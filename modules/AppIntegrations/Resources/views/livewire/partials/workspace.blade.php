<div class="space-y-6">
    <section class="overflow-hidden rounded-[1.45rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
        radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.1), transparent 34%),
        linear-gradient(180deg, rgba(var(--theme-border-color-rgb), 0.04), rgba(var(--theme-border-color-rgb), 0.015));
        box-shadow: var(--theme-card-shadow);">
        <div class="border-b px-6 py-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Integration directory') }}</p>
                    <h3 class="text-[1.35rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Choose a provider') }}</h3>
                    <p class="max-w-3xl text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Switch providers here to edit credentials, callback URLs, scopes, and integration toggles without a long sidebar.') }}</p>
                </div>
                <div class="inline-flex items-center gap-2 self-start rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.2em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color); background-color: rgba(var(--theme-surface-overlay-rgb, var(--theme-border-color-rgb)), 0.08);">
                    <span>{{ count($providers) }}</span>
                    <span>{{ __('providers') }}</span>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-5 lg:p-6">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($providers as $providerKey => $provider)
                    @php($isActive = $activeProvider === $providerKey)
                    <button
                        type="button"
                        wire:click="switchProvider('{{ $providerKey }}')"
                        class="group w-full rounded-[1.15rem] border px-4 py-4 text-left transition"
                        @if ($isActive)
                            style="border-color: rgba(var(--theme-accent-rgb), 0.36); background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.18), rgba(var(--theme-accent-rgb), 0.05)); box-shadow: 0 18px 40px -28px rgba(var(--theme-accent-rgb), 0.55);"
                        @else
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.025);"
                        @endif
                    >
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[1rem] text-base" style="background-color: color-mix(in srgb, {{ $provider['color'] }} 12%, white); color: {{ $provider['color'] }};">
                                <i class="{{ $provider['icon'] }}"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $provider['label'] }}</p>
                                    @if ($isActive)
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]" style="background-color: rgba(var(--theme-accent-rgb), 0.14); color: var(--theme-accent);">{{ __('Active') }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 line-clamp-2 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $provider['description'] }}</p>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <div class="min-w-0">
        <form wire:submit="saveProvider" class="space-y-6">
            <div class="overflow-hidden rounded-[1.45rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base); box-shadow: var(--theme-card-shadow);">
                <div class="border-b px-6 py-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-center gap-4">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-[1.1rem] text-lg" style="background-color: color-mix(in srgb, {{ $currentProvider['color'] ?? 'var(--theme-accent)' }} 12%, white); color: {{ $currentProvider['color'] ?? 'var(--theme-accent)' }};">
                                <i class="{{ $currentProvider['icon'] ?? 'fa-light fa-plug-circle-bolt' }}"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ $currentProvider['label'] ?? __('API configuration') }}</h3>
                                    <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">{{ __('API configuration') }}</span>
                                </div>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $currentProvider['description'] ?? __('Set credentials, callback targets, scopes, and toggles for this provider.') }}</p>
                            </div>
                        </div>
                        <p class="max-w-md text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Set credentials, callback targets, scopes, and toggles for this provider.') }}</p>
                    </div>
                </div>
                <div class="space-y-5 p-6">
                    <div wire:key="integration-provider-settings-{{ $activeProvider }}">
                        @if (! empty($currentProvider['settings_view']))
                            @include($currentProvider['settings_view'], [
                                'providerState' => $providerState,
                                'statusOptions' => $statusOptions,
                                'currentProvider' => $currentProvider,
                            ])
                        @else
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ($currentProvider['fields'] as $field)
                                    @if (($field['type'] ?? 'text') === 'toggle')
                                        <div class="rounded-[1rem] border p-4 lg:col-span-2" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __($field['label']) }}</p>
                                            <div class="mt-3 flex flex-wrap gap-6">
                                                @foreach ($statusOptions as $option)
                                                    <label class="inline-flex items-center gap-3 text-sm font-medium" style="color: var(--theme-header-text-color);">
                                                        <input
                                                            type="radio"
                                                            wire:model.live="providerState.{{ $field['key'] }}"
                                                            value="{{ $option['value'] }}"
                                                            class="h-4 w-4 border-slate-300 text-[var(--theme-accent)] focus:ring-[var(--theme-accent)]"
                                                        >
                                                        <span>{{ __($option['label']) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('providerState.'.$field['key'])
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @elseif (($field['type'] ?? 'text') === 'select')
                                        <div class="space-y-2">
                                            <label class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __($field['label']) }}</label>
                                            <select
                                                wire:model="providerState.{{ $field['key'] }}"
                                                class="w-full rounded-[1rem] border px-4 py-3 text-sm outline-none transition"
                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"
                                            >
                                                @foreach (($field['options'] ?? []) as $value => $label)
                                                    <option value="{{ $value }}">{{ __($label) }}</option>
                                                @endforeach
                                            </select>
                                            @error('providerState.'.$field['key'])
                                                <p class="text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @elseif (($field['type'] ?? 'text') === 'textarea')
                                        <div class="lg:col-span-2">
                                            <div class="space-y-2">
                                                <label class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __($field['label']) }}</label>
                                                <textarea
                                                    wire:model="providerState.{{ $field['key'] }}"
                                                    rows="5"
                                                    class="w-full rounded-[1rem] border px-4 py-3 text-sm outline-none transition"
                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"
                                                >{{ $providerState[$field['key']] ?? '' }}</textarea>
                                                @error('providerState.'.$field['key'])
                                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    @else
                                        <div class="space-y-2">
                                            <label class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __($field['label']) }}</label>
                                            <input
                                                wire:model="providerState.{{ $field['key'] }}"
                                                type="{{ ($field['type'] ?? 'text') === 'secret' ? 'password' : 'text' }}"
                                                @if (! empty($field['readonly'])) readonly @endif
                                                class="w-full rounded-[1rem] border px-4 py-3 text-sm outline-none transition"
                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"
                                            >
                                            @error('providerState.'.$field['key'])
                                                <p class="text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-4 border-t pt-5 lg:flex-row lg:items-center lg:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <div class="space-y-1">
                            <x-action-message class="text-emerald-600 dark:text-emerald-400" on="integration-config-saved">
                                {{ __('Integration settings saved.') }}
                            </x-action-message>
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('This page only stores provider API settings. Channel accounts themselves are managed in the user dashboard.') }}</p>
                        </div>
                        <x-ui.button type="submit">{{ __('Save configuration') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
