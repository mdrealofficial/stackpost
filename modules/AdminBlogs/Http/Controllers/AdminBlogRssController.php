<?php

namespace Modules\AdminBlogs\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AdminBlogs\Models\BlogRssSource;
use Modules\AdminBlogs\Support\RssImportService;

class AdminBlogRssController extends Controller
{
    public function index(): View
    {
        $sources = BlogRssSource::query()
            ->with('category:id,name,name_translations')
            ->withCount('imports')
            ->orderByDesc('id')
            ->get();

        return view('adminblogs::rss.index', [
            'sources' => $sources,
            'categories' => BlogCategory::query()->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => BlogTag::query()->where('status', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'feed_url' => ['required', 'url', 'max:2000'],
            'blog_category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('blog_tags', 'id')],
            'status' => ['nullable', 'boolean'],
            'auto_publish' => ['nullable', 'boolean'],
            'ai_improve' => ['nullable', 'boolean'],
            'ai_prompt' => ['nullable', 'string'],
            'sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'max_items_per_run' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $this->validateFeedUrl((string) $validated['feed_url']);

        BlogRssSource::query()->create([
            'id_secure' => Str::random(32),
            'name' => trim((string) $validated['name']),
            'feed_url' => trim((string) $validated['feed_url']),
            'blog_category_id' => $validated['blog_category_id'] ?? null,
            'tag_ids' => array_values(array_map('intval', $validated['tag_ids'] ?? [])),
            'status' => $request->boolean('status', true),
            'auto_publish' => $request->boolean('auto_publish', true),
            'ai_improve' => $request->boolean('ai_improve'),
            'ai_prompt' => trim((string) ($validated['ai_prompt'] ?? '')) ?: null,
            'sync_interval_minutes' => (int) $validated['sync_interval_minutes'],
            'max_items_per_run' => (int) $validated['max_items_per_run'],
            'changed' => time(),
            'created' => time(),
        ]);

        return redirect()->route('admin-blogs.rss.index')->with('status', __('RSS source created successfully.'));
    }

    public function run(BlogRssSource $source, RssImportService $service): RedirectResponse
    {
        $result = $service->importSource($source, true);

        return redirect()
            ->route('admin-blogs.rss.index')
            ->with('status', __('RSS sync finished. Created :created, skipped :skipped.', [
                'created' => $result['created'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
            ]));
    }

    public function runAll(RssImportService $service): RedirectResponse
    {
        $created = 0;
        $skipped = 0;

        BlogRssSource::query()->where('status', true)->get()->each(function (BlogRssSource $source) use ($service, &$created, &$skipped): void {
            $result = $service->importSource($source, true);
            $created += (int) ($result['created'] ?? 0);
            $skipped += (int) ($result['skipped'] ?? 0);
        });

        return redirect()->route('admin-blogs.rss.index')->with('status', __('RSS sync finished. Created :created, skipped :skipped.', compact('created', 'skipped')));
    }

    public function destroy(BlogRssSource $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('admin-blogs.rss.index')->with('status', __('RSS source deleted successfully.'));
    }

    protected function validateFeedUrl(string $feedUrl): void
    {
        $response = Http::timeout(20)
            ->accept('application/rss+xml, application/xml, text/xml, application/atom+xml')
            ->get($feedUrl);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'feed_url' => __('Could not fetch this feed URL.'),
            ]);
        }

        $body = trim((string) $response->body());

        if ($body === '') {
            throw ValidationException::withMessages([
                'feed_url' => __('This feed URL returned an empty response.'),
            ]);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $isValidFeed = $xml instanceof \SimpleXMLElement
            && (isset($xml->channel->item) || isset($xml->entry));

        if (! $isValidFeed) {
            throw ValidationException::withMessages([
                'feed_url' => __('This URL is reachable but does not appear to be a valid RSS or Atom feed.'),
            ]);
        }
    }
}
