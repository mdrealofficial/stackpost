<?php

namespace Modules\AppChannelInstagramProfiles\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;

#[Title('Select Instagram Profiles')]
class InstagramProfilePicker extends Component
{
    public string $search = '';

    public bool $confirmOverLimit = false;

    public array $pendingSelectedIds = [];

    public int $pendingAcceptedCount = 0;

    public array $selectedIds = [];

    public array $profiles = [];

    public array $context = [];

    public function mount(): void
    {
        $this->context = (array) session('instagram_profile_picker', []);
        $this->profiles = array_values((array) ($this->context['profiles'] ?? []));

        abort_unless($this->planAccess()->canCreate(auth()->user()) && $this->planAccess()->canUseCapability(auth()->user(), 'instagram_profile'), 403);
    }

    public function addSelected(array $selectedIds = [])
    {
        abort_unless($this->planAccess()->canCreate(auth()->user()) && $this->planAccess()->canUseCapability(auth()->user(), 'instagram_profile'), 403);

        $selectedIds = array_values(array_filter($selectedIds, fn ($id) => filled($id)));
        $existingIds = $this->existingSelectedIds($selectedIds);
        $newSelectedIds = array_values(array_diff($selectedIds, $existingIds));

        $remainingSlots = $this->planAccess()->remainingSlots(auth()->user(), 'instagram_profile');

        if ($remainingSlots !== null && $remainingSlots <= 0 && $newSelectedIds !== []) {
            session()->flash('status', __('Your current plan has reached the channel limit.'));

            return null;
        }

        if ($remainingSlots !== null && count($newSelectedIds) > $remainingSlots) {
            $this->confirmOverLimit = true;
            $this->pendingSelectedIds = array_values(array_merge($existingIds, array_slice($newSelectedIds, 0, $remainingSlots)));
            $this->pendingAcceptedCount = count($this->pendingSelectedIds);

            return null;
        }

        return $this->persistSelected($selectedIds);
    }

    public function confirmAddSelected()
    {
        if (! $this->confirmOverLimit || $this->pendingSelectedIds === []) {
            return null;
        }

        $selectedIds = array_slice($this->pendingSelectedIds, 0, $this->pendingAcceptedCount);

        $this->cancelAddSelected();

        return $this->persistSelected($selectedIds);
    }

    public function cancelAddSelected(): void
    {
        $this->confirmOverLimit = false;
        $this->pendingSelectedIds = [];
        $this->pendingAcceptedCount = 0;
    }

    protected function persistSelected(array $selectedIds = [])
    {
        abort_unless($this->planAccess()->canCreate(auth()->user()) && $this->planAccess()->canUseCapability(auth()->user(), 'instagram_profile'), 403);

        if ($this->profiles === [] || $selectedIds === []) {
            return null;
        }

        $profiles = $this->filteredProfiles(false)
            ->whereIn('id', $selectedIds)
            ->values();

        if ($profiles->isEmpty()) {
            return null;
        }

        $existingIds = $this->existingSelectedIds($selectedIds);

        foreach ($profiles as $profile) {
            if (! in_array((string) $profile['id'], $existingIds, true)
                && $this->planAccess()->hasReachedLimit(auth()->user(), 'instagram_profile')) {
                break;
            }

            SocialAccount::query()->updateOrCreate(
                [
                    'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                    'provider_key' => 'instagram',
                    'capability_key' => 'instagram_profile',
                    'external_id' => (string) $profile['id'],
                ],
                [
                    'display_name' => (string) $profile['name'],
                    'username' => (string) ($profile['username'] ?? Str::slug((string) $profile['name'])),
                    'category' => 'Profile',
                    'account_type' => 'oauth',
                    'profile_url' => (string) ($profile['link'] ?? ''),
                    'avatar_url' => (string) ($profile['avatar_url'] ?? ''),
                    'reconnect_url' => (string) ($this->context['reconnect_url'] ?? ''),
                    'access_token' => (string) ($profile['access_token'] ?? $this->context['user_token'] ?? ''),
                    'refresh_token' => null,
                    'scopes' => (string) ($this->context['permissions'] ?? ''),
                    'auth_data' => [
                        'user_token' => (string) ($this->context['user_token'] ?? ''),
                        'token_payload' => $this->context['token_payload'] ?? [],
                        'profile_payload' => $profile['payload'] ?? $profile,
                    ],
                    'metadata' => [
                        'provider' => 'instagram',
                        'page_id' => (string) ($profile['page_id'] ?? ''),
                        'page_name' => (string) ($profile['page_name'] ?? ''),
                        'ig_id' => (string) ($profile['ig_id'] ?? ''),
                    ],
                    'is_active' => true,
                    'connected_at' => now(),
                ]
            );
        }

        session()->forget('instagram_profile_picker');

        return redirect()
            ->route('portal.channels', ['provider' => 'instagram'])
            ->with('channels.flash', [
                'tone' => 'success',
                'message' => trans_choice(':count Instagram profile connected.|:count Instagram profiles connected.', $profiles->count(), ['count' => $profiles->count()]),
            ]);
    }

