<?php

namespace Modules\AdminBlogTags\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminBlogTags\Models\BlogTag;

#[Title('Blog Tags')]
class TagIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'range', except: 'all')]
    public string $dateFilter = 'all';

    #[Url(as: 'sort', except: 'recent')]
    public string $sortFilter = 'recent';

    #[Url(as: 'show', except: '15')]
    public string $perPage = '15';

    public array $selectedTagIds = [];

    public bool $selectPage = false;

    public ?string $statusMessage = null;

    public function updatingSearch(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatingSortFilter(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selectedTagIds = $value
            ? $this->currentPageTagIds()
            : [];
    }

    public function updatedSelectedTagIds(): void
    {
        $this->selectPage = count($this->selectedTagIds) > 0
            && count($this->selectedTagIds) === count($this->currentPageTagIds());
    }

    public function delete(int $tagId): void
    {
        $tag = BlogTag::query()->findOrFail($tagId);
        $metadata = ['name' => $tag->name, 'slug' => $tag->slug];

        $tag->delete();

        log_activity('admin.blog-tags.delete', 'Deleted a blog tag.', [
            'subject_type' => BlogTag::class,
            'subject_id' => $tagId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Blog tag deleted successfully.');
        $this->selectedTagIds = array_values(array_diff($this->selectedTagIds, [$tagId]));
        $this->selectPage = count($this->selectedTagIds) > 0
            && count($this->selectedTagIds) === count($this->currentPageTagIds());
        $this->resetPageIfNeeded();
    }

    public function deleteSelected(): void
    {
        $tagIds = collect($this->selectedTagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($tagIds->isEmpty()) {
            return;
        }

        $tags = BlogTag::query()->whereIn('id', $tagIds)->get(['id', 'name', 'slug']);

        if ($tags->isEmpty()) {
            $this->resetSelection();

            return;
        }

        BlogTag::query()->whereIn('id', $tags->pluck('id'))->delete();

        log_activity('admin.blog-tags.bulk-delete', 'Deleted multiple blog tags.', [
            'subject_type' => BlogTag::class,
            'subject_id' => null,
            'metadata' => [
                'count' => $tags->count(),
                'tags' => $tags->map(fn (BlogTag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])->values()->all(),
            ],
        ]);

        $this->statusMessage = trans_choice(
            '{1} :count blog tag deleted successfully.|[2,*] :count blog tags deleted successfully.',
            $tags->count(),
            ['count' => $tags->count()]
        );

        $this->resetSelection();
        $this->resetPageIfNeeded();
    }

    public function enableSelected(): void
    {
        $this->updateSelectedStatus(true);
    }

    public function disableSelected(): void
    {
        $this->updateSelectedStatus(false);
    }

    public function render(): View
    {
        $tags = $this->filteredQuery()->paginate((int) $this->perPage);
        $allTags = BlogTag::query()->get(['status']);

        return view('adminblogtags::livewire.index', [
            'tags' => $tags,
            'filterOptions' => [
                'dateRanges' => $this->dateRangeOptions(),
                'sorts' => $this->sortOptions(),
                'perPage' => $this->perPageOptions(),
            ],
            'summary' => [
                'total' => $allTags->count(),
                'enabled' => $allTags->where('status', true)->count(),
                'disabled' => $allTags->where('status', false)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Blog Tags'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && $this->filteredQuery()
                ->paginate((int) $this->perPage, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->dateFilter = 'all';
        $this->sortFilter = 'recent';
        $this->perPage = '15';
        $this->resetSelection();
        $this->resetPage();
    }

    protected function filteredQuery()
    {
        $query = BlogTag::query()
            ->withCount('blogs')
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter));

        $now = time();

        match ($this->dateFilter) {
            'today' => $query->where('created', '>=', strtotime('today')),
            '7d' => $query->where('created', '>=', $now - (7 * 86400)),
            '30d' => $query->where('created', '>=', $now - (30 * 86400)),
            '90d' => $query->where('created', '>=', $now - (90 * 86400)),
            default => null,
        };

        match ($this->sortFilter) {
            'oldest' => $query->orderBy('created')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'posts_desc' => $query->orderByDesc('blogs_count')->orderByDesc('id'),
            'posts_asc' => $query->orderBy('blogs_count')->orderByDesc('id'),
            default => $query->orderByDesc('changed')->orderByDesc('id'),
        };

        return $query;
    }

    protected function dateRangeOptions(): array
    {
        return [
            ['value' => 'all', 'label' => __('Any time')],
            ['value' => 'today', 'label' => __('Today')],
            ['value' => '7d', 'label' => __('Last 7 days')],
            ['value' => '30d', 'label' => __('Last 30 days')],
            ['value' => '90d', 'label' => __('Last 90 days')],
        ];
    }

    protected function sortOptions(): array
    {
        return [
            ['value' => 'recent', 'label' => __('Newest first')],
            ['value' => 'oldest', 'label' => __('Oldest first')],
            ['value' => 'name_asc', 'label' => __('Name A-Z')],
            ['value' => 'name_desc', 'label' => __('Name Z-A')],
            ['value' => 'posts_desc', 'label' => __('Most posts')],
            ['value' => 'posts_asc', 'label' => __('Fewest posts')],
        ];
    }

    protected function perPageOptions(): array
    {
        return [
            ['value' => '15', 'label' => __('15 rows')],
            ['value' => '30', 'label' => __('30 rows')],
            ['value' => '50', 'label' => __('50 rows')],
        ];
    }

    protected function currentPageTagIds(): array
    {
        return $this->filteredQuery()
            ->paginate((int) $this->perPage, ['id'], 'page', $this->getPage())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resetSelection(): void
    {
        $this->selectedTagIds = [];
        $this->selectPage = false;
    }

    protected function updateSelectedStatus(bool $status): void
    {
        $tagIds = collect($this->selectedTagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($tagIds->isEmpty()) {
            return;
        }

        BlogTag::query()->whereIn('id', $tagIds)->update([
            'status' => $status,
            'changed' => time(),
        ]);

        $this->statusMessage = $status
            ? trans_choice('{1} :count tag enabled.|[2,*] :count tags enabled.', $tagIds->count(), ['count' => $tagIds->count()])
            : trans_choice('{1} :count tag disabled.|[2,*] :count tags disabled.', $tagIds->count(), ['count' => $tagIds->count()]);

        $this->resetSelection();
    }
}
