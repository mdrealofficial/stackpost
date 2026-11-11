<?php

namespace Modules\AdminBlogTags\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;

class AdminBlogTagController extends Controller
{
    public function __construct(
        protected MultilingualContentGenerator $contentGenerator,
    ) {}

    public function index(Request $request): View
    {
        $query = BlogTag::query()
            ->withCount('blogs')
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $search = trim((string) $request->string('q'));

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', (int) $request->input('status')))
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $tags = $query->paginate(15)->withQueryString();
        $allTags = BlogTag::query()->get(['status']);

        return view('adminblogtags::index', [
            'tags' => $tags,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->input('status', 'all'),
            ],
            'summary' => [
                'total' => $allTags->count(),
                'enabled' => $allTags->where('status', true)->count(),
                'disabled' => $allTags->where('status', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('adminblogtags::create', [
            'tag' => new BlogTag(['status' => true, 'color' => '#0f766e']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tag = BlogTag::query()->create($this->validated($request));

        log_activity('admin.blog-tags.create', 'Created a blog tag.', [
            'subject_type' => BlogTag::class,
            'subject_id' => $tag->id,
            'metadata' => [
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
        ]);

        return redirect()->route('admin-blog-tags.index')->with('status', __('Blog tag created successfully.'));
    }

    public function edit(BlogTag $blogTag): View
    {
        return view('adminblogtags::edit', [
            'tag' => $blogTag,
        ]);
    }

    public function update(Request $request, BlogTag $blogTag): RedirectResponse
    {
        $blogTag->update($this->validated($request, $blogTag));

        log_activity('admin.blog-tags.update', 'Updated a blog tag.', [
            'subject_type' => BlogTag::class,
            'subject_id' => $blogTag->id,
            'metadata' => [
                'name' => $blogTag->name,
                'slug' => $blogTag->slug,
            ],
        ]);

        return redirect()->route('admin-blog-tags.index')->with('status', __('Blog tag updated successfully.'));
    }

    public function destroy(BlogTag $blogTag): RedirectResponse
    {
        $metadata = ['name' => $blogTag->name, 'slug' => $blogTag->slug];
        $blogTag->delete();

        log_activity('admin.blog-tags.delete', 'Deleted a blog tag.', [
            'subject_type' => BlogTag::class,
            'subject_id' => $blogTag->id,
            'metadata' => $metadata,
        ]);

        return redirect()->route('admin-blog-tags.index')->with('status', __('Blog tag deleted successfully.'));
    }

    protected function validated(Request $request, ?BlogTag $tag = null): array
    {
        $defaultLocale = $this->defaultLocale();

        $validated = $request->validate([
            'name_translations' => ['required', 'array'],
            'name_translations.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_tags', 'slug')->ignore($tag?->id)],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:32'],
            'status' => ['required', 'boolean'],
            'auto_translate_missing' => ['nullable', 'boolean'],
        ]);

        $nameTranslations = BlogTag::cleanTranslations($validated['name_translations'] ?? [], 255);

        if (($nameTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "name_translations.{$defaultLocale}" => __('The default language name is required.'),
            ]);
        }

        if ($request->boolean('auto_translate_missing')) {
            $generated = $this->contentGenerator->completeFields([
                'name' => $nameTranslations,
            ], [
                'name' => 255,
            ]);

            $nameTranslations = $generated['name'];
        }

        $defaultName = $nameTranslations[$defaultLocale];

        return [
            'id_secure' => $tag?->id_secure ?: Str::random(32),
            'name' => $defaultName,
            'name_translations' => $nameTranslations,
            'slug' => Str::slug(trim($validated['slug'] ?: $defaultName)),
            'icon' => trim((string) ($validated['icon'] ?? '')) ?: null,
            'color' => trim((string) $validated['color']),
            'status' => (bool) $validated['status'],
            'changed' => time(),
            'created' => $tag?->created ?: time(),
        ];
    }

    protected function defaultLocale(): string
    {
        return function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', 'en');
    }
}
