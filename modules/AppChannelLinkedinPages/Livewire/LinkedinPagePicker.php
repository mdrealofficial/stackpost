<?php

namespace Modules\AppChannelLinkedinPages\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannels\Support\ChannelPlanAccess;

#[Title('Select LinkedIn Pages')]
class LinkedinPagePicker extends Component
{
    public string $search = '';

    public bool $confirmOverLimit = false;

    public array $pendingSelectedIds = [];

    public int $pendingAcceptedCount = 0;

    public array $selectedIds = [];

    public array $pages = [];

    public array $context = [];

    public function mount(): void
    {
        $this->context = (array) session('linkedin_page_picker', []);
        $this->pages = array_values((array) ($this->context['pages'] ?? []));

        abort_unless($this->planAccess()->canCreate(auth()->user()) && $this->planAccess()->canUseCapability(auth()->user(), 'linkedin_page'), 403);
    }

    public function addSelected(array $selectedIds = [])
    {
        abort_unless($this->planAccess()->canCreate(auth()->user()) && $this->planAccess()->canUseCapability(auth()->user(), 'linkedin_page'), 403);

        $selectedIds = array_values(array_filter($selectedIds, fn ($id) => filled($id)));
        $existingIds = $this->existingSelectedIds($selectedIds);
        $newSelectedIds = array_values(array_diff($selectedIds, $existingIds));

        $remainingSlots = $this->planAccess()->remainingSlots(auth()->user(), 'linkedin_page');

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

    public function reconnect()
    {
        return redirect()->route('portal.channels.linkedin.pages.connect');
    }

    public function render(): View
    {
        $items = $this->filteredPages()
            ->map(fn (array $page): array => [
                'id' => (string) $page['id'],
                'name' => (string) $page['name'],
                'subtitle' => (string) (($page['username'] ?? '') !== '' ? '@'.$page['username'] : __('Page')),
                'avatar_url' => (string) ($page['avatar_url'] ?? ''),
            ])
            ->values()
            ->all();

        return view('appchannellinkedinpages::livewire.linkedin-page-picker', [
            'items' => $items,
            'hasSourceItems' => $this->pages !== [],
            'sourceItemsCount' => count($this->pages),
            'selectedCount' => count($this->selectedIds),
            'pickerEyebrow' => __('Channel connection'),
            'pickerTitle' => __('Select LinkedIn pages'),
            'pickerDescription' => __('Choose only the LinkedIn organization pages you want to connect to this workspace.'),
            'pickerIcon' => 'fa-brands fa-linkedin',
            'reconnectLabel' => __('Reconnect'),
            'emptyTitle' => __('No LinkedIn pages found'),
            'emptyDescription' => __('Reconnect your LinkedIn authorization to refresh the available pages, then pick the ones you want to add.'),
            'emptyBody' => __('No selectable LinkedIn organization pages came back from the provider.'),
            'collectionTitle' => __('LinkedIn pages'),
            'collectionDescription' => __('Review the pages returned by LinkedIn and add only the ones you want in this workspace.'),
            'searchLabel' => __('Search pages'),
            'searchHelp' => __('Filter by page name or vanity name before adding.'),
            'searchPlaceholder' => __('Search pages...'),
            'defaultSubtitle' => __('Page'),
            'noMatchTitle' => __('No matching pages'),
            'noMatchDescription' => __('Try a different search term or reconnect to refresh the list.'),
            'footerDescription' => __('Only selected LinkedIn pages will be added to your Channels workspace.'),
            'submitLabel' => __('Add selected'),
            'confirmOverLimit' => $this->confirmOverLimit,
            'pendingAcceptedCount' => $this->pendingAcceptedCount,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Select LinkedIn Pages'),
        ]);
    }

    protected function persistSelected(array $selectedIds = [])
    {
        abort_unless($this->planAccess()->canCreate(auth()->user()) && $this->planAccess()->canUseCapability(auth()->user(), 'linkedin_page'), 403);

        if ($this->pages === [] || $selectedIds === []) {
            return null;
        }

        $pages = $this->filteredPages(false)
            ->whereIn('id', $selectedIds)
            ->values();

        if ($pages->isEmpty()) {
            return null;
        }

        $existingIds = $this->existingSelectedIds($selectedIds);

        foreach ($pages as $page) {
            if (! in_array((string) $page['id'], $existingIds, true)
                && $this->planAccess()->hasReachedLimit(auth()->user(), 'linkedin_page')) {
                break;
            }

            SocialAccount::query()->updateOrCreate(
                [
                    'created_by_user_id' => \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()),
                    'provider_key' => (string) ($this->context['provider_key'] ?? 'linkedin_page'),
                    'capability_key' => 'linkedin_page',
                    'external_id' => (string) $page['id'],
                ],
                [
                    'display_name' => (string) $page['name'],
                    'username' => (string) ($page['username'] ?: Str::slug((string) $page['name'])),
                    'category' => 'Page',
                    'account_type' => 'oauth',
                    'profile_url' => (string) ($page['link'] ?? ''),
                    'avatar_url' => (string) ($page['avatar_url'] ?? ''),
                    'reconnect_url' => (string) ($this->context['reconnect_url'] ?? ''),
                    'access_token' => (string) ($page['access_token'] ?? $this->context['user_token'] ?? ''),
                    'refresh_token' => null,
                    'scopes' => (string) ($this->context['permissions'] ?? ''),
                    'auth_data' => [
                        'token_payload' => $this->context['token_payload'] ?? [],
                        'page_payload' => $page['payload'] ?? $page,
                    ],
                    'metadata' => [
                        'provider' => (string) ($this->context['provider_key'] ?? 'linkedin_page'),
                        'author_urn' => (string) ($page['author_urn'] ?? 'urn:li:organization:'.$page['id']),
                    ],
                    'is_active' => true,
                    'connected_at' => now(),
                ]
            );
        }

        session()->forget('linkedin_page_picker');

        return redirect()
            ->route('portal.channels', ['provider' => (string) ($this->context['provider_key'] ?? 'linkedin_page')])
            ->with('channels.flash', [
                'tone' => 'success',
                'message' => trans_choice(':count LinkedIn page connected.|:count LinkedIn pages connected.', $pages->count(), ['count' => $pages->count()]),
            ]);
    }

    protected function filteredPages(bool $applySearch = true): Collection
    {
        return collect($this->pages)
            ->filter(function (array $page) use ($applySearch): bool {
                if (! $applySearch || trim($this->search) === '') {
                    return true;
                }

                $needle = Str::lower(trim($this->search));

                return Str::contains(Str::lower((string) ($page['name'] ?? '')), $needle)
                    || Str::contains(Str::lower((string) ($page['username'] ?? '')), $needle);
            });
    }

    protected function existingSelectedIds(array $selectedIds): array
    {
        if ($selectedIds === []) {
            return [];
        }

        return SocialAccount::query()
            ->where('created_by_user_id', \Modules\AppTeams\Support\TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user()))
            ->where('provider_key', (string) ($this->context['provider_key'] ?? 'linkedin_page'))
            ->where('capability_key', 'linkedin_page')
            ->whereIn('external_id', $selectedIds)
            ->pluck('external_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    protected function planAccess(): ChannelPlanAccess
    {
        return app(ChannelPlanAccess::class);
    }
}
