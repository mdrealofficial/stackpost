<?php

namespace Modules\AppChannels\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelCatalog;
use Modules\AppChannels\Support\ChannelPlanAccess;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Channels')]
class PortalDashboard extends Component
{
    #[Url(as: 'provider')]
    public string $activeProvider = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'connection')]
    public string $connectionFilter = 'all';

    #[Url(as: 'sort')]
    public string $sortBy = 'latest';

    /**
     * @var array<int, int|string>
     */
    public array $selectedAccountIds = [];

    public string $selectedCapability = '';

    /**
     * @var array<string, mixed>
     */
    public array $accountForm = [];

    public ?int $editingAccountId = null;

    public bool $showAccountForm = false;

    public ?string $bannerMessage = null;

    public string $bannerTone = 'warning';

    protected ChannelCatalog $catalog;

    protected ChannelPlanAccess $planAccess;

    public function boot(ChannelCatalog $catalog, ChannelPlanAccess $planAccess): void
    {
        $this->catalog = $catalog;
        $this->planAccess = $planAccess;
    }

    public function mount(): void
    {
        abort_unless($this->channelsEnabled(), 404);

        $this->syncActiveProvider();
        $this->normalizeConnectionFilters();

        $flash = session('channels.flash');

        if (is_array($flash) && filled($flash['message'] ?? null)) {
            $this->bannerMessage = (string) $flash['message'];
            $this->bannerTone = (string) ($flash['tone'] ?? 'warning');
        }
    }

    public function updatedActiveProvider(): void
    {
        $this->syncActiveProvider();
        $this->syncSelectedAccounts();
        $this->cancelAccountForm();
    }

    public function updatedStatusFilter(): void
    {
        $this->normalizeConnectionFilters();

        $this->syncSelectedAccounts();
    }

    public function updatedConnectionFilter(): void
    {
        $this->normalizeConnectionFilters();
        $this->syncSelectedAccounts();
    }

    protected function normalizeConnectionFilters(): void
    {
        // Preserve links that previously used the status parameter for connection type.
        if (in_array($this->statusFilter, ['oauth', 'manual'], true)) {
            $this->connectionFilter = $this->statusFilter;
            $this->statusFilter = 'all';
        }

        if (! in_array($this->statusFilter, ['all', 'active', 'paused'], true)) {
            $this->statusFilter = 'all';
        }
        if (! in_array($this->connectionFilter, ['all', 'oauth', 'manual'], true)) {
            $this->connectionFilter = 'all';
        }
    }

    public function updatedSortBy(): void
    {
        if (! in_array($this->sortBy, collect($this->sortOptions())->pluck('value')->all(), true)) {
            $this->sortBy = 'latest';
        }

        $this->syncSelectedAccounts();
    }

    public function updatedSearch(): void
    {
        $this->syncSelectedAccounts();
    }

    public function updatedSelectedAccountIds(): void
    {
        $this->selectedAccountIds = $this->sanitizeAccountIds($this->selectedAccountIds);
    }

    public function switchProvider(string $provider): void
    {
        $this->activeProvider = $provider;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->connectionFilter = 'all';
        $this->activeProvider = '';
        $this->cancelAccountForm();
        $this->syncSelectedAccounts();
    }

    public function toggleSelectAllVisible(): void
    {
        $visibleIds = $this->visibleAccountIds();

        if ($visibleIds === []) {
            $this->selectedAccountIds = [];

            return;
        }

        $selected = $this->sanitizeAccountIds($this->selectedAccountIds);
        $allVisibleSelected = collect($visibleIds)->every(fn (int $id): bool => in_array($id, $selected, true));

        if ($allVisibleSelected) {
            $this->selectedAccountIds = array_values(array_diff($selected, $visibleIds));

            return;
        }

        $this->selectedAccountIds = array_values(array_unique([...$selected, ...$visibleIds]));
    }

    public function clearSelectedAccounts(): void
    {
        $this->selectedAccountIds = [];
    }

    public function bulkActivateSelected(): void
    {
        if (! $this->planAccess->canToggleStatus(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow pausing or resuming channels.');
            $this->bannerTone = 'warning';

            return;
        }

        $count = $this->selectedAccountsQuery()->update(['is_active' => true]);
        $this->selectedAccountIds = [];
        $this->bannerMessage = trans_choice(':count channel activated.', $count, ['count' => $count]);
        $this->bannerTone = 'success';
        $this->dispatch('portal-account-saved');
    }

    public function bulkPauseSelected(): void
    {
        if (! $this->planAccess->canToggleStatus(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow pausing or resuming channels.');
            $this->bannerTone = 'warning';

            return;
        }

        $count = $this->selectedAccountsQuery()->update(['is_active' => false]);
        $this->selectedAccountIds = [];
        $this->bannerMessage = trans_choice(':count channel paused.', $count, ['count' => $count]);
        $this->bannerTone = 'success';
        $this->dispatch('portal-account-saved');
    }

    public function bulkDeleteSelected(): void
    {
        if (! $this->planAccess->canDelete(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow deleting channels.');
            $this->bannerTone = 'warning';

            return;
        }

        $count = $this->selectedAccountsQuery()->count();
        $this->selectedAccountsQuery()->delete();
        $this->selectedAccountIds = [];
        $this->bannerMessage = trans_choice(':count channel removed.', $count, ['count' => $count]);
        $this->bannerTone = $count > 0 ? 'success' : 'warning';
        $this->dispatch('portal-account-deleted');
    }

    public function createAccount(string $capabilityKey)
    {
        if (! $this->planAccess->canCreate(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow adding channels.');
            $this->bannerTone = 'warning';

            return;
        }

        if (! $this->planAccess->canUseCapability(auth()->user(), $capabilityKey)) {
            $this->bannerMessage = __('This channel type is not enabled for your current plan.');
            $this->bannerTone = 'warning';

            return;
        }

        if ($this->planAccess->hasReachedLimit(auth()->user(), $capabilityKey)) {
            $this->bannerMessage = __('Your current plan has reached the channel limit.');
            $this->bannerTone = 'warning';

            return;
        }

        $capability = $this->currentCapability($capabilityKey);

        if ($capability === []) {
            return;
        }

        $providerState = integration_item_state((string) $capability['provider_key']);

        if (! $providerState['ready']) {
            $missing = collect($providerState['missing_fields'])
                ->map(fn (string $field): string => str($field)->replace('_', ' ')->headline())
                ->implode(', ');

            $this->bannerMessage = $missing !== ''
                ? __(':provider is not ready yet. Complete: :fields in API Integration first.', [
                    'provider' => data_get($providerState, 'item.label', __('This provider')),
                    'fields' => $missing,
                ])
                : __(':provider is disabled in API Integration. Enable it before adding channels here.', [
                    'provider' => data_get($providerState, 'item.label', __('This provider')),
                ]);
            $this->bannerTone = 'warning';

            return;
        }

        $driverClass = data_get($capability, 'driver');

        if (is_string($driverClass) && class_exists($driverClass) && method_exists($driverClass, 'authorizeUrl')) {
            $authorizeUrl = $driverClass::authorizeUrl(auth()->user(), [
                'capability' => $capability,
                'provider_state' => $providerState,
            ]);

            if (filled($authorizeUrl)) {
                return redirect()->away($authorizeUrl);
            }
        }

        $this->bannerMessage = null;
        $this->bannerTone = 'warning';
        $this->selectedCapability = $capabilityKey;
        $this->editingAccountId = null;
        $this->showAccountForm = true;
        $this->resetAccountForm();
    }

    public function editAccount(int $accountId): void
    {
        if (! $this->planAccess->canEdit(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow editing channels.');
            $this->bannerTone = 'warning';

            return;
        }

        $account = $this->ownedAccounts()->findOrFail($accountId);

        $this->editingAccountId = $account->id;
        $this->showAccountForm = true;
        $this->selectedCapability = (string) ($account->capability_key ?: $account->provider_key);
        $this->accountForm = [
            'display_name' => (string) $account->display_name,
            'username' => (string) ($account->username ?? ''),
            'external_id' => (string) ($account->external_id ?? ''),
            'category' => (string) ($account->category ?? ''),
            'account_type' => (string) ($account->account_type ?? 'manual'),
            'profile_url' => (string) ($account->profile_url ?? ''),
            'avatar_url' => (string) ($account->avatar_url ?? ''),
            'reconnect_url' => (string) ($account->reconnect_url ?? ''),
            'access_token' => (string) ($account->access_token ?? ''),
            'refresh_token' => (string) ($account->refresh_token ?? ''),
            'scopes' => (string) ($account->scopes ?? ''),
            'auth_data_json' => $this->encodeJson($account->auth_data),
            'metadata_json' => $this->encodeJson($account->metadata),
            'notes' => (string) ($account->notes ?? ''),
            'is_active' => $account->is_active ? '1' : '0',
        ];
    }

    public function saveAccount(): void
    {
        $isEditing = $this->editingAccountId !== null;

        if ($isEditing && ! $this->planAccess->canEdit(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow editing channels.');
            $this->bannerTone = 'warning';

            return;
        }

        if (! $isEditing && ! $this->planAccess->canCreate(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow adding channels.');
            $this->bannerTone = 'warning';

            return;
        }

        if (! $this->planAccess->canUseCapability(auth()->user(), $this->selectedCapability)) {
            $this->bannerMessage = __('This channel type is not enabled for your current plan.');
            $this->bannerTone = 'warning';

            return;
        }

        if (! $isEditing && $this->planAccess->hasReachedLimit(auth()->user(), $this->selectedCapability)) {
            $this->bannerMessage = __('Your current plan has reached the channel limit.');
            $this->bannerTone = 'warning';

            return;
        }

        $capability = $this->currentCapability();

        if ($capability === []) {
            return;
        }

        $validated = $this->validate($this->accountValidationRules(), [], $this->accountAttributeNames());
        $providerKey = (string) $capability['provider_key'];

        $payload = [
            'provider_key' => $providerKey,
            'capability_key' => $this->selectedCapability,
            'display_name' => $validated['accountForm.display_name'],
            'username' => $this->nullableString($validated['accountForm.username']),
            'external_id' => $this->nullableString($validated['accountForm.external_id']),
            'category' => $this->nullableString($validated['accountForm.category']),
            'account_type' => $validated['accountForm.account_type'],
            'profile_url' => $this->nullableString($validated['accountForm.profile_url']),
            'avatar_url' => $this->nullableString($validated['accountForm.avatar_url']),
            'reconnect_url' => $this->nullableString($validated['accountForm.reconnect_url']),
            'access_token' => $this->nullableString($validated['accountForm.access_token']),
            'refresh_token' => $this->nullableString($validated['accountForm.refresh_token']),
            'scopes' => $this->nullableString($validated['accountForm.scopes']),
            'auth_data' => $this->decodeJson($validated['accountForm.auth_data_json']),
            'metadata' => $this->decodeJson($validated['accountForm.metadata_json']),
            'notes' => $this->nullableString($validated['accountForm.notes']),
            'is_active' => ($validated['accountForm.is_active'] ?? '1') === '1',
        ];

        if ($this->editingAccountId) {
            $account = $this->ownedAccounts()->findOrFail($this->editingAccountId);
            $account->update($payload);
        } else {
            SocialAccount::query()->create($payload + [
                'created_by_user_id' => $this->workspaceOwnerUserId(),
                'connected_at' => now(),
            ]);
        }

        $this->dispatch('portal-account-saved');
        $this->cancelAccountForm();
    }

    public function deleteAccount(int $accountId): void
    {
        if (! $this->planAccess->canDelete(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow deleting channels.');
            $this->bannerTone = 'warning';

            return;
        }

        $this->ownedAccounts()->whereKey($accountId)->delete();

        if ($this->editingAccountId === $accountId) {
            $this->cancelAccountForm();
        }

        $this->dispatch('portal-account-deleted');
    }

    public function reconnectAccount(int $accountId)
    {
        if (! $this->planAccess->canReconnect(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow reconnecting channels.');
            $this->bannerTone = 'warning';

            return null;
        }

        $account = $this->ownedAccounts()->findOrFail($accountId);
        $reconnectUrl = (string) ($account->reconnect_url ?? '');

        if ($reconnectUrl === '') {
            $this->bannerMessage = __(':name does not have a reconnect flow configured.', [
                'name' => $account->display_name,
            ]);
            $this->bannerTone = 'warning';

            return null;
        }

        $separator = str_contains($reconnectUrl, '?') ? '&' : '?';
        $reconnectUrl .= $separator.http_build_query([
            'reconnect' => 1,
            'account' => $account->id,
        ]);

        return redirect()->to($reconnectUrl);
    }

    public function toggleAccountStatus(int $accountId): void
    {
        if (! $this->planAccess->canToggleStatus(auth()->user())) {
            $this->bannerMessage = __('Your current plan does not allow pausing or resuming channels.');
            $this->bannerTone = 'warning';

            return;
        }

        $account = $this->ownedAccounts()->findOrFail($accountId);
        $account->update([
            'is_active' => ! $account->is_active,
        ]);

        $this->bannerMessage = $account->is_active
            ? __(':name has been paused.', ['name' => $account->display_name])
            : __(':name is active again.', ['name' => $account->display_name]);
        $this->bannerTone = $account->is_active ? 'warning' : 'success';

        $this->dispatch('portal-account-saved');
    }

    public function cancelAccountForm(): void
    {
        $this->showAccountForm = false;
        $this->editingAccountId = null;
        $this->selectedCapability = '';
        $this->resetAccountForm();
    }

    public function render(): View
    {
        abort_unless($this->channelsEnabled(), 404);

        $user = auth()->user();
        $plan = $user?->plan;
        $planOwner = TeamWorkspaceAccess::activeTeam($user)?->owner ?: $user;
        $channelLimit = (int) ($planOwner?->planLimit('max_channels', -1) ?? -1);
        $channelLimitPerNetwork = $planOwner?->planLimit('channel_count_mode', 'entire_social_network') === 'each_social_network';
        $allowedCapabilityKeys = $this->planAccess->allowedCapabilityKeys($user);
        $providerCards = collect(channel_provider_cards())
            ->map(function (array $provider) use ($allowedCapabilityKeys): array {
                if ($allowedCapabilityKeys === null) {
                    return $provider;
                }

                $provider['capabilities'] = collect($provider['capabilities'] ?? [])
                    ->filter(fn (array $capability): bool => in_array((string) ($capability['key'] ?? ''), $allowedCapabilityKeys, true))
                    ->values()
                    ->all();

                return $provider;
            })
            ->filter(fn (array $provider): bool => ($provider['capabilities'] ?? []) !== [])
            ->values()
            ->all();
        $providers = integration_items();
        $allAccounts = $this->ownedAccounts()->get(['id', 'is_active', 'account_type', 'connected_at']);
        $filteredQuery = $this->filteredAccountsQuery();

        $accounts = match ($this->sortBy) {
            'oldest' => (clone $filteredQuery)->oldest()->get(),
            'name' => (clone $filteredQuery)->orderBy('display_name')->get(),
            'recently_connected' => (clone $filteredQuery)->orderByDesc('connected_at')->get(),
            default => (clone $filteredQuery)->latest()->get(),
        };

        $visibleAccountIds = $accounts->modelKeys();
        $selectedAccountIds = $this->sanitizeAccountIds($this->selectedAccountIds);
        $selectedVisibleCount = count(array_intersect($selectedAccountIds, $visibleAccountIds));

        $providerCounts = $this->ownedAccounts()
            ->selectRaw('provider_key, count(*) as aggregate')
            ->groupBy('provider_key')
            ->pluck('aggregate', 'provider_key')
            ->all();
        $capabilityCounts = $this->ownedAccounts()
            ->selectRaw('capability_key, count(*) as aggregate')
            ->groupBy('capability_key')
            ->pluck('aggregate', 'capability_key')
            ->all();

        $activeAccounts = $allAccounts->where('is_active', true)->count();
        $pausedAccounts = $allAccounts->where('is_active', false)->count();
        $oauthAccounts = $allAccounts->where('account_type', 'oauth')->count();
        $manualAccounts = $allAccounts->where('account_type', 'manual')->count();
        $connectedThisMonth = $allAccounts
            ->filter(fn (SocialAccount $account): bool => $account->connected_at?->gte(now()->subDays(30)) ?? false)
            ->count();
        $providersReady = collect($providerCards)->where('is_ready', true)->count();
        $providersNeedingConfig = collect($providerCards)->where('is_ready', false)->count();
        $currentProvider = $this->currentProvider($providerCards);
        $currentProviderState = $currentProvider !== []
            ? integration_item_state((string) $currentProvider['key'])
            : null;
        $topCapabilities = collect(channel_capabilities())
            ->when($allowedCapabilityKeys !== null, fn ($collection) => $collection->filter(fn (array $capability, string $key): bool => in_array($key, $allowedCapabilityKeys, true)))
            ->map(function (array $capability) use ($capabilityCounts): array {
                $capability['account_count'] = (int) ($capabilityCounts[$capability['key']] ?? 0);

                return $capability;
            })
            ->sortByDesc('account_count')
            ->take(5)
            ->values()
            ->all();

        return view('appchannels::livewire.portal-dashboard', [
            'user' => $user,
            'plan' => $plan,
            'channelLimit' => $channelLimit,
            'channelLimitPerNetwork' => $channelLimitPerNetwork,
            'channelSlotsUsed' => $channelLimit >= 0 && ! $channelLimitPerNetwork
                ? TeamWorkspaceAccess::accessibleAccountsQuery($user)->count()
                : null,
            'providerCards' => $providerCards,
            'providerRegistry' => $providers,
            'currentProvider' => $currentProvider,
            'currentProviderState' => $currentProviderState,
            'accounts' => $accounts,
            'accountTotal' => $allAccounts->count(),
            'providerCounts' => $providerCounts,
            'capabilityCounts' => $capabilityCounts,
            'capabilityMap' => channel_capabilities(),
            'statusOptions' => $this->toggleOptions(),
            'accountTypeOptions' => $this->accountTypeOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'selectedCapabilityDefinition' => $this->currentCapability(),
            'statusFilterOptions' => $this->statusFilterOptions(),
            'sortOptions' => $this->sortOptions(),
            'activeAccounts' => $activeAccounts,
            'pausedAccounts' => $pausedAccounts,
            'oauthAccounts' => $oauthAccounts,
            'manualAccounts' => $manualAccounts,
            'connectedThisMonth' => $connectedThisMonth,
            'providersReady' => $providersReady,
            'providersNeedingConfig' => $providersNeedingConfig,
            'topCapabilities' => $topCapabilities,
            'canCreateChannels' => $this->planAccess->canCreate($user),
            'canEditChannels' => $this->planAccess->canEdit($user),
            'canDeleteChannels' => $this->planAccess->canDelete($user),
            'canReconnectChannels' => $this->planAccess->canReconnect($user),
            'canToggleChannelStatus' => $this->planAccess->canToggleStatus($user),
            'selectedAccountCount' => count($selectedAccountIds),
            'selectedVisibleCount' => $selectedVisibleCount,
            'allVisibleSelected' => $visibleAccountIds !== [] && $selectedVisibleCount === count($visibleAccountIds),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Channels'),
        ]);
    }

    protected function filteredAccountsQuery()
    {
        return $this->ownedAccounts()
            ->when($this->activeProvider !== '', fn ($query) => $query->where('provider_key', $this->activeProvider))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($subQuery): void {
                    $term = '%'.trim($this->search).'%';

                    $subQuery->where('display_name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('external_id', 'like', $term)
                        ->orWhere('category', 'like', $term);
                });
            })
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'paused', fn ($query) => $query->where('is_active', false))
            ->when($this->connectionFilter === 'oauth', fn ($query) => $query->where('account_type', 'oauth'))
            ->when($this->connectionFilter === 'manual', fn ($query) => $query->where('account_type', 'manual'));
    }

    protected function ownedAccounts()
    {
        $query = TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user());
        $allowedCapabilityKeys = $this->planAccess->allowedCapabilityKeys(auth()->user());

        if ($allowedCapabilityKeys !== null) {
            $query->whereIn('capability_key', $allowedCapabilityKeys);
        }

        return $query;
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function channelsEnabled(): bool
    {
        return $this->planAccess->channelsEnabled(auth()->user());
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentProvider(array $providerCards = []): array
    {
        $cards = $providerCards !== [] ? $providerCards : channel_provider_cards();

        return collect($cards)->firstWhere('key', $this->activeProvider) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentCapability(?string $key = null): array
    {
        $capabilityKey = $key ?? $this->selectedCapability;

        if ($capabilityKey === '') {
            return [];
        }

        $capability = channel_capability($capabilityKey);

        if ($capability !== []) {
            return $capability;
        }

        return collect(channel_capabilities())
            ->first(fn (array $item): bool => ($item['provider_key'] ?? null) === $capabilityKey)
            ?? [];
    }

    protected function resetAccountForm(): void
    {
        $capability = $this->currentCapability();

        $this->accountForm = [
            'display_name' => '',
            'username' => '',
            'external_id' => '',
            'category' => $capability['categories'][0] ?? '',
            'account_type' => $capability['account_types'][0] ?? '',
            'profile_url' => '',
            'avatar_url' => '',
            'reconnect_url' => '',
            'access_token' => '',
            'refresh_token' => '',
            'scopes' => '',
            'auth_data_json' => '',
            'metadata_json' => '',
            'notes' => '',
            'is_active' => '1',
        ];
    }

    protected function accountValidationRules(): array
    {
        $capability = $this->currentCapability();

        return [
            'accountForm.display_name' => ['required', 'string', 'max:255'],
            'accountForm.username' => ['nullable', 'string', 'max:255'],
            'accountForm.external_id' => ['nullable', 'string', 'max:255'],
            'accountForm.category' => ['nullable', 'string', Rule::in($capability['categories'] ?? [])],
            'accountForm.account_type' => ['required', 'string', Rule::in($capability['account_types'] ?? [])],
            'accountForm.profile_url' => ['nullable', 'url', 'max:500'],
            'accountForm.avatar_url' => ['nullable', 'url', 'max:500'],
            'accountForm.reconnect_url' => ['nullable', 'url', 'max:500'],
            'accountForm.access_token' => ['nullable', 'string', 'max:5000'],
            'accountForm.refresh_token' => ['nullable', 'string', 'max:5000'],
            'accountForm.scopes' => ['nullable', 'string', 'max:2000'],
            'accountForm.auth_data_json' => ['nullable', 'json'],
            'accountForm.metadata_json' => ['nullable', 'json'],
            'accountForm.notes' => ['nullable', 'string', 'max:4000'],
            'accountForm.is_active' => ['required', 'in:0,1'],
        ];
    }

    protected function accountAttributeNames(): array
    {
        return [
            'accountForm.display_name' => 'display name',
            'accountForm.username' => 'username',
            'accountForm.external_id' => 'external ID',
            'accountForm.category' => 'category',
            'accountForm.account_type' => 'account type',
            'accountForm.profile_url' => 'profile URL',
            'accountForm.avatar_url' => 'avatar URL',
            'accountForm.reconnect_url' => 'reconnect URL',
            'accountForm.access_token' => 'access token',
            'accountForm.refresh_token' => 'refresh token',
            'accountForm.scopes' => 'scopes',
            'accountForm.auth_data_json' => 'auth payload JSON',
            'accountForm.metadata_json' => 'metadata JSON',
            'accountForm.notes' => 'notes',
            'accountForm.is_active' => 'status',
        ];
    }

    protected function toggleOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Enable'],
            ['value' => '0', 'label' => 'Disable'],
        ];
    }

    protected function statusFilterOptions(): array
    {
        return [
            ['value' => 'all', 'label' => __('All channels')],
            ['value' => 'active', 'label' => __('Enabled')],
            ['value' => 'paused', 'label' => __('Paused')],
        ];
    }

    protected function sortOptions(): array
    {
        return [
            ['value' => 'latest', 'label' => __('Newest added')],
            ['value' => 'recently_connected', 'label' => __('Recently connected')],
            ['value' => 'name', 'label' => __('Name A-Z')],
            ['value' => 'oldest', 'label' => __('Oldest first')],
        ];
    }

    protected function accountTypeOptions(): array
    {
        return collect($this->currentCapability()['account_types'] ?? [])
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => ucfirst(str_replace('_', ' ', $value)),
            ])->values()->all();
    }

    protected function categoryOptions(): array
    {
        return collect($this->currentCapability()['categories'] ?? [])
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => $value,
            ])->values()->all();
    }

    protected function nullableString(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }

    protected function decodeJson(?string $value): ?array
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }

    protected function encodeJson(mixed $value): string
    {
        if (! is_array($value) || $value === []) {
            return '';
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function syncActiveProvider(): void
    {
        $providerCards = channel_provider_cards();
        $providerKeys = collect($providerCards)->pluck('key')->all();

        if ($this->activeProvider === '') {
            return;
        }

        if (in_array($this->activeProvider, $providerKeys, true)) {
            return;
        }

        $this->activeProvider = '';
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    protected function sanitizeAccountIds(array $ids): array
    {
        $ownedIds = $this->ownedAccounts()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => in_array($id, $ownedIds, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function visibleAccountIds(): array
    {
        return $this->filteredAccountsQuery()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected function syncSelectedAccounts(): void
    {
        $visibleIds = $this->visibleAccountIds();

        $this->selectedAccountIds = array_values(array_intersect(
            $this->sanitizeAccountIds($this->selectedAccountIds),
            $visibleIds
        ));
    }

    protected function selectedAccountsQuery()
    {
        return $this->ownedAccounts()->whereIn('id', $this->sanitizeAccountIds($this->selectedAccountIds));
    }
}
