<?php

namespace App\Support;

use Modules\AdminBlogs\Models\Blog;
use Modules\AppFiles\Models\AppFile;

class PublishedBlogMedia
{
    public function resolve(string $url): ?AppFile
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $host = parse_url($url, PHP_URL_HOST);
        $localHosts = array_filter([parse_url(config('app.url'), PHP_URL_HOST), request()->getHost()]);

        if ($host && ! in_array(strtolower($host), array_map('strtolower', $localHosts), true)) {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        if (preg_match('#/(?:portal|admin)/files/([a-zA-Z0-9_-]+)/preview$#', $path, $matches)) {
            return AppFile::query()->where('is_folder', false)->where('id_secure', $matches[1])->first();
        }

        if (str_contains($path, '/storage/')) {
            $relative = substr($path, strpos($path, '/storage/') + 9);

            return AppFile::query()->where('is_folder', false)->where('disk', 'public')->where('path', $relative)->first();
        }

        return null;
    }

    public function isReferenced(Blog $blog, AppFile $file): bool
    {
        $urls = [(string) $blog->thumbnail];
        $contents = [(string) $blog->content, ...array_values($blog->content_translations ?? [])];
        foreach ($contents as $content) {
            if (! is_string($content)) {
                continue;
            }
            preg_match_all('/\b(?:src|poster)=["\']([^"\']+)["\']/i', $content, $matches);
            array_push($urls, ...($matches[1] ?? []));
        }

        foreach (array_unique($urls) as $url) {
            if ($this->resolve($url)?->is($file)) {
                return true;
            }
        }

        return false;
    }
}
