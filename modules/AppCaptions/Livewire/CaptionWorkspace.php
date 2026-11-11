<?php

namespace Modules\AppCaptions\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppCaptions\Models\CaptionLibraryItem;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Captions')]
class CaptionWorkspace extends Component
{
    use WithPagination;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingSourceFilter(): void { $this->resetPage(); }

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'source')]
    public string $sourceFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public ?int $editingId = null;
    public bool $editorOpen = false;
    public string $name = '';
    public string $sourceType = 'manual';
    public string $captionStatus = 'active';
    public string $content = '';
    public string $tags = '';
    public string $notes = '';

    public function mount(Request $request): void
    {
        abort_unless($this->captionsEnabled(), 404);

        $editId = $request->integer('edit');
        if ($editId > 0) {
            $this->editCaption($editId);
        }
    }

    public function save(AiContentStudioService $studio): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('post.create'), 403);
        abort_unless($this->captionsEnabled(), 404);

        $this->sourceType = $this->editingId ? (string)$this->captionQuery()->findOrFail($this->editingId)->source_type : 'manual';
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'sourceType' => ['required', Rule::in(['ai', 'manual'])],
            'captionStatus' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'content' => ['required', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'sourceType' => __('source'),
            'captionStatus' => __('status'),
        ]);

        $user = auth()->user();
        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $tagList = collect(explode(',', (string) ($validated['tags'] ?? '')))
            ->map(function ($tag) {
                $value = Str::of((string) $tag)->ascii()->lower()->trim()->value();
                $value = preg_replace('/[^a-z0-9\\-\\s]+/i', '', $value) ?? '';
                $value = preg_replace('/\\s+/', '-', $value) ?? '';

                return trim($value, '-');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($this->editingId) {
            $caption = $this->captionQuery()->findOrFail($this->editingId);
            $caption->update([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], $workspaceOwnerUserId, $caption->id),
                'source_type' => $validated['sourceType'],
                'status' => $validated['captionStatus'],
                'content' => $validated['content'],
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'tags' => $tagList,
            ]);

            $studio->annotateCaption($caption);
            $this->dispatch('app-toast', type: 'success', message: __('Caption updated successfully.'));
        } else {
            $caption = CaptionLibraryItem::query()->create([
                'owner_user_id' => $workspaceOwnerUserId,
                'team_id' => $this->currentTeamId($user),
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], $workspaceOwnerUserId),
                'source_type' => $validated['sourceType'],
                'status' => $validated['captionStatus'],
                'content' => $validated['content'],
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'tags' => $tagList,
            ]);

            $studio->annotateCaption($caption);
            $this->dispatch('app-toast', type: 'success', message: __('Caption saved successfully.'));
        }

        $this->editorOpen = false;
        $this->resetEditorState();
    }

    public function editCaption(int $captionId): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('post.create'), 403);
        abort_unless($this->captionsEnabled(), 404);

        $caption = $this->captionQuery()->findOrFail($captionId);

        $this->editingId = (int) $caption->id;
        $this->name = (string) $caption->name;
        $this->sourceType = (string) $caption->source_type;
        $this->captionStatus = (string) $caption->status;
        $this->content = (string) $caption->content;
        $this->notes = (string) ($caption->notes ?? '');
        $this->tags = implode(', ', $caption->tags ?? []);
        $this->editorOpen = true;

        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteCaption(int $captionId): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('post.create'), 403);
        abort_unless($this->captionsEnabled(), 404);

        $caption = $this->captionQuery()->findOrFail($captionId);
        $wasEditing = $this->editingId === (int) $caption->id;
        $caption->delete();

        if ($wasEditing) {
            $this->resetEditor();
        }

        $this->dispatch('app-toast', type: 'success', message: __('Caption deleted successfully.'));
    }

    public function resetEditor(): void
    {
        $this->editorOpen = false;
        $this->resetEditorState();
    }

    public function openCreateEditor(): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('post.create'), 403);
        $this->editorOpen = true;
        $this->resetEditorState();
    }

    protected function resetEditorState(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->sourceType = 'manual';
        $this->captionStatus = 'active';
        $this->content = '';
        $this->tags = '';
        $this->notes = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function resetFilters(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->sourceFilter = '';
        $this->statusFilter = '';
    }

    public function render(): View
    {
        abort_unless($this->captionsEnabled(), 404);

        $baseQuery = $this->captionQuery();

        $captions = (clone $baseQuery)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($nested): void {
                    $nested
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('content', 'like', '%'.$this->search.'%')
                        ->orWhere('notes', 'like', '%'.$this->search.'%')->orWhere('tags', 'like', '%'.$this->search.'%');
                });
            })
            ->when(in_array($this->sourceFilter, ['manual', 'ai'], true), fn ($query) => $query->where('source_type', $this->sourceFilter))
            ->when(in_array($this->statusFilter, ['active', 'draft', 'archived'], true), fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(18);

        return view('appcaptions::livewire.index', [
            'captions' => $captions,
            'canManage' => \Modules\AppTeams\Support\PortalLibraryAccess::canManage('post.create'),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'ai' => (clone $baseQuery)->where('source_type', 'ai')->count(),
                'manual' => (clone $baseQuery)->where('source_type', 'manual')->count(),
                'active' => (clone $baseQuery)->where('status', 'active')->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Captions'),
        ]);
    }

    protected function captionQuery()
    {
        return \Modules\AppTeams\Support\PortalLibraryAccess::query(CaptionLibraryItem::query());
    }

    protected function currentTeamId($user): ?int
    {
        return TeamWorkspaceAccess::activeTeam($user)?->id;
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function uniqueSlug(string $value, int $userId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'caption';
        $slug = $baseSlug;
        $counter = 2;

        while (CaptionLibraryItem::query()
            ->ownedBy($userId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    protected function captionsEnabled(): bool
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return ! $planOwner?->plan || $planOwner->hasPlanFeature('captions');
    }
}