    public function reconnect()
    {
        return redirect()->route('portal.channels.instagram.profiles.connect');
    }

    public function render(): View
    {
        $items = $this->filteredProfiles()
            ->map(fn (array $profile): array => [
                'id' => (string) $profile['id'],
                'name' => (string) $profile['name'],
                'subtitle' => '@'.ltrim((string) ($profile['username'] ?? ''), '@'),
                'avatar_url' => (string) ($profile['avatar_url'] ?? ''),
            ])->values()->all();

        return view('appchannelinstagramprofiles::livewire.instagram-profile-picker', [
            'items' => $items,
            'hasSourceItems' => $this->profiles !== [],
            'sourceItemsCount' => count($this->profiles),
            'selectedCount' => count($this->selectedIds),
            'pickerEyebrow' => __('Channel connection'),
            'pickerTitle' => __('Select Instagram profiles'),
            'pickerDescription' => __('Choose only the Instagram profiles you want to connect to this workspace.'),
            'pickerIcon' => 'fa-brands fa-instagram',
            'reconnectLabel' => __('Reconnect'),
            'emptyTitle' => __('No Instagram profiles found'),
            'emptyDescription' => __('Reconnect your Instagram authorization to refresh the available profiles, then pick the ones you want to add.'),
            'emptyBody' => __('No selectable Instagram business or creator profiles came back from the provider.'),
            'collectionTitle' => __('Instagram profiles'),
            'collectionDescription' => __('Review the Instagram profiles returned by Meta and add only the ones you want in this workspace.'),
            'searchLabel' => __('Search profiles'),
            'searchHelp' => __('Filter by profile name or username before adding.'),
            'searchPlaceholder' => __('Search profiles...'),
            'defaultSubtitle' => __('Profile'),
            'noMatchTitle' => __('No matching profiles'),
            'noMatchDescription' => __('Try a different search term or reconnect to refresh the list.'),
            'footerDescription' => __('Only selected Instagram profiles will be added to your Channels workspace.'),
            'submitLabel' => __('Add selected'),
            'confirmOverLimit' => $this->confirmOverLimit,
            'pendingAcceptedCount' => $this->pendingAcceptedCount,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Select Instagram Profiles'),
        ]);
    }

    protected function filteredProfiles(bool $applySearch = true): Collection
    {
        return collect($this->profiles)
            ->filter(function (array $profile) use ($applySearch): bool {
                if (! $applySearch || trim($this->search) === '') {
                    return true;
                }

                $needle = Str::lower(trim($this->search));

                return Str::contains(Str::lower((string) ($profile['name'] ?? '')), $needle)
                    || Str::contains(Str::lower((string) ($profile['username'] ?? '')), $needle);
            });
    }

    protected function planAccess(): ChannelPlanAccess
    {
        return app(ChannelPlanAccess::class);
    }

    protected function existingSelectedIds(array $selectedIds): array
    {
        if ($selectedIds === []) {
            return [];
        }

        return SocialAccount::query()
            ->where('created_by_user_id', \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()))
            ->where('provider_key', 'instagram')
            ->where('capability_key', 'instagram_profile')
            ->whereIn('external_id', $selectedIds)
            ->pluck('external_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
