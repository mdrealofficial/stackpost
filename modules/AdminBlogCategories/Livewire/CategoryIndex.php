<?php

namespace Modules\AdminBlogCategories\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminBlogCategories\Models\BlogCategory;

#[Title('Blog Categories')]
class CategoryIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public array $selectedCategoryIds = [];

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

    public function updatedSelectPage(bool $value): void
    {
        $this->selectedCategoryIds = $value
            ? $this->currentPageCategoryIds()
            : [];
    }

    public function updatedSelectedCategoryIds(): void
    {
        $this->selectPage = count($this->selectedCategoryIds) > 0
            && count($this->selectedCategoryIds) === count($this->currentPageCategoryIds());
    }

    public function delete(int $categoryId): void
    {
        $category = BlogCategory::query()->findOrFail($categoryId);
        $metadata = ['name' => $category->name, 'slug' => $category->slug];

        $category->delete();

        log_activity('admin.blog-categories.delete', 'Deleted a blog category.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => $categoryId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Blog category deleted successfully.');
        $this->selectedCategoryIds = array_values(array_diff($this->selectedCategoryIds, [$categoryId]));
        $this->selectPage = count($this->selectedCategoryIds) > 0
            && count($this->selectedCategoryIds) === count($this->currentPageCategoryIds());
        $this->resetPageIfNeeded();
    }

    public function deleteSelected(): void
    {
        $categoryIds = collect($this->selectedCategoryIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return;
        }

        $categories = BlogCategory::query()
            ->whereIn('id', $categoryIds)
            ->get(['id', 'name', 'slug']);

        if ($categories->isEmpty()) {
            $this->resetSelection();

            return;
        }

        BlogCategory::query()->whereIn('id', $categories->pluck('id'))->delete();

        log_activity('admin.blog-categories.bulk-delete', 'Deleted multiple blog categories.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => null,
            'metadata' => [
                'count' => $categories->count(),
                'categories' => $categories->map(fn (BlogCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values()->all(),
            ],
        ]);

        $this->statusMessage = trans_choice(
            '{1} :count blog category deleted successfully.|[2,*] :count blog categories deleted successfully.',
            $categories->count(),
            ['count' => $categories->count()]
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

    public function reorder(array $orderedIds): void
    {
        $orderedIds = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($orderedIds->isEmpty()) {
            return;
        }

        $categories = BlogCategory::query()
            ->whereIn('id', $orderedIds)
            ->get(['id', 'sort_order']);

        if ($categories->count() !== $orderedIds->count()) {
            return;
        }

        $baseOrder = (int) ($categories->min('sort_order') ?? 0);

        foreach ($orderedIds as $offset => $categoryId) {
            BlogCategory::query()
                ->whereKey($categoryId)
                ->update([
                    'sort_order' => $baseOrder + $offset,
                    'changed' => time(),
                ]);
        }

        log_activity('admin.blog-categories.reorder', 'Reordered blog categories.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => null,
            'metadata' => [
                'ordered_ids' => $orderedIds->all(),
            ],
        ]);

        $this->statusMessage = __('Blog category order updated successfully.');
        $this->resetSelection();
    }

    public function render(): View
    {
        $query = $this->categoriesQuery()
            ->orderBy('sort_order')
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $categories = $query->paginate(15);
        $allCategories = BlogCategory::query()->get(['status']);

        return view('adminblogcategories::livewire.index', [
            'categories' => $categories,
            'summary' => [
                'total' => $allCategories->count(),
                'enabled' => $allCategories->where('status', true)->count(),
                'disabled' => $allCategories->where('status', false)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Blog Categories'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && $this->categoriesQuery()
                ->paginate(15, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }

    protected function categoriesQuery()
    {
        return BlogCategory::query()
            ->withCount('blogs')
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter));
    }

    protected function currentPageCategoryIds(): array
    {
        return $this->categoriesQuery()
            ->orderBy('sort_order')
            ->orderByDesc('changed')
            ->orderByDesc('id')
            ->paginate(15, ['id'], 'page', $this->getPage())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resetSelection(): void
    {
        $this->selectedCategoryIds = [];
        $this->selectPage = false;
    }

    protected function updateSelectedStatus(bool $status): void
    {
        $categoryIds = collect($this->selectedCategoryIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return;
        }

        BlogCategory::query()->whereIn('id', $categoryIds)->update([
            'status' => $status,
            'changed' => time(),
        ]);

        $this->statusMessage = $status
            ? trans_choice('{1} :count category enabled.|[2,*] :count categories enabled.', $categoryIds->count(), ['count' => $categoryIds->count()])
            : trans_choice('{1} :count category disabled.|[2,*] :count categories disabled.', $categoryIds->count(), ['count' => $categoryIds->count()]);

        $this->resetSelection();
    }
}
