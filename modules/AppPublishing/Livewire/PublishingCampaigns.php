<?php

namespace Modules\AppPublishing\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Publishing Campaigns')]
class PublishingCampaigns extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.status' => ['required', 'in:active,draft,completed,archived'],
            'form.color' => ['required', 'string', 'max:24'],
            'form.starts_on' => ['nullable', 'date'],
            'form.ends_on' => ['nullable', 'date', 'after_or_equal:form.starts_on'],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'form.name' => __('campaign name'),
            'form.status' => __('status'),
            'form.color' => __('accent color'),
            'form.starts_on' => __('start date'),
            'form.ends_on' => __('end date'),
            'form.description' => __('description'),
            'form.notes' => __('internal notes'),
        ])['form'];

        $userId = $this->workspaceOwnerUserId();
        $campaign = $this->editingId
            ? PublishingCampaign::query()->ownedBy($userId)->findOrFail($this->editingId)
            : new PublishingCampaign(['owner_user_id' => $userId]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug !== '' ? $slug : 'campaign';
        $suffix = 1;

        while (PublishingCampaign::query()
            ->ownedBy($userId)
            ->where('slug', $slug = $suffix === 1 ? $baseSlug : $baseSlug.'-'.$suffix)
            ->when($campaign->exists, fn ($query) => $query->whereKeyNot($campaign->id))
            ->exists()) {
            $suffix++;
        }

        $campaign->fill([
            'name' => trim((string) $validated['name']),
            'slug' => $slug,
            'status' => $validated['status'],
            'color' => trim((string) $validated['color']),
            'starts_on' => $validated['starts_on'] ?: null,
            'ends_on' => $validated['ends_on'] ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ])->save();

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Campaign saved'),
            message: $this->editingId
                ? __('The publishing campaign has been updated.')
                : __('A new publishing campaign has been created.')
        );

        $this->editingId = null;
        $this->resetForm();
    }

    public function edit(int $campaignId): void
    {
        $campaign = PublishingCampaign::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->findOrFail($campaignId);

        $this->editingId = $campaign->id;
        $this->form = [
            'name' => $campaign->name,
            'status' => $campaign->status,
            'color' => $campaign->color,
            'starts_on' => optional($campaign->starts_on)->toDateString() ?? '',
            'ends_on' => optional($campaign->ends_on)->toDateString() ?? '',
            'description' => $campaign->description ?? '',
            'notes' => $campaign->notes ?? '',
        ];
        $this->resetErrorBag();
    }

    public function delete(int $campaignId): void
    {
        $campaign = PublishingCampaign::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->findOrFail($campaignId);

        $campaign->delete();

        if ($this->editingId === $campaignId) {
            $this->editingId = null;
            $this->resetForm();
        }

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Campaign deleted'),
            message: __('The publishing campaign has been removed.')
        );
    }

    public function duplicate(int $campaignId): void
    {
        $source = PublishingCampaign::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->findOrFail($campaignId);

        $this->editingId = null;
        $this->form = [
            'name' => $source->name.' Copy',
            'status' => 'draft',
            'color' => $source->color,
            'starts_on' => optional($source->starts_on)->toDateString() ?? '',
            'ends_on' => optional($source->ends_on)->toDateString() ?? '',
            'description' => $source->description ?? '',
            'notes' => $source->notes ?? '',
        ];
        $this->resetErrorBag();
    }

    public function createNew(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function render(): View
    {
        abort_unless($this->publishingEnabled(), 404);

        $campaigns = PublishingCampaign::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($query): void {
                $needle = trim($this->search);
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('name', 'like', '%'.$needle.'%')
                        ->orWhere('description', 'like', '%'.$needle.'%')
                        ->orWhere('notes', 'like', '%'.$needle.'%');
                });
            })
            ->orderByRaw("case when status = 'active' then 0 when status = 'draft' then 1 when status = 'completed' then 2 else 3 end")
            ->orderByDesc('updated_at')
            ->get();

        return view('apppublishing::livewire.campaigns', [
            'campaigns' => $campaigns,
            'summary' => [
                'total' => $campaigns->count(),
                'active' => $campaigns->where('status', 'active')->count(),
                'draft' => $campaigns->where('status', 'draft')->count(),
                'completed' => $campaigns->where('status', 'completed')->count(),
            ],
            'statusOptions' => [
                ['value' => 'active', 'label' => __('Active')],
                ['value' => 'draft', 'label' => __('Draft')],
                ['value' => 'completed', 'label' => __('Completed')],
                ['value' => 'archived', 'label' => __('Archived')],
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Publishing Campaigns'),
        ]);
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'status' => 'active',
            'color' => '#2563eb',
            'starts_on' => '',
            'ends_on' => '',
            'description' => '',
            'notes' => '',
        ];
        $this->resetErrorBag();
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function publishingEnabled(): bool
    {
        return TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'publishing');
    }
}
