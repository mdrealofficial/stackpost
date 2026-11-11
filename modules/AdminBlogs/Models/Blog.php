<?php

namespace Modules\AdminBlogs\Models;

use App\Concerns\HasLocalizedAttributes;
use App\Support\PublishedBlogMedia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AppFiles\Models\AppFile;

class Blog extends Model
{
    use HasLocalizedAttributes;

    protected $table = 'blogs';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'title_translations' => 'array',
            'excerpt_translations' => 'array',
            'content_translations' => 'array',
            'status' => 'boolean',
            'published_at' => 'integer',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_tag_maps', 'blog_id', 'blog_tag_id');
    }

    public function titleForLocale(?string $locale = null): string
    {
        return $this->localized('title', $locale);
    }

    public function excerptForLocale(?string $locale = null): string
    {
        return $this->localized('excerpt', $locale);
    }

    public function contentForLocale(?string $locale = null): string
    {
        return $this->localized('content', $locale);
    }

    public function normalizedContentForLocale(?string $locale = null): string
    {
        $content = $this->contentForLocale($locale);

        if ($content === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\b(src|poster)=(["\'])([^"\']+)\2/i',
            fn (array $matches) => $matches[1].'='.$matches[2].$this->normalizeMediaUrl((string) ($matches[3] ?? '')).$matches[2],
            $content
        );
    }

    public function thumbnailUrl(): ?string
    {
        $url = $this->normalizeMediaUrl($this->thumbnail);

        return $url !== '' ? $url : null;
    }

    public function contentPreview(int $limit = 160, ?string $locale = null): string
    {
        $content = $this->excerptForLocale($locale);

        if ($content === '') {
            $content = trim(strip_tags($this->contentForLocale($locale)));
        }

        return $content === '' ? __('No content') : mb_strimwidth($content, 0, $limit, '...');
    }

    public function statusLabel(): string
    {
        return $this->status ? __('Published') : __('Draft');
    }

    public function statusVariant(): string
    {
        return $this->status ? 'success' : 'neutral';
    }

    public function publishedAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        return $this->published_at ? Carbon::createFromTimestamp((int) $this->published_at)->format($format) : null;
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        return $this->created ? Carbon::createFromTimestamp((int) $this->created)->format($format) : null;
    }

    public function seoTitle(): string
    {
        return trim((string) ($this->meta_title ?: $this->titleForLocale()));
    }

    public function seoDescription(int $limit = 180): string
    {
        $description = trim((string) ($this->meta_description ?: $this->excerptForLocale()));

        if ($description === '') {
            $description = trim(strip_tags($this->contentForLocale()));
        }

        return $description === '' ? '' : mb_strimwidth($description, 0, $limit, '...');
    }

    public static function cleanTranslations(array $translations, int $max = 65535): array
    {
        return self::sanitizeTranslations($translations, $max);
    }

    protected function normalizeMediaUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if ($this->status && filled($this->slug) && ($file = app(PublishedBlogMedia::class)->resolve($url))) {
            return route('guest.blog-media', ['slug' => $this->slug, 'fileKey' => $file->getRouteKey()]);
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && ! in_array(strtolower($host), array_map('strtolower', array_filter([
            parse_url(config('app.url'), PHP_URL_HOST), request()->getHost(),
        ])), true)) {
            return $url;
        }

        // An orphaned library reference must not send visitors to a private preview.
        if ($this->status && preg_match('#/(?:portal|admin)/files/[^/]+/preview$#', (string) parse_url($url, PHP_URL_PATH))) {
            return '';
        }

        if ($previewUrl = $this->resolveAppFilePreviewUrl($url)) {
            return $this->prependBasePath($previewUrl);
        }

        $normalized = $url;

        if (Str::startsWith($normalized, ['http://', 'https://'])) {
            $path = parse_url($normalized, PHP_URL_PATH) ?: '';
            $query = parse_url($normalized, PHP_URL_QUERY);

            if ($path !== '') {
                $normalized = $query ? $path.'?'.$query : $path;
            }
        }

        return $this->prependBasePath($normalized);
    }

    protected function resolveAppFilePreviewUrl(string $url): ?string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: $url));

        if ($path === '') {
            return null;
        }

        if (preg_match('#/admin/files/[^/]+/preview$#', $path)) {
            return $path;
        }

        $basePath = app()->bound('request') ? rtrim((string) request()->getBaseUrl(), '/') : '';

        if ($basePath !== '' && str_starts_with($path, $basePath.'/')) {
            $path = substr($path, strlen($basePath));
        }

        if (! str_contains($path, '/storage/')) {
            return null;
        }

        $storageRelativePath = ltrim((string) Str::after($path, '/storage/'), '/');

        if ($storageRelativePath === '') {
            return null;
        }

        $file = AppFile::query()
            ->where('path', $storageRelativePath)
            ->where('is_folder', false)
            ->first();

        if (! $file) {
            return null;
        }

        return route('admin-files.preview', $file, false);
    }

    protected function prependBasePath(string $url): string
    {
        if (! app()->bound('request')) {
            return $url;
        }

        $basePath = rtrim((string) request()->getBaseUrl(), '/');

        if ($basePath !== '' && str_starts_with($url, '/') && ! str_starts_with($url, $basePath.'/') && $url !== $basePath) {
            return $basePath.$url;
        }

        return $url;
    }
}
