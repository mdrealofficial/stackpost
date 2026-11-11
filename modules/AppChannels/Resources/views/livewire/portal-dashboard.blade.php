<div class="channels-page" x-data="{ viewMode: localStorage.getItem('stackposts-channels-view') === 'list' ? 'list' : 'grid', filtersOpen: false, setView(mode) { this.viewMode = mode; localStorage.setItem('stackposts-channels-view', mode); } }" x-bind:class="{ 'channels-list': viewMode === 'list' }">
    @include('appchannels::partials.channel-workspace-styles')
    <header class="channels-heading">
        <div class="channels-heading-copy">
            <h1>{{ __('Channels') }}</h1>
            <p>{{ __('Manage your social accounts and keep your workspace connected.') }}</p>
        </div>
        <div class="channels-heading-actions">
            @if(Route::has('portal.groups') && auth()->user()?->canUsePlanFeature('groups'))
                <x-ui.button :href="route('portal.groups')" variant="outline">{{ __('Manage groups') }}</x-ui.button>
            @endif
                <x-ui.modal width="xl" body-class="channels-picker-body" :title="__('Connect a channel')" :description="__('Choose a network and the account type you want to connect.')">
                    <x-slot:trigger>
                        @if ($canCreateChannels)
                            <x-ui.button type="button">
                                {{ __('Add channels') }}
                            </x-ui.button>
                        @else
                            <x-ui.button type="button" disabled>
                                {{ __('Add channels') }}
                            </x-ui.button>
                        @endif
                    </x-slot:trigger>

                    @php
                        $pickerGroups = [
                            ['ready' => true, 'title' => __('Ready to connect')],
                            ['ready' => false, 'title' => __('Setup required')],
                        ];
                        $pickerSearchTerms = collect($providerCards)->mapWithKeys(fn ($provider) => [
                            $provider['key'] => $provider['label'].' '.$provider['key'].' '.collect($provider['capabilities'])->pluck('label')->implode(' '),
                        ])->all();
                    @endphp
                    <div class="channels-picker" x-data="{
                        query: '',
                        networks: @js($pickerSearchTerms),
                        matches(value) { return value.toLocaleLowerCase().includes(this.query.trim().toLocaleLowerCase()); },
                        count(keys) { return keys.filter(key => this.matches(this.networks[key])).length; }
                    }" x-init="$watch('open', value => { if (value) { $nextTick(() => $refs.networkSearch.focus()); } else { query = ''; } })">
                        <div class="channels-picker-toolbar">
                            <div class="channels-picker-search">
                                <x-ui.icon-input icon="fa-light fa-magnifying-glass" x-ref="networkSearch" type="search" x-model="query" :aria-label="__('Search networks')" :placeholder="__('Search networks...')" autocomplete="off" />
                            </div>
                            <span class="channels-picker-result" role="status" aria-live="polite"><strong x-text="count(Object.keys(networks))">{{ count($providerCards) }}</strong> {{ __('networks') }}</span>
                        </div>
                        @if (config('app.demo_mode') && collect($providerCards)->contains(fn ($provider) => (bool) ($provider['has_addon_capabilities'] ?? false)))
                            <p class="channels-picker-addon-note"><span>{{ __('Addon') }}</span>{{ __('Some connection types are provided by separately installed add-ons.') }}</p>
                        @endif
                        <div class="channels-picker-scroll">
                            @foreach ($pickerGroups as $group)
                                @php
                                    $groupProviders = collect($providerCards)->filter(fn ($provider) => (bool)$provider['is_ready'] === $group['ready']);
                                    $groupKeys = $groupProviders->pluck('key')->values()->all();
                                @endphp
                                @if ($groupProviders->isNotEmpty())
                                    <section class="channels-picker-group" x-show="count(@js($groupKeys)) > 0" aria-label="{{ $group['title'] }}">
                                        <div class="channels-picker-group-heading"><h4>{{ $group['title'] }}</h4><span x-text="count(@js($groupKeys))">{{ $groupProviders->count() }}</span></div>
                                        @if (!$group['ready'])<p class="channels-picker-help">{{ __('These networks are not ready to connect. Contact support for setup assistance.') }}</p>@endif
                                        <div class="channels-picker-grid">
                                            @foreach ($groupProviders as $provider)
                                                @php
                                                    $providerTone = publishing_provider_tone((string)$provider['key']);
                                                @endphp
                                                <article class="channels-picker-card" data-ready="{{ $provider['is_ready'] ? 'true' : 'false' }}" x-show="matches(networks[@js($provider['key'])])">
                                                    <div class="channels-picker-identity">
                                                        <span class="channels-picker-icon" style="background:{{ $providerTone['surface'] }};color:{{ $providerTone['text'] }};" aria-hidden="true"><i class="{{ $provider['icon'] }}"></i></span>
                                                        <h5>{{ $provider['label'] }}</h5>
                                                    </div>
                                                    <div class="channels-picker-actions">
                                                        @foreach ($provider['capabilities'] as $capability)
                                                            @php
                                                                $accountType = $capability['connect_label'] ?? $capability['label'];
                                                            @endphp
                                                            <x-ui.button type="button" variant="outline" wire:click="createAccount('{{ $capability['key'] }}')" x-on:click="open = false" :disabled="!$provider['is_ready'] || !$canCreateChannels" aria-label="{{ __('Connect :type on :network', ['type' => $accountType, 'network' => $provider['label']]) }}">
                                                                <i class="fa-light fa-plus" aria-hidden="true"></i>{{ __('Connect :type', ['type' => $accountType]) }}@if ($capability['is_addon'] ?? false)<span class="channels-addon-badge">{{ __('Addon') }}</span>@endif
                                                            </x-ui.button>
                                                        @endforeach
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            @endforeach
                            <div class="channels-picker-empty" x-cloak x-show="count(Object.keys(networks)) === 0">
                                <i class="fa-light fa-magnifying-glass" aria-hidden="true"></i>
                                <h4>{{ __('No networks found') }}</h4>
                                <p>{{ __('Try another network name or clear your search.') }}</p>
                                <x-ui.button type="button" variant="outline" x-on:click="query = ''; $refs.networkSearch.focus()">{{ __('Clear search') }}</x-ui.button>
                            </div>
                        </div>
                    </div>
                </x-ui.modal>
        </div>
    </header>
    @php
        $channelMetrics = [
            [
                'label' => __('Total channels'),
                'value' => number_format($accountTotal),
                'description' => __('Across :count networks', ['count' => count($providerCounts)]),
                'icon' => 'fa-light fa-layer-group',
                'tone' => '#0d9488',
                'iconSurface' => 'rgba(13, 148, 136, 0.12)',
                'spark' => 'M2 24 C10 24 12 15 20 19 S28 29 36 19 S45 11 52 17 S58 18 62 12',
            ],
            [
                'label' => __('Enabled'),
                'value' => number_format($activeAccounts),
                'description' => __('Ready to publish from this workspace'),
                'icon' => 'fa-light fa-circle-check',
                'tone' => '#16856f',
                'iconSurface' => 'rgba(22, 133, 111, 0.11)',
                'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13',
            ],
            [
                'label' => __('Paused'),
                'value' => number_format($pausedAccounts),
                'description' => __('Temporarily stopped in this workspace'),
                'icon' => 'fa-light fa-pause',
                'tone' => '#b7791f',
                'iconSurface' => 'rgba(183, 121, 31, 0.12)',
                'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18',
            ],
            [
                'label' => __('Recently added'),
                'value' => number_format($connectedThisMonth),
                'description' => __('Connected in the last 30 days'),
                'icon' => 'fa-light fa-sparkles',
                'tone' => '#7c3aed',
                'iconSurface' => 'rgba(124, 58, 237, 0.10)',
                'spark' => 'M2 25 C10 25 12 15 19 18 S28 28 35 19 S43 10 51 15 S58 19 62 11',
            ],
        ];
    @endphp
    <x-ui.metric-strip
        :items="$channelMetrics"
        columns="grid-cols-2 lg:grid-cols-4"
        gap="gap-5"
        class="mb-8"
        variant="portal"
        :show-progress="false"
        aria-label="{{ __('Channel summary') }}"
    />
    @if ($bannerMessage)
        <x-ui.card
            @class([
                'channels-update-banner' => true,
                'border-amber-200 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-500/10' => $bannerTone === 'warning',
                'border-emerald-200 bg-emerald-50/80 dark:border-emerald-500/20 dark:bg-emerald-500/10' => $bannerTone === 'success',
                'border-rose-200 bg-rose-50/80 dark:border-rose-500/20 dark:bg-rose-500/10' => $bannerTone === 'danger',
            ])
        >
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-header-text-color);">
                    <i class="fa-light {{ $bannerTone === 'success' ? 'fa-circle-check' : ($bannerTone === 'danger' ? 'fa-circle-xmark' : 'fa-circle-exclamation') }}"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                        {{ $bannerTone === 'success' ? __('Channel updated') : ($bannerTone === 'danger' ? __('Channel action failed') : __('Channel attention required')) }}
                    </p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $bannerMessage }}</p>
                </div>
            </div>
        </x-ui.card>
    @endif

    @if (blank($providerCards))
        <x-ui.card>
            <div class="rounded-[1rem] border p-5" style="border-color: var(--theme-divider-border-color, var(--theme-border-color)); background-color: var(--theme-surface-soft);">
                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    {{ __('No networks are available to connect yet. Contact support for help enabling channels in your workspace.') }}
                </p>
            </div>
        </x-ui.card>
    @else
        <div class="min-w-0 max-w-full space-y-8">
            @if ($currentProvider && ! $currentProvider['is_ready'])
                <x-ui.card>
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Provider setup required') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">
                                {{ __('This network is not ready to connect yet.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-warning-color-rgb),0.28); background-color: rgba(var(--theme-warning-color-rgb),0.06);">
                                <p class="text-sm font-semibold" style="color: var(--theme-warning-color);">{{ __('Missing requirements') }}</p>
                                <div class="mt-3 space-y-2">
                                    @forelse ($currentProvider['missing_fields'] as $field)
                                        <div class="flex items-center gap-2 text-sm" style="color: var(--theme-warning-color);">
                                            <i class="fa-light fa-circle-small"></i>
                                            <span>{{ str($field)->replace('_', ' ')->headline() }}</span>
                                        </div>
                                    @empty
                                        <p class="text-sm" style="color: var(--theme-warning-color);">{{ __('The provider is disabled in API Integration.') }}</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('What to do') }}</p>
                                <p class="mt-3 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                                    {{ __('Complete the API Integration credentials first, then return here to connect channels for this provider.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @endif

            @if ($showAccountForm && $selectedCapabilityDefinition)
                @php
                    $selectedCapabilityTone = publishing_provider_tone((string) data_get($selectedCapabilityDefinition, 'provider_key', data_get($selectedCapabilityDefinition, 'key', '')));
                @endphp
                <x-theme.section-card
                    :title="__('Channel editor')"
                    :description="__('Capture identity, connection metadata, and internal ops notes for the selected capability.')"
                    body-class="space-y-6 p-6"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-[1rem] text-lg" style="background-color: {{ $selectedCapabilityTone['surface'] }}; color: {{ $selectedCapabilityTone['text'] }};">
                                <i class="{{ data_get($selectedCapabilityDefinition, 'icon', 'fa-light fa-share-nodes') }}"></i>
                            </span>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Selected capability') }}</p>
                                <h4 class="mt-1 text-lg font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ data_get($selectedCapabilityDefinition, 'title', __('Channel')) }}</h4>
                                <p class="channel-handle mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ data_get($selectedCapabilityDefinition, 'description', __('Store channel metadata and connection details for this destination.')) }}</p>
                            </div>
                        </div>

                        <button type="button" wire:click="cancelAccountForm" class="rounded-[0.9rem] border px-4 py-2 text-sm font-semibold transition" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">
                            {{ __('Close editor') }}
                        </button>
                    </div>

                    <form wire:submit="saveAccount" class="space-y-5">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <x-ui.input wire:model="accountForm.display_name" :label="__('Display name')" :error="$errors->first('accountForm.display_name')" />
                            <x-ui.input wire:model="accountForm.username" :label="__('Username / handle')" :error="$errors->first('accountForm.username')" />
                            <x-ui.input wire:model="accountForm.external_id" :label="__('External ID')" :error="$errors->first('accountForm.external_id')" />
                            <x-ui.select wire:model="accountForm.category" :label="__('Category')" :error="$errors->first('accountForm.category')">
                                @foreach ($categoryOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.select wire:model="accountForm.account_type" :label="__('Connection type')" :error="$errors->first('accountForm.account_type')">
                                @foreach ($accountTypeOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </x-ui.select>
                            <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                                <x-ui.radio-group name="accountForm.is_active" wire:model.live="accountForm.is_active" :label="__('Status')" :options="$statusOptions" :value="$accountForm['is_active']" />
                            </div>
                            <x-ui.input wire:model="accountForm.profile_url" :label="__('Profile URL')" :error="$errors->first('accountForm.profile_url')" />
                            <x-ui.input wire:model="accountForm.avatar_url" :label="__('Avatar URL')" :error="$errors->first('accountForm.avatar_url')" />
                            <x-ui.input wire:model="accountForm.reconnect_url" :label="__('Reconnect URL')" :error="$errors->first('accountForm.reconnect_url')" />
                            <x-ui.input wire:model="accountForm.scopes" :label="__('Scopes')" :error="$errors->first('accountForm.scopes')" />
                            <div class="lg:col-span-2">
                                <x-ui.textarea wire:model="accountForm.access_token" :label="__('Access token')" :error="$errors->first('accountForm.access_token')">{{ $accountForm['access_token'] }}</x-ui.textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <x-ui.textarea wire:model="accountForm.refresh_token" :label="__('Refresh token')" :error="$errors->first('accountForm.refresh_token')">{{ $accountForm['refresh_token'] }}</x-ui.textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <x-ui.textarea wire:model="accountForm.auth_data_json" :label="__('Auth payload JSON')" :error="$errors->first('accountForm.auth_data_json')">{{ $accountForm['auth_data_json'] }}</x-ui.textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <x-ui.textarea wire:model="accountForm.metadata_json" :label="__('Metadata JSON')" :error="$errors->first('accountForm.metadata_json')">{{ $accountForm['metadata_json'] }}</x-ui.textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <x-ui.textarea wire:model="accountForm.notes" :label="__('Internal notes')" :error="$errors->first('accountForm.notes')">{{ $accountForm['notes'] }}</x-ui.textarea>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t pt-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('This record inherits provider-level credentials from API Integration and stores the account-specific metadata here.') }}</p>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="cancelAccountForm" class="rounded-[0.9rem] border px-4 py-2 text-sm font-semibold transition" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">{{ __('Cancel') }}</button>
                                <x-ui.button type="submit">{{ __('Save channel') }}</x-ui.button>
                            </div>
                        </div>
                    </form>
                </x-theme.section-card>
            @endif

            <section class="min-w-0 max-w-full space-y-5" aria-label="{{ __('Connected channels') }}">
                <div class="channels-collection-heading">
                    <div><h2>{{ __('Connected accounts') }}</h2><p>{{ __('Find an account or select several to manage them together.') }}</p></div>
                    <div class="channels-capacity" aria-label="{{ __('Channel capacity') }}">
                        @if($channelLimit < 0)
                            <strong>{{ __('Unlimited') }}</strong><span>{{ __('channel capacity') }}</span>
                        @elseif($channelLimitPerNetwork)
                            <strong>{{ number_format($channelLimit) }}</strong><span>{{ __('channels per network') }}</span>
                        @else
                            <strong>{{ number_format($channelSlotsUsed) }} <span>/ {{ number_format($channelLimit) }}</span></strong><span>{{ __('channel slots used') }}</span>
                        @endif
                    </div>
                </div>
                <x-ui.card padding="none" class="channels-toolbar">
                    <div class="channels-network-shortcuts" role="group" aria-label="{{ __('Quick network filters') }}">
                        <x-ui.button type="button" variant="ghost" wire:click="$set('activeProvider', '')" :aria-pressed="$activeProvider === '' ? 'true' : 'false'">{{ __('All networks') }}<span>{{ $accountTotal }}</span></x-ui.button>
                        @foreach(collect($providerCards)->filter(fn ($provider) => ($providerCounts[$provider['key']] ?? 0) > 0)->sortByDesc(fn ($provider) => $providerCounts[$provider['key']])->take(5) as $provider)
                            <x-ui.button type="button" variant="ghost" wire:click="$set('activeProvider', '{{ $provider['key'] }}')" :aria-pressed="$activeProvider === $provider['key'] ? 'true' : 'false'"><i class="{{ $provider['icon'] }}" aria-hidden="true"></i>{{ $provider['label'] }}<span>{{ $providerCounts[$provider['key']] }}</span></x-ui.button>
                        @endforeach
                    </div>
                    <div class="channels-toolbar-main">
                        <div class="channels-search">
                            <x-ui.icon-input wire:model.live.debounce.300ms="search" icon="fa-light fa-magnifying-glass" :placeholder="__('Search channels...')" :aria-label="__('Search channels')" />
                        </div>
                        <div class="channels-toolbar-buttons">
                            <x-ui.button type="button" variant="outline" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen.toString()" aria-controls="channels-filters">
                                <i class="fa-light fa-filter" aria-hidden="true"></i>{{ __('Filters') }}
                                @if ($activeProvider !== '' || $statusFilter !== 'all' || $connectionFilter !== 'all')<span class="channels-filter-count">{{ (int)($activeProvider !== '') + (int)($statusFilter !== 'all') + (int)($connectionFilter !== 'all') }}</span>@endif
                            </x-ui.button>
                            <div class="channels-view-toggle" role="group" aria-label="{{ __('Channel layout') }}">
                                <x-ui.button variant="ghost" type="button" x-on:click="setView('grid')" x-bind:aria-pressed="viewMode === 'grid'" aria-label="{{ __('Grid view') }}" title="{{ __('Grid view') }}"><i class="fa-light fa-grid-2" aria-hidden="true"></i></x-ui.button>
                                <x-ui.button variant="ghost" type="button" x-on:click="setView('list')" x-bind:aria-pressed="viewMode === 'list'" aria-label="{{ __('List view') }}" title="{{ __('List view') }}"><i class="fa-light fa-list" aria-hidden="true"></i></x-ui.button>
                            </div>
                        </div>
                    </div>
                    <div id="channels-filters" x-cloak x-show="filtersOpen" class="channels-filter-fields">
                        <x-ui.select wire:model.live="activeProvider" :label="__('Social network')" :aria-label="__('Social network')">
                            <option value="">{{ __('All networks') }}</option>
                            @foreach ($providerCards as $provider)<option value="{{ $provider['key'] }}">{{ $provider['label'] }} ({{ $providerCounts[$provider['key']] ?? 0 }})</option>@endforeach
                        </x-ui.select>
                        <x-ui.select wire:model.live="statusFilter" :label="__('Workspace status')" :aria-label="__('Workspace status')">
                            @foreach ($statusFilterOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
                        </x-ui.select>
                        <x-ui.select wire:model.live="connectionFilter" :label="__('Connection method')" :aria-label="__('Connection method')">
                            <option value="all">{{ __('All methods') }}</option><option value="oauth">{{ __('OAuth') }}</option><option value="manual">{{ __('Manual') }}</option>
                        </x-ui.select>
                        <x-ui.select wire:model.live="sortBy" :label="__('Sort by')" :aria-label="__('Sort by')">
                            @foreach ($sortOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
                        </x-ui.select>
                    </div>
                    <div class="channels-toolbar-bottom">
                    <div class="channels-results-bar">
                        <div class="channels-results" role="status" aria-live="polite">{{ __(':visible of :total channels', ['visible' => $accounts->count(), 'total' => $accountTotal]) }}</div>
                        @if ($search !== '' || $activeProvider !== '' || $statusFilter !== 'all' || $connectionFilter !== 'all')
                            <div class="channels-active-filters">
                                @if ($search !== '')<span>{{ __('Search') }}: {{ $search }}</span>@endif
                                @if ($activeProvider !== '')<span>{{ $currentProvider['label'] ?? $activeProvider }}</span>@endif
                                @if ($statusFilter !== 'all')<span>{{ $statusFilter === 'active' ? __('Enabled') : __('Paused') }}</span>@endif
                                @if ($connectionFilter !== 'all')<span>{{ $connectionFilter === 'oauth' ? __('OAuth') : __('Manual') }}</span>@endif
                                <button type="button" wire:click="clearFilters">{{ __('Clear filters') }}</button>
                            </div>
                        @endif
                    </div>
                    <div class="channels-selection-bar">
                        <span wire:key="channels-select-all-{{ $selectedVisibleCount }}-{{ $accounts->count() }}">
                            <x-ui.checkbox id="channels-select-all" class="channels-select-all" :minimal="true" :checked="$allVisibleSelected" :disabled="$accounts->isEmpty()" wire:click="toggleSelectAllVisible" :aria-label="$allVisibleSelected ? __('Deselect all results') : __('Select all results')" x-data="{}" :x-init="'$el.indeterminate = '.($selectedVisibleCount > 0 && !$allVisibleSelected ? 'true' : 'false')">{{ __('Select all results') }}</x-ui.checkbox>
                        </span>
                        @if ($selectedAccountCount > 0)
                            <span class="channels-selected-count" role="status">{{ __(':count selected', ['count' => $selectedAccountCount]) }}</span>
                            <button type="button" wire:click="clearSelectedAccounts" class="channels-clear-selection">{{ __('Clear selection') }}</button>
                            <x-ui.dropdown-menu align="right" width="auto" panel-class="min-w-[10.5rem]">
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="outline" class="channels-bulk-actions" :disabled="$selectedAccountCount === 0">
                                        <i class="fa-light fa-grid-2"></i>
                                        {{ __('Selected actions') }}
                                    </x-ui.button>
                                </x-slot:trigger>

                                <div class="min-w-[10.5rem] p-1.5">
                                    @if ($canToggleChannelStatus)
                                        <x-ui.dropdown-menu-item type="button" icon="fa-light fa-check" wire:click="bulkActivateSelected">
                                            {{ __('Enable') }}
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item type="button" icon="fa-light fa-pause" wire:click="bulkPauseSelected">
                                            {{ __('Pause') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif

                                    @if ($canDeleteChannels)
                                        <x-ui.dropdown-menu-item type="button" icon="fa-light fa-trash" variant="danger" wire:click="bulkDeleteSelected" wire:confirm="{{ __('Delete selected channels?') }}">
                                            {{ __('Delete') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                </div>
                            </x-ui.dropdown-menu>
                        @endif
                    </div>
                    </div>
                </x-ui.card>

                <div class="hidden">
                    <x-action-message class="text-emerald-600 dark:text-emerald-400" on="portal-account-saved">{{ __('Channel updated.') }}</x-action-message>
                    <x-action-message class="text-emerald-600 dark:text-emerald-400" on="portal-account-deleted">{{ __('Channel removed.') }}</x-action-message>
                </div>

                <div class="channels-accounts">
                    <?php if ($accounts->isEmpty()): ?>
                        <div class="channels-empty">
                            <x-ui.empty :title="$accountTotal === 0 ? __('Connect your first channel') : __('No channels match this view')" :description="$accountTotal === 0 ? __('Choose Add channels to connect a social account to your workspace.') : __('Try another search or clear your filters to see more channels.')" />
                            @if($search !== '' || $statusFilter !== 'all' || $activeProvider !== '' || $connectionFilter !== 'all')
                            <div class="pb-6 text-center"><x-ui.button type="button" wire:click="clearFilters">{{ __('Clear search and filters') }}</x-ui.button></div>
                            @endif
                        </div>
                    <?php else: ?>
                    <?php foreach ($accounts as $account): ?>
                            @php
                                $capability = $capabilityMap[$account->capability_key] ?? [];
                                $provider = $providerRegistry[$account->provider_key] ?? [];
                                $providerColor = data_get($provider, 'color', '#64748b');
                                $providerTone = publishing_provider_tone((string) $account->provider_key);
                                $usesLocalSocialAvatar = str_contains((string) ($account->avatar_url ?? ''), '/media/public/social-accounts/')
                                    || str_contains((string) ($account->avatar_url ?? ''), '/storage/social-accounts/');
                                $avatarFallback = collect(preg_split('/\s+/', trim((string) $account->display_name)) ?: [])
                                    ->filter()
                                    ->map(fn (string $part): string => \Illuminate\Support\Str::of($part)->substr(0, 1)->upper()->toString())
                                    ->take(2)
                                    ->implode('');

                                if ($avatarFallback === '') {
                                    $avatarFallback = \Illuminate\Support\Str::of((string) $account->username)->replace('@', '')->substr(0, 2)->upper()->toString();
                                }

                                if ($avatarFallback === '') {
                                    $avatarFallback = 'TG';
                                }
                            @endphp

                            <x-ui.card padding="none" wire:key="channel-{{ $account->id }}" class="channel-card" data-selected="{{ in_array($account->id, $selectedAccountIds, true) ? 'true' : 'false' }}">
                                <div class="channel-identity">
                                    <span class="channel-avatar" aria-hidden="true">
                                        <span class="channel-avatar-initials">{{ $avatarFallback }}</span>
                                        @if ($account->avatar_url)
                                            <img src="{{ $account->avatar_url }}" alt="" class="channel-avatar-image {{ $usesLocalSocialAvatar ? 'channel-avatar-contained' : '' }}" loading="lazy" decoding="async" referrerpolicy="no-referrer" x-data x-on:error="$el.hidden = true">
                                        @endif
                                        <span class="channel-provider-icon" style="background-color: {{ $providerColor }};"><i class="{{ data_get($provider, 'icon', 'fa-light fa-share-nodes') }}"></i></span>
                                    </span>
                                    <div class="channel-name-block">
                                        <h2 class="channel-name" title="{{ $account->display_name }}">{{ $account->display_name }}</h2>
                                        <p class="channel-username" title="{{ $account->username ? '@'.$account->username : __('No username') }}">{{ $account->username ? '@'.$account->username : __('No username') }}</p>
                                    </div>
                                    <x-ui.checkbox :id="'channel-select-'.$account->id" class="channel-select" :minimal="true" wire:model.live="selectedAccountIds" :value="$account->id" :checked="in_array($account->id, $selectedAccountIds, true)" :aria-label="__('Select :name', ['name' => $account->display_name])" />
                                </div>
                                <div class="channel-meta">
                                    <span class="channel-network" title="{{ data_get($capability, 'title', $account->category ?: __('Channel')) }}">{{ data_get($capability, 'title', $account->category ?: __('Channel')) }}</span>
                                    <span class="channel-status" data-enabled="{{ $account->is_active ? 'true' : 'false' }}" title="{{ __('Workspace setting only. This does not verify the connection to the social network.') }}"><i aria-hidden="true"></i>{{ $account->is_active ? __('Enabled') : __('Paused') }}</span>
                                </div>

                                <div class="channel-actions">
                                    <?php if ($account->profile_url): ?>
                                        <x-ui.button :href="$account->profile_url" variant="ghost" target="_blank" rel="noopener noreferrer" class="channel-profile-link" :aria-label="__('View profile for :name', ['name' => $account->display_name])">{{ __('View profile') }}<i class="fa-light fa-arrow-up-right-from-square" aria-hidden="true"></i></x-ui.button>
                                    <?php endif; ?>



                                    <?php if ($canReconnectChannels || $canToggleChannelStatus || $canDeleteChannels): ?>
                                        <x-ui.dropdown-menu align="right" width="auto">
                                            <x-slot:trigger><x-ui.button type="button" variant="outline" class="channel-menu-button" x-bind:aria-expanded="open.toString()" :aria-label="__('Actions for :name', ['name' => $account->display_name])"><i class="fa-light fa-ellipsis" aria-hidden="true"></i></x-ui.button></x-slot:trigger>
                                                @if ($canReconnectChannels && filled($account->reconnect_url))
                                                    <x-ui.dropdown-menu-item icon="fa-light fa-rotate-right" wire:click="reconnectAccount({{ $account->id }})">{{ __('Reconnect') }}</x-ui.dropdown-menu-item>
                                                @endif
                                                @if ($canToggleChannelStatus)
                                                    <x-ui.dropdown-menu-item :icon="$account->is_active ? 'fa-light fa-pause' : 'fa-light fa-play'" wire:click="toggleAccountStatus({{ $account->id }})">{{ $account->is_active ? __('Pause') : __('Resume') }}</x-ui.dropdown-menu-item>
                                                @endif
                                                <?php if ($canDeleteChannels): ?>
                                                    <x-ui.dialog
                                                        width="sm"
                                                        dismissible
                                                        :title="__('Remove this channel?')"
                                                        :description="__('This permanently removes the selected channel from the workspace.')"
                                                    >
                                                        <x-slot:trigger>
                                                            <button
                                                                type="button"
                                                                x-on:click="open = false"
                                                                class="flex w-full items-center gap-2 rounded-[0.85rem] px-3 py-2 text-sm font-medium transition"
                                                                style="color: var(--theme-error-color, #ef4444);"
                                                            >
                                                                <i class="fa-light fa-trash text-xs"></i>
                                                                {{ __('Delete') }}
                                                            </button>
                                                        </x-slot:trigger>

                                                        <x-slot:footer>
                                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                            <x-ui.button type="button" variant="danger" wire:click="deleteAccount({{ $account->id }})" x-on:click="open = false">{{ __('Delete channel') }}</x-ui.button>
                                                        </x-slot:footer>
                                                    </x-ui.dialog>
                                                <?php endif; ?>
                                        </x-ui.dropdown-menu>
                                    <?php endif; ?>
                                </div>
                            </x-ui.card>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    @endif
</div>
