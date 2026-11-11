@component(theme_view('layouts.app', 'app'), ['title' => __('Groups')])
    @php
        $activeGroups = $groups->where('status', 'active')->count();
        $inactiveGroups = $groups->filter(fn ($group) => in_array($group->status, ['inactive', 'draft', 'archived'], true))->count();
        $groupedAccountIds = $groups
            ->flatMap(fn ($group) => $group->accounts->pluck('id'))
            ->unique()
            ->count();
        $channelProviderRegistry = collect(channel_provider_cards())->keyBy('key');
        $channelOptions = collect($accounts)
            ->map(function ($account) use ($channelProviderRegistry) {
                $provider = $channelProviderRegistry->get((string) $account->provider_key, []);
                $providerLabel = (string) data_get($provider, 'label', str($account->provider_key)->headline());
                $capabilityLabel = (string) ($account->category ?: __('Channel'));
                $providerTone = publishing_provider_tone((string) $account->provider_key);

                return [
                    'key' => (string) $account->id,
                    'label' => (string) ($account->display_name ?: ($account->username ? '@'.$account->username : strtoupper((string) $account->provider_key))),
                    'subtitle' => trim($providerLabel.' '.str($capabilityLabel)->lower()),
                    'avatarUrl' => (string) ($account->avatar_url ?? ''),
                    'providerKey' => (string) $account->provider_key,
                    'providerLabel' => $providerLabel,
                    'providerIcon' => (string) data_get($provider, 'icon', ''),
                    'providerColor' => (string) data_get($provider, 'color', ''),
                    'providerToneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'providerToneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->values()
            ->all();
        $channelNetworks = $channelProviderRegistry
            ->only(collect($accounts)->pluck('provider_key')->filter()->unique()->values()->all())
            ->map(function ($provider) {
                $providerKey = (string) ($provider['key'] ?? '');
                $providerTone = publishing_provider_tone($providerKey);

                return [
                    'key' => $providerKey,
                    'label' => (string) ($provider['label'] ?? ''),
                    'icon' => (string) ($provider['icon'] ?? ''),
                    'color' => (string) ($provider['color'] ?? ''),
                    'toneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'toneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->filter(fn ($provider) => $provider['key'] !== '' && $provider['label'] !== '')
            ->values()
            ->all();
    @endphp

    <div class="space-y-6 px-4 pb-8 pt-4 sm:px-5 xl:px-6">
        <section class="overflow-hidden rounded-[1.75rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
            radial-gradient(circle at 0% 0%, rgba(var(--theme-accent-rgb), 0.16), transparent 30%),
            linear-gradient(135deg, rgba(var(--theme-surface-base-rgb,255,255,255),0.98), rgba(var(--theme-surface-base-rgb,255,255,255),0.94));
        ">
            <div class="grid gap-6 px-6 py-7 sm:px-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-accent);">{{ __('Workspace clusters') }}</p>
                    <h1 class="mt-2 text-[1.85rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ __('Groups Workspace') }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Group connected social accounts into reusable publishing clusters so campaign setup, filtering, and future reporting all stay aligned.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button :href="route('portal.channels')" variant="outline">
                        <i class="fa-light fa-share-nodes"></i>
                        {{ __('Open Channels') }}
                    </x-ui.button>
                    <x-ui.button :href="route('portal.groups', array_filter(['edit' => null]))">
                        <i class="fa-light fa-plus"></i>
                        {{ $editing ? __('New group') : __('Create group') }}
                    </x-ui.button>
                </div>
            </div>
        </section>

        

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article class="rounded-[1.35rem] border px-5 py-5 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Total') }}</p>
                        <p class="mt-3 text-[2.25rem] font-semibold tracking-[-0.06em]" style="color: var(--theme-header-text-color);">{{ $groups->count() }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-[1rem] border text-base" style="border-color: rgba(var(--theme-accent-rgb),0.18); background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                        <i class="fa-light fa-layer-group"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Reusable account clusters available across the workspace.') }}</p>
            </article>

            <article class="rounded-[1.35rem] border px-5 py-5 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Active') }}</p>
                        <p class="mt-3 text-[2.25rem] font-semibold tracking-[-0.06em]" style="color: var(--theme-header-text-color);">{{ $activeGroups }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-[1rem] border text-base" style="border-color: rgba(34,197,94,0.18); background-color: rgba(34,197,94,0.10); color: #16a34a;">
                        <i class="fa-light fa-bolt"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Groups currently ready to use in publishing flows.') }}</p>
            </article>

            <article class="rounded-[1.35rem] border px-5 py-5 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Inactive') }}</p>
                        <p class="mt-3 text-[2.25rem] font-semibold tracking-[-0.06em]" style="color: var(--theme-header-text-color);">{{ $inactiveGroups }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-[1rem] border text-base" style="border-color: rgba(249,115,22,0.18); background-color: rgba(249,115,22,0.10); color: #f97316;">
                        <i class="fa-light fa-box-archive"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Inactive clusters kept for record or later reuse.') }}</p>
            </article>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <section class="space-y-5">
                <section class="rounded-[1.45rem] border px-5 py-5 shadow-[0_24px_70px_-46px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
                    radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.08), transparent 26%),
                    rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Group Inventory') }}</p>
                            <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Browse saved groups, filter by status, and reopen any cluster for editing.') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1.5 text-sm font-medium" style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);">
                            {{ count($groups) }} {{ __('shown') }}
                        </span>
                    </div>

                    <form method="GET" action="{{ route('portal.groups') }}" class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_12rem_auto]">
                        <x-ui.input name="q" :value="$filters['q'] ?? ''" :placeholder="__('Search name, description, or slug')" />
                        <x-ui.select name="status">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                        </x-ui.select>
                        <div class="flex items-center gap-3">
                            <x-ui.button type="submit" variant="outline">{{ __('Apply') }}</x-ui.button>
                            <x-ui.button :href="route('portal.groups')" variant="ghost">{{ __('Reset') }}</x-ui.button>
                        </div>
                    </form>
                </section>

                @if ($groups->isNotEmpty())
                    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($groups as $group)
                            <article class="rounded-[1.25rem] border px-5 py-5 shadow-[0_18px_50px_-40px_rgba(15,23,42,0.22)] h-full" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
                                radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.06), transparent 26%),
                                rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                                <div class="flex h-full flex-col">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-muted-text-color);">{{ __('Group') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                                style="
                                                    @if ($group->status === 'active')
                                                        background-color: rgba(34,197,94,0.10); color: #16a34a;
                                                    @else
                                                        background-color: rgba(249,115,22,0.10); color: #f97316;
                                                    @endif
                                                ">
                                                {{ strtoupper($group->status === 'active' ? __('active') : __('inactive')) }}
                                            </span>
                                        </div>
                                        <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ $group->updated_at?->diffForHumans() }}</span>
                                    </div>

                                    <div class="mt-4">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1 inline-flex h-4 w-4 shrink-0 rounded-full border border-white/80 shadow-[0_8px_20px_-14px_rgba(15,23,42,0.35)]" style="background-color: {{ $group->color }};"></span>
                                            <div class="min-w-0">
                                                <p class="text-[1.05rem] font-semibold leading-7 tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $group->name }}</p>
                                                @if (filled($group->description))
                                                    <p class="mt-2 text-sm leading-7" style="color: var(--theme-header-text-color);">{{ \Illuminate\Support\Str::limit($group->description, 180) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5 rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Accounts') }}</p>
                                            <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                                {{ trans_choice(':count account|:count accounts', $group->accounts_count, ['count' => $group->accounts_count]) }}
                                            </span>
                                        </div>

                                        @if ($group->accounts->isNotEmpty())
                                            <div class="mt-3 flex items-center">
                                                @foreach ($group->accounts->take(5) as $account)
                                                    @php($accountTone = publishing_provider_tone((string) $account->provider_key))
                                                    <span
                                                        class="inline-flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border-2 shadow-[0_10px_20px_-14px_rgba(15,23,42,0.35)] {{ $loop->first ? '' : '-ml-3' }}"
                                                        style="border-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96); background-color: {{ $accountTone['surface'] }};"
                                                        title="{{ $account->display_name }}"
                                                    >
                                                        @if ($account->avatar_url)
                                                            <img src="{{ $account->avatar_url }}" alt="{{ $account->display_name }}" class="h-full w-full object-cover">
                                                        @else
                                                            <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: {{ $accountTone['text'] }};">
                                                                {{ str($account->display_name)->substr(0, 2)->upper() }}
                                                            </span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                                @if ($group->accounts->count() > 5)
                                                    <span class="-ml-3 inline-flex h-10 min-w-[2.5rem] items-center justify-center rounded-full border-2 px-2 text-[12px] font-semibold shadow-[0_10px_20px_-14px_rgba(15,23,42,0.35)]"
                                                        style="border-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96); background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                                                        +{{ $group->accounts->count() - 5 }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('No accounts assigned yet.') }}</p>
                                        @endif
                                    </div>

                                    <div class="mt-auto flex items-center gap-2 pt-5">
                                        <x-ui.button :href="route('portal.groups', ['edit' => $group->id])" size="sm" variant="outline">{{ __('Edit') }}</x-ui.button>

                                        <x-ui.confirm-action
                                            :action="route('portal.groups.destroy', $group)"
                                            :title="__('Delete this group?')"
                                            :description="__('This removes the group definition but does not delete any connected social accounts.')"
                                            :submit-label="__('Delete')"
                                            variant="danger"
                                            size="sm"
                                        >
                                            <x-slot:trigger>
                                                <x-ui.button type="button" size="sm" variant="danger">{{ __('Delete') }}</x-ui.button>
                                            </x-slot:trigger>
                                        </x-ui.confirm-action>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty icon="fa-light fa-layer-group" :title="__('No groups found')" :description="__('Create your first account group to speed up scheduling and prepare for group-level analytics later.')" />
                @endif
            </section>

            <div class="hidden xl:block xl:self-start xl:sticky xl:top-24">
                <section class="overflow-hidden rounded-[1.45rem] border shadow-[0_24px_70px_-46px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.98);">
                    <div class="border-b px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $editing ? __('Edit Group') : __('Create Group') }}</p>
                                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ $editing ? __('Update channels, notes, or status for this group.') : __('Add a reusable account cluster for your publishing workspace and future campaigns.') }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                style="{{ $editing ? 'background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);' : 'background-color: rgba(34,197,94,0.10); color: #16a34a;' }}">
                                {{ $editing ? __('Editing') : __('New') }}
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="{{ $editing ? route('portal.groups.update', $editing) : route('portal.groups.store') }}" class="space-y-4 px-5 py-5">
                        @csrf
                        @if ($editing)
                            @method('PUT')
                        @endif

                        <x-ui.input name="name" :label="__('Group name')" :value="$form['name']" :error="$errors->first('name')" :placeholder="__('Launch accounts')" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.color-picker name="color" :label="__('Accent color')" :value="$form['color']" :error="$errors->first('color')" />
                            <x-ui.select name="status" :label="__('Status')" :error="$errors->first('status')">
                                @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                                    <option value="{{ $value }}" @selected($form['status'] === $value)>{{ __($label) }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <x-ui.channel-selector
                            name="account_ids"
                            :label="__('Accounts')"
                            :options="$channelOptions"
                            :network-options="$channelNetworks"
                            :selected="collect((array) $form['account_ids'])->map(fn ($id) => (string) $id)->all()"
                            :error="$errors->first('account_ids') ?: $errors->first('account_ids.*')"
                            :placeholder="__('Choose one or more accounts')"
                            :empty-label="__('No matching channels found.')"
                            :multiple="true"
                            :show-network-filters="true"
                            :connect-href="route('portal.channels')"
                            :connect-label="__('Connect a channel')"
                            :inline-panel="true"
                        />

                        <x-ui.textarea name="description" :label="__('Description')" :error="$errors->first('description')" rows="6" :placeholder="__('Optional internal note about campaign purpose, region, or audience.')">{{ $form['description'] }}</x-ui.textarea>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <x-ui.button type="submit">
                                {{ $editing ? __('Update Group') : __('Create Group') }}
                            </x-ui.button>
                            <x-ui.button :href="route('portal.groups')" variant="outline">{{ __('Cancel') }}</x-ui.button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
@endcomponent
