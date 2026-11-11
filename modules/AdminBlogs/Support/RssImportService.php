<?php

namespace Modules\AdminBlogs\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AdminBlogs\Models\Blog;
use Modules\AdminBlogs\Models\BlogRssImport;
use Modules\AdminBlogs\Models\BlogRssSource;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;

class RssImportService
{
    public function __construct(
        protected RssContentImprover $improver,
        protected MultilingualContentGenerator $multilingualContentGenerator,
        protected FileManager $fileManager,
    ) {}

    public function importSource(BlogRssSource $source, bool $force = false): array
    {
        $now = time();

        if (! $force && ! $source->shouldRunAt($now)) {
            return ['created' => 0, 'skipped' => 0, 'message' => __('Source is not due yet.')];
        }

        $response = Http::timeout(30)
            ->accept('application/rss+xml, application/xml, text/xml, application/atom+xml')
            ->get($source->feed_url);

        if (! $response->successful()) {
            $source->update([
                'last_checked_at' => $now,
                'last_error' => __('Could not fetch the RSS feed.'),
                'changed' => $now,
            ]);

            return ['created' => 0, 'skipped' => 0, 'message' => __('Could not fetch the RSS feed.')];
        }

        $items = collect($this->parseFeed($response->body()))
            ->take(max(1, (int) $source->max_items_per_run));

        $created = 0;
        $skipped = 0;
        $defaultLocale = function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', 'en');

        foreach ($items as $item) {
            $hash = sha1(implode('|', array_filter([
                (string) ($item['guid'] ?? ''),
                (string) ($item['link'] ?? ''),
                (string) ($item['title'] ?? ''),
            ])));

            if (BlogRssImport::query()->where('blog_rss_source_id', $source->id)->where('content_hash', $hash)->exists()) {
                $skipped++;
                continue;
            }

            $payload = [
                'title' => trim((string) ($item['title'] ?? '')),
                'excerpt' => trim(strip_tags((string) ($item['summary'] ?? ''))),
                'content' => trim((string) ($item['content'] ?: $item['summary'] ?: '')),
            ];

            if ($payload['title'] === '' || $payload['content'] === '') {
                $skipped++;
                continue;
            }

            if ($source->ai_improve) {
                $payload = $this->improver->improve($payload, $source->ai_prompt);
            }

            $title = trim((string) $payload['title']);
            $excerpt = trim((string) ($payload['excerpt'] ?: ''));
            $content = trim((string) $payload['content']);
            $importOwner = $this->resolveImportOwner();
            $content = $this->localizeContentImages($content, $item['link'] ?? $source->feed_url, $importOwner);
            $thumbnail = $this->localizeRemoteImage($item['thumbnail'] ?? null, $item['link'] ?? $source->feed_url, $importOwner);

            $fieldTranslations = [
                'title' => [$defaultLocale => $title],
                'excerpt' => [$defaultLocale => $excerpt],
                'content' => [$defaultLocale => $content],
            ];

            if ($source->ai_auto_translate) {
                $fieldTranslations = $this->multilingualContentGenerator->completeFields($fieldTranslations, [
                    'title' => 255,
                    'excerpt' => 65535,
                    'content' => 65535,
                ]);
            }

            $blog = Blog::query()->create([
                'id_secure' => Str::random(32),
                'blog_category_id' => $source->blog_category_id,
                'title' => $title,
                'title_translations' => $fieldTranslations['title'],
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'excerpt_translations' => $fieldTranslations['excerpt'],
                'content' => $content,
                'content_translations' => $fieldTranslations['content'],
                'slug' => $this->uniqueSlug($title),
                'thumbnail' => $thumbnail,
                'status' => $source->auto_publish,
                'published_at' => $source->auto_publish ? ((int) ($item['published_at'] ?: $now)) : null,
                'changed' => $now,
                'created' => $now,
            ]);

            $tagIds = collect($source->tag_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
            if ($tagIds !== []) {
                $blog->tags()->sync($tagIds);
            }

            BlogRssImport::query()->create([
                'blog_rss_source_id' => $source->id,
                'blog_id' => $blog->id,
                'external_guid' => $item['guid'] ?? null,
                'external_url' => $item['link'] ?? null,
                'content_hash' => $hash,
                'title' => $title,
                'source_published_at' => $item['published_at'] ?? null,
                'changed' => $now,
                'created' => $now,
            ]);

            $created++;
        }

        $source->update([
            'last_checked_at' => $now,
            'last_imported_at' => $created > 0 ? $now : $source->last_imported_at,
            'last_error' => null,
            'changed' => $now,
        ]);

        return ['created' => $created, 'skipped' => $skipped, 'message' => __('RSS import completed.')];
    }

    protected function parseFeed(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $feed instanceof \SimpleXMLElement) {
            return [];
        }

        if (isset($feed->channel->item)) {
            return collect($feed->channel->item)->map(fn ($item) => $this->parseRssItem($item))->filter()->values()->all();
        }

        if (isset($feed->entry)) {
            return collect($feed->entry)->map(fn ($item) => $this->parseAtomEntry($item))->filter()->values()->all();
        }

        return [];
    }

