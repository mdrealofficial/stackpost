<?php

namespace Modules\AppPublishing\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Publishing Labels')]
class PublishingLabels extends Component
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
            'form.name' => ['required', 'string', 'max:80'],
            'form.status' => ['required', 'in:active,draft,archived'],
            'form.color' => ['required', 'string', 'max:24'],
            'form.description' => ['nullable', 'string', 'max:500'],
        ])['form'];

        $userId = $this->workspaceOwnerUserId();
        $label = $this->editingId
            ? PublishingLabel::query()->ownedBy($userId)->findOrFail($this->editingId)
            : new PublishingLabel(['owner_user_id' => $userId]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug !== '' ? $slug : 'label';
        $suffix = 1;

        while (PublishingLabel::query()
            ->ownedBy($userId)
            ->where('slug', $slug = $suffix === 1 ? $baseSlug : $baseSlug.'-'.$suffix)
            ->when($label->exists, fn ($query) => $query->whereKeyNot($label->id))
            ->exists()) {
            $suffix++;
        }

        $label->fill([
            'name' => trim((string) $validated['name']),
            'slug' => $slug,
            'status' => $validated['status'],
            'color' => trim((string) $validated['color']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
        ])->save();

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Label saved'),
            message: $this->editingId
                ? __('The publishing label has been updated.')
                : __('A new publishing label has been created.')
        );

        $this->editingId = null;
        $this->resetForm();
    }

    public function edit(int $labelId): void
    {
        $label = PublishingLabel::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->findOrFail($labelId);

        $this->editingId = $label->id;
        $this->form = [
            'name' => $label->name,
            'status' => $label->status,
            'color' => $label->color,
            'description' => $label->description ?? '',
        ];
        $this->resetErrorBag();
    }

    public function delete(int $labelId): void
    {
        $label = PublishingLabel::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->findOrFail($labelId);

        $label->delete();

        if ($this->editingId === $labelId) {
            $this->editingId = null;
            $this->resetForm();
        }

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Label deleted'),
            message: __('The publishing label has been removed.')
        );
    }

    public function duplicate(int $labelId): void
    {
        $source = PublishingLabel::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->findOrFail($labelId);

        $this->editingId = null;
        $this->form = [
            'name' => $source->name.' Copy',
            'status' => 'draft',
            'color' => $source->color,
            'description' => $source->description ?? '',
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

        $labels = PublishingLabel::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($query): void {
                $needle = trim($this->search);
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('name', 'like', '%'.$needle.'%')
                        ->orWhere('description', 'like', '%'.$needle.'%');
                });
            })
            ->orderByRaw("case when status = 'active' then 0 when status = 'draft' then 1 else 2 end")
            ->orderBy('name')
            ->get();

        return view('apppublishing::livewire.labels', [
            'labels' => $labels,
            'summary' => [
                'total' => $labels->count(),
                'active' => $labels->where('status', 'active')->count(),
                'draft' => $labels->where('status', 'draft')->count(),
            ],
            'statusOptions' => [
                ['value' => 'active', 'label' => __('Active')],
                ['value' => 'draft', 'label' => __('Draft')],
                ['value' => 'archived', 'label' => __('Archived')],
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Publishing Labels'),
        ]);
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'status' => 'active',
            'color' => '#2563eb',
            'description' => '',
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
