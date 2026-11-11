<?php

namespace Modules\AdminBlogs\Http\Controllers;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogs\Models\Blog;
use Modules\AdminBlogs\Support\BlogAiContentGenerator;
use Modules\AdminBlogs\Support\BlogAiImageGenerator;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AppFiles\Models\AppFile;

class AdminBlogController extends Controller
{
    public function __construct(
        protected MultilingualContentGenerator $contentGenerator,
        protected BlogAiContentGenerator $blogAiContentGenerator,
        protected BlogAiImageGenerator $blogAiImageGenerator,
        protected StorageDriverManager $storageDriverManager,
    ) {}

    public function index(Request $request): View
    {
        $query = Blog::query()
            ->with(['category:id,name,name_translations', 'tags:id,name,name_translations'])
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $search = trim((string) $request->string('q'));

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', (int) $request->input('status')))
            ->when($request->filled('category') && $request->input('category') !== 'all', fn ($builder) => $builder->where('blog_category_id', (int) $request->input('category')))
            ->orderByDesc('published_at')
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $blogs = $query->paginate(12)->withQueryString();
        $allBlogs = Blog::query()->get(['status']);

        return view('adminblogs::index', [
            'blogs' => $blogs,
            'categories' => BlogCategory::query()->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->input('status', 'all'),
                'category' => $request->input('category', 'all'),
            ],
            'summary' => [
                'total' => $allBlogs->count(),
                'published' => $allBlogs->where('status', true)->count(),
                'draft' => $allBlogs->where('status', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('adminblogs::create', $this->formData(new Blog(['status' => true])));
    }

    public function preview(Blog $blog): View
    {
        $blog->load(['category:id,name,name_translations', 'tags:id,name,name_translations']);

        return view('adminblogs::preview', [
            'blog' => $blog,
        ]);
    }

    public function searchTags(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q'));

        $tags = BlogTag::query()
            ->where('status', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (BlogTag $tag) => [
                'key' => (string) $tag->id,
                'label' => trim((string) ($tag->nameForLocale() ?: $tag->name)),
            ])
            ->values();

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function createTag(Request $request): \Illuminate\Http\JsonResponse
    {
        $defaultLocale = $this->defaultLocale();
        $name = trim((string) $request->input('name'));

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => __('The tag name is invalid.'),
            ]);
        }

        $existing = BlogTag::query()
            ->where('slug', $slug)
            ->orWhere('name', $name)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => __('Tag already exists.'),
                'data' => [
                    'key' => (string) $existing->id,
                    'label' => trim((string) ($existing->nameForLocale() ?: $existing->name)),
                ],
            ]);
        }

        $tag = BlogTag::query()->create([
            'id_secure' => Str::random(32),
            'name' => $name,
            'name_translations' => [$defaultLocale => $name],
            'slug' => $slug,
            'status' => true,
            'color' => '#0f766e',
            'icon' => 'fa-light fa-hashtag',
            'changed' => time(),
            'created' => time(),
        ]);

        return response()->json([
            'message' => __('Tag created successfully.'),
            'data' => [
                'key' => (string) $tag->id,
                'label' => trim((string) ($tag->nameForLocale() ?: $tag->name)),
            ],
        ]);
    }

    public function createCategory(Request $request): \Illuminate\Http\JsonResponse
    {
        $defaultLocale = $this->defaultLocale();
        $name = trim((string) $request->input('name'));

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => __('The category name is invalid.'),
            ]);
        }

        $existing = BlogCategory::query()
            ->where('slug', $slug)
            ->orWhere('name', $name)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => __('Category already exists.'),
                'data' => [
                    'key' => (string) $existing->id,
                    'label' => $existing->nameForLocale(),
                ],
            ]);
        }

        $category = BlogCategory::query()->create([
            'id_secure' => Str::random(32),
            'name' => $name,
            'name_translations' => [$defaultLocale => $name],
            'description' => null,
            'description_translations' => [],
            'slug' => $slug,
            'icon' => 'fa-light fa-folder-tree',
            'color' => '#0f766e',
            'sort_order' => 0,
            'status' => true,
            'changed' => time(),
            'created' => time(),
        ]);

        return response()->json([
            'message' => __('Category created successfully.'),
            'data' => [
                'key' => (string) $category->id,
                'label' => $category->nameForLocale(),
            ],
        ]);
    }

    public function generateAiContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:5', 'max:4000'],
            'locale' => ['nullable', 'string', 'max:12'],
        ]);

        try {
            $payload = $this->blogAiContentGenerator->generate(
                trim((string) $validated['prompt']),
                strtolower((string) ($validated['locale'] ?? $this->defaultLocale()))
            );
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'prompt' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('AI content generated successfully.'),
            'data' => $payload,
        ]);
    }

    public function generateAiImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:5', 'max:2000'],
            'folder' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $parent = filled($validated['folder'] ?? null)
            ? AppFile::query()->ownedBy($user)->where('is_folder', true)->where('id_secure', $validated['folder'])->firstOrFail()
            : null;

        try {
            $file = $this->blogAiImageGenerator->generateForUser($user, trim((string) $validated['prompt']), $parent);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'prompt' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('AI image generated successfully.'),
            'data' => $this->serializeImagePickerFile($file, $request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $blog = Blog::query()->create($this->validated($request));
        $blog->tags()->sync($request->input('tag_ids', []));

        log_activity('admin.blogs.create', 'Created a blog post.', [
            'subject_type' => Blog::class,
            'subject_id' => $blog->id,
            'metadata' => [
                'title' => $blog->title,
                'slug' => $blog->slug,
            ],
        ]);

        return redirect()->route('admin-blogs.index')->with('status', __('Blog post created successfully.'));
    }

    public function duplicate(Blog $blog): RedirectResponse
    {
        $blog->load('tags:id');
        $now = time();
        $duplicateTitle = $blog->titleForLocale().' '.__('(Copy)');

        $clone = Blog::query()->create([
            'id_secure' => Str::random(32),
            'blog_category_id' => $blog->blog_category_id,
            'title' => $duplicateTitle,
            'title_translations' => $this->duplicateTranslations($blog->title_translations ?? [], $duplicateTitle),
            'excerpt' => $blog->excerpt,
            'excerpt_translations' => $blog->excerpt_translations,
            'content' => $blog->content,
            'content_translations' => $blog->content_translations,
            'slug' => $this->uniqueSlug($duplicateTitle),
            'thumbnail' => $blog->thumbnail,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'canonical_url' => null,
            'og_image' => $blog->og_image,
            'status' => false,
            'published_at' => null,
            'changed' => $now,
            'created' => $now,
        ]);

        $clone->tags()->sync($blog->tags->pluck('id')->all());

        log_activity('admin.blogs.duplicate', 'Duplicated a blog post.', [
            'subject_type' => Blog::class,
            'subject_id' => $clone->id,
            'metadata' => [
                'source_blog_id' => $blog->id,
                'title' => $clone->title,
                'slug' => $clone->slug,
            ],
        ]);

        return redirect()->route('admin-blogs.edit', $clone)->with('status', __('Blog post duplicated successfully.'));
    }

    public function edit(Blog $blog): View
    {
        $blog->load('tags:id');

        return view('adminblogs::edit', $this->formData($blog));
    }

    public function update(Request $request, Blog $blog): RedirectResponse
    {
        $blog->update($this->validated($request, $blog));
        $blog->tags()->sync($request->input('tag_ids', []));

        log_activity('admin.blogs.update', 'Updated a blog post.', [
            'subject_type' => Blog::class,
            'subject_id' => $blog->id,
            'metadata' => [
                'title' => $blog->title,
                'slug' => $blog->slug,
            ],
        ]);

        return redirect()->route('admin-blogs.index')->with('status', __('Blog post updated successfully.'));
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        $metadata = ['title' => $blog->title, 'slug' => $blog->slug];
        $blog->delete();

        log_activity('admin.blogs.delete', 'Deleted a blog post.', [
            'subject_type' => Blog::class,
            'subject_id' => $blog->id,
            'metadata' => $metadata,
        ]);

        return redirect()->route('admin-blogs.index')->with('status', __('Blog post deleted successfully.'));
    }

    protected function formData(Blog $blog): array
    {
        $user = auth()->user();

        return [
            'blog' => $blog,
            'categories' => BlogCategory::query()->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => BlogTag::query()->where('status', true)->orderBy('name')->get(),
            'authorFolders' => $user
                ? AppFile::query()->ownedBy($user)->where('is_folder', true)->orderBy('name')->get(['id', 'id_secure', 'name'])
                : collect(),
        ];
    }

    protected function validated(Request $request, ?Blog $blog = null): array
    {
        $defaultLocale = $this->defaultLocale();

        $validated = $request->validate([
            'blog_category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')],
            'title_translations' => ['required', 'array'],
            'title_translations.*' => ['nullable', 'string', 'max:255'],
            'excerpt_translations' => ['nullable', 'array'],
            'excerpt_translations.*' => ['nullable', 'string'],
            'content_translations' => ['required', 'array'],
            'content_translations.*' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blogs', 'slug')->ignore($blog?->id)],
            'thumbnail' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:2000'],
            'og_image' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('blog_tags', 'id')],
            'auto_translate_missing' => ['nullable', 'boolean'],
        ]);

        $titleTranslations = Blog::cleanTranslations($validated['title_translations'] ?? [], 255);
        $excerptTranslations = Blog::cleanTranslations($validated['excerpt_translations'] ?? []);
        $contentTranslations = Blog::cleanTranslations($validated['content_translations'] ?? []);

        if (($titleTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "title_translations.{$defaultLocale}" => __('The default language title is required.'),
            ]);
        }

        if (($contentTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "content_translations.{$defaultLocale}" => __('The default language content is required.'),
            ]);
        }

        if ($request->boolean('auto_translate_missing')) {
            $generated = $this->contentGenerator->completeFields([
                'title' => $titleTranslations,
                'excerpt' => $excerptTranslations,
                'content' => $contentTranslations,
            ], [
                'title' => 255,
            ]);

            $titleTranslations = $generated['title'];
            $excerptTranslations = $generated['excerpt'];
            $contentTranslations = $generated['content'];
        }

        $defaultTitle = $titleTranslations[$defaultLocale];
        $isPublished = (bool) $validated['status'];

        return [
            'id_secure' => $blog?->id_secure ?: Str::random(32),
            'blog_category_id' => $validated['blog_category_id'] ?? null,
            'title' => $defaultTitle,
            'title_translations' => $titleTranslations,
            'excerpt' => $excerptTranslations[$defaultLocale] ?? null,
            'excerpt_translations' => $excerptTranslations,
            'content' => $contentTranslations[$defaultLocale],
            'content_translations' => $contentTranslations,
            'slug' => Str::slug(trim($validated['slug'] ?: $defaultTitle)),
            'thumbnail' => trim((string) ($validated['thumbnail'] ?? '')) ?: null,
            'meta_title' => trim((string) ($validated['meta_title'] ?? '')) ?: null,
            'meta_description' => trim((string) ($validated['meta_description'] ?? '')) ?: null,
            'canonical_url' => trim((string) ($validated['canonical_url'] ?? '')) ?: null,
            'og_image' => trim((string) ($validated['og_image'] ?? '')) ?: null,
            'status' => $isPublished,
            'published_at' => $isPublished ? ($blog?->published_at ?: time()) : null,
            'changed' => time(),
            'created' => $blog?->created ?: time(),
        ];
    }

    protected function defaultLocale(): string
    {
        return function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', 'en');
    }

    protected function duplicateTranslations(array $translations, string $fallbackTitle): array
    {
        if ($translations === []) {
            return [$this->defaultLocale() => $fallbackTitle];
        }

        $translations[$this->defaultLocale()] = $fallbackTitle;

        return $translations;
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'post';
        $slug = $base;
        $index = 2;

        while (Blog::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    protected function serializeImagePickerFile(AppFile $file, Request $request): array
    {
        $basePath = rtrim((string) $request->getBaseUrl(), '/');
        $normalizeUrl = static function (?string $url) use ($basePath): ?string {
            $url = trim((string) $url);

            if ($url === '') {
                return null;
            }

            if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                $query = parse_url($url, PHP_URL_QUERY);

                if ($path !== '') {
                    $relative = $query ? $path.'?'.$query : $path;

                    if ($basePath !== '' && str_starts_with($relative, '/') && ! str_starts_with($relative, $basePath.'/') && $relative !== $basePath) {
                        return $basePath.$relative;
                    }

                    return $relative;
                }
            }

            if ($basePath !== '' && str_starts_with($url, '/') && ! str_starts_with($url, $basePath.'/') && $url !== $basePath) {
                return $basePath.$url;
            }

            return $url;
        };

        $previewUrl = $normalizeUrl(route('admin-files.preview', $file, false));
        $url = null;

        $url = $normalizeUrl($this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path));

        return [
            'id' => $file->id,
            'name' => $file->name,
            'note' => $file->note,
            'url' => $url ?: $previewUrl,
            'previewUrl' => $previewUrl,
            'embedUrl' => $previewUrl,
            'size' => $file->humanSize(),
            'category' => $file->category,
            'mimeType' => $file->mime_type,
        ];
    }
}
