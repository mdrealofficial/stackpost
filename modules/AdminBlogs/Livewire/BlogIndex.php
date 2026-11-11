<?php

namespace Modules\AdminBlogs\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogs\Models\Blog;

#[Title('Blogs')]
class BlogIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'category', except: 'all')]
    public string $categoryFilter = 'all';

    public array $selectedBlogIds = [];

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

    public function updatingCategoryFilter(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selectedBlogIds = $value
            ? $this->currentPageBlogIds()
            : [];
    }

    public function updatedSelectedBlogIds(): void
    {
        $this->selectPage = count($this->selectedBlogIds) > 0
            && count($this->selectedBlogIds) === count($this->currentPageBlogIds());
    }

    public function delete(int $blogId): void
    {
        $blog = Blog::query()->findOrFail($blogId);
        $metadata = ['title' => $blog->title, 'slug' => $blog->slug];

        $blog->delete();

        log_activity('admin.blogs.delete', 'Deleted a blog post.', [
            'subject_type' => Blog::class,
            'subject_id' => $blogId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Blog post deleted successfully.');
        $this->selectedBlogIds = array_values(array_diff($this->selectedBlogIds, [$blogId]));
        $this->selectPage = count($this->selectedBlogIds) > 0
            && count($this->selectedBlogIds) === count($this->currentPageBlogIds());
        $this->resetPageIfNeeded();
    }

    public function deleteSelected(): void
    {
        $blogIds = collect($this->selectedBlogIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($blogIds->isEmpty()) {
            return;
        }

        $blogs = Blog::query()->whereIn('id', $blogIds)->get(['id', 'title', 'slug']);

        if ($blogs->isEmpty()) {
            $this->resetSelection();

            return;
        }

        Blog::query()->whereIn('id', $blogs->pluck('id'))->delete();

        log_activity('admin.blogs.bulk-delete', 'Deleted multiple blog posts.', [
            'subject_type' => Blog::class,
            'subject_id' => null,
            'metadata' => [
                'count' => $blogs->count(),
                'blogs' => $blogs->map(fn (Blog $blog) => [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'slug' => $blog->slug,
                ])->values()->all(),
            ],
        ]);

        $this->statusMessage = trans_choice(
            '{1} :count blog post deleted successfully.|[2,*] :count blog posts deleted successfully.',
            $blogs->count(),
            ['count' => $blogs->count()]
        );

        $this->resetSelection();
        $this->resetPageIfNeeded();
    }

    public function publishSelected(): void
    {
        $this->updateSelectedStatus(true);
    }

    public function draftSelected(): void
    {
        $this->updateSelectedStatus(false);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->categoryFilter = 'all';
        $this->resetSelection();
        $this->resetPage();
    }

    public function render(): View
    {
        $blogs = $this->filteredQuery()->paginate(12);
        $allBlogs = Blog::query()->get(['status']);

        return view('adminblogs::livewire.index', [
            'blogs' => $blogs,
            'categories' => BlogCategory::query()->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => [
                'q' => $this->search,
                'status' => $this->statusFilter,
                'category' => $this->categoryFilter,
            ],
            'summary' => [
                'total' => $allBlogs->count(),
                'published' => $allBlogs->where('status', true)->count(),
                'draft' => $allBlogs->where('status', false)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Blogs'),
        ]);
    }

    protected function filteredQuery()
    {
        return Blog::query()
            ->with(['category:id,name,name_translations', 'tags:id,name,name_translations'])
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->when($this->categoryFilter !== 'all', fn ($builder) => $builder->where('blog_category_id', (int) $this->categoryFilter))
            ->orderByDesc('published_at')
            ->orderByDesc('changed')
            ->orderByDesc('id');
    }

    protected function currentPageBlogIds(): array
    {
        return $this->filteredQuery()
            ->paginate(12, ['id'], 'page', $this->getPage())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && $this->filteredQuery()->paginate(12, ['*'], 'page', $this->getPage())->isEmpty()) {
            $this->previousPage();
        }
    }

    protected function resetSelection(): void
    {
        $this->selectedBlogIds = [];
        $this->selectPage = false;
    }

    protected function updateSelectedStatus(bool $status): void
    {
        $blogIds = collect($this->selectedBlogIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($blogIds->isEmpty()) {
            return;
        }

        $now = time();

        Blog::query()
            ->whereIn('id', $blogIds)
            ->update([
                'status' => $status,
                'published_at' => $status ? $now : null,
                'changed' => $now,
            ]);

        $this->statusMessage = $status
            ? trans_choice('{1} :count post published.|[2,*] :count posts published.', $blogIds->count(), ['count' => $blogIds->count()])
            : trans_choice('{1} :count post moved to draft.|[2,*] :count posts moved to draft.', $blogIds->count(), ['count' => $blogIds->count()]);

        $this->resetSelection();
    }
}