    protected function parseRssItem(\SimpleXMLElement $item): ?array
    {
        $content = '';
        $mediaThumbnail = null;
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['content'])) {
            $contentNode = $item->children($namespaces['content']);
            $content = trim((string) ($contentNode->encoded ?? ''));
        }

        if (isset($namespaces['media'])) {
            $mediaNode = $item->children($namespaces['media']);
            $mediaThumbnail = trim((string) ($mediaNode->thumbnail->attributes()->url ?? $mediaNode->content->attributes()->url ?? ''));
        }

        $enclosureThumbnail = '';
        if (isset($item->enclosure)) {
            $type = strtolower((string) ($item->enclosure->attributes()->type ?? ''));
            if (Str::startsWith($type, 'image/')) {
                $enclosureThumbnail = trim((string) ($item->enclosure->attributes()->url ?? ''));
            }
        }

        $description = trim((string) ($item->description ?? ''));
        $title = trim((string) ($item->title ?? ''));

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'link' => trim((string) ($item->link ?? '')),
            'guid' => trim((string) ($item->guid ?? '')),
            'summary' => $description,
            'content' => $content !== '' ? $content : $description,
            'thumbnail' => $mediaThumbnail ?: $enclosureThumbnail ?: $this->firstImageFromHtml($content ?: $description),
            'published_at' => ($time = strtotime((string) ($item->pubDate ?? ''))) ? $time : null,
        ];
    }

    protected function parseAtomEntry(\SimpleXMLElement $entry): ?array
    {
        $title = trim((string) ($entry->title ?? ''));
        if ($title === '') {
            return null;
        }

        $link = '';
        foreach ($entry->link as $node) {
            $attrs = $node->attributes();
            if ((string) ($attrs['rel'] ?? 'alternate') === 'alternate') {
                $link = trim((string) ($attrs['href'] ?? ''));
                break;
            }
        }

        $content = trim((string) ($entry->content ?? ''));
        $summary = trim((string) ($entry->summary ?? ''));

        return [
            'title' => $title,
            'link' => $link,
            'guid' => trim((string) ($entry->id ?? '')),
            'summary' => $summary,
            'content' => $content !== '' ? $content : $summary,
            'thumbnail' => $this->firstImageFromHtml($content ?: $summary),
            'published_at' => ($time = strtotime((string) ($entry->published ?? $entry->updated ?? ''))) ? $time : null,
        ];
    }

    protected function firstImageFromHtml(string $html): ?string
    {
        if (! preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            return null;
        }

        return trim((string) ($matches[1] ?? '')) ?: null;
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

    protected function localizeContentImages(string $html, ?string $baseUrl, ?User $owner): string
    {
        if ($html === '' || ! str_contains(strtolower($html), '<img')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<img\b([^>]*?)\bsrc=(["\'])([^"\']+)\2([^>]*)>/i',
            function (array $matches) use ($baseUrl, $owner): string {
                $originalUrl = trim((string) ($matches[3] ?? ''));
                $localizedUrl = $this->localizeRemoteImage($originalUrl, $baseUrl, $owner);

                if (! $localizedUrl || $localizedUrl === $originalUrl) {
                    return $matches[0];
                }

                return '<img'.$matches[1].'src='.$matches[2].e($localizedUrl).$matches[2].$matches[4].'>';
            },
            $html
        );
    }

    protected function localizeRemoteImage(?string $url, ?string $baseUrl, ?User $owner): ?string
    {
        $resolvedUrl = $this->resolveRemoteUrl($url, $baseUrl);

        if (! $resolvedUrl) {
            return null;
        }

        if ($owner) {
            try {
                $file = $this->fileManager->storeRemoteFile($owner, $resolvedUrl);
                return route('admin-files.preview', $file, false);
            } catch (\Throwable) {
            }
        }

        return $resolvedUrl;
    }

    protected function resolveRemoteUrl(?string $url, ?string $baseUrl): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url((string) $baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme.':'.$url;
        }

        $baseUrl = trim((string) $baseUrl);
        if ($baseUrl === '') {
            return $url;
        }

        $parts = parse_url($baseUrl);
        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return $url;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        $basePath = isset($parts['path']) ? rtrim(str_replace('\\', '/', dirname($parts['path'])), '/') : '';
        $basePath = $basePath === '/' ? '' : $basePath;

        return $origin.($basePath !== '' ? '/'.ltrim($basePath, '/') : '').'/'.ltrim($url, '/');
    }

    protected function resolveImportOwner(): ?User
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user;
        }

        return User::query()
            ->where('username', 'admin')
            ->first()
            ?? User::query()->oldest('id')->first();
    }
}
