<?php

namespace Modules\AdminBlogCategories\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;

class AdminBlogCategoryController extends Controller
{
    public function __construct(
        protected MultilingualContentGenerator $contentGenerator,
    ) {}

    public function index(Request $request): View
    {
        $query = BlogCategory::query()
            ->withCount('blogs')
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $search = trim((string) $request->string('q'));

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', (int) $request->input('status')))
            ->orderBy('sort_order')
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $categories = $query->paginate(15)->withQueryString();
        $allCategories = BlogCategory::query()->get(['status']);

        return view('adminblogcategories::index', [
            'categories' => $categories,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->input('status', 'all'),
            ],
            'summary' => [
                'total' => $allCategories->count(),
                'enabled' => $allCategories->where('status', true)->count(),
                'disabled' => $allCategories->where('status', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('adminblogcategories::create', [
            'category' => new BlogCategory(['status' => true, 'color' => '#0f766e']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = BlogCategory::query()->create($this->validated($request));

        log_activity('admin.blog-categories.create', 'Created a blog category.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => $category->id,
            'metadata' => [
                'name' => $category->name,
                'slug' => $category->slug,
            ],
        ]);

        return redirect()->route('admin-blog-categories.index')->with('status', __('Blog category created successfully.'));
    }

    public function edit(BlogCategory $blogCategory): View
    {
        return view('adminblogcategories::edit', [
            'category' => $blogCategory,
        ]);
    }

    public function update(Request $request, BlogCategory $blogCategory): RedirectResponse
    {
        $blogCategory->update($this->validated($request, $blogCategory));

        log_activity('admin.blog-categories.update', 'Updated a blog category.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => $blogCategory->id,
            'metadata' => [
                'name' => $blogCategory->name,
                'slug' => $blogCategory->slug,
            ],
        ]);

        return redirect()->route('admin-blog-categories.index')->with('status', __('Blog category updated successfully.'));
    }

    public function destroy(BlogCategory $blogCategory): RedirectResponse
    {
        $metadata = ['name' => $blogCategory->name, 'slug' => $blogCategory->slug];
        $blogCategory->delete();

        log_activity('admin.blog-categories.delete', 'Deleted a blog category.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => $blogCategory->id,
            'metadata' => $metadata,
        ]);

        return redirect()->route('admin-blog-categories.index')->with('status', __('Blog category deleted successfully.'));
    }

    protected function validated(Request $request, ?BlogCategory $category = null): array
    {
        $defaultLocale = $this->defaultLocale();

        $validated = $request->validate([
            'name_translations' => ['required', 'array'],
            'name_translations.*' => ['nullable', 'string', 'max:255'],
            'description_translations' => ['nullable', 'array'],
            'description_translations.*' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_categories', 'slug')->ignore($category?->id)],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
            'auto_translate_missing' => ['nullable', 'boolean'],
        ]);

        $nameTranslations = BlogCategory::cleanTranslations($validated['name_translations'] ?? [], 255);

        if (($nameTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "name_translations.{$defaultLocale}" => __('The default language name is required.'),
            ]);
        }

        $descriptionTranslations = BlogCategory::cleanTranslations($validated['description_translations'] ?? []);

        if ($request->boolean('auto_translate_missing')) {
            $generated = $this->contentGenerator->completeFields([
                'name' => $nameTranslations,
                'description' => $descriptionTranslations,
            ], [
                'name' => 255,
            ]);

            $nameTranslations = $generated['name'];
            $descriptionTranslations = $generated['description'];
        }

        $defaultName = $nameTranslations[$defaultLocale];

        return [
            'id_secure' => $category?->id_secure ?: Str::random(32),
            'name' => $defaultName,
            'name_translations' => $nameTranslations,
            'description' => $descriptionTranslations[$defaultLocale] ?? null,
            'description_translations' => $descriptionTranslations,
            'slug' => Str::slug(trim($validated['slug'] ?: $defaultName)),
            'icon' => trim((string) ($validated['icon'] ?? '')) ?: null,
            'color' => trim((string) $validated['color']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'status' => (bool) $validated['status'],
            'changed' => time(),
            'created' => $category?->created ?: time(),
        ];
    }

    protected function defaultLocale(): string
    {
        return function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', 'en');
    }
}
