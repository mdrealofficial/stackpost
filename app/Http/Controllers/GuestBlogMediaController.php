<?php

namespace App\Http\Controllers;

use App\Support\PublishedBlogMedia;
use Illuminate\Support\Facades\Storage;
use Modules\AdminBlogs\Models\Blog;
use Modules\AppFiles\Models\AppFile;

class GuestBlogMediaController extends Controller
{
    public function __invoke(string $slug, string $fileKey, PublishedBlogMedia $media)
    {
        $blog = Blog::query()->where('status', true)->where('slug', $slug)->firstOrFail();
        $file = AppFile::query()->where('is_folder', false)->where('id_secure', $fileKey)->firstOrFail();
        abort_unless($media->isReferenced($blog, $file), 404);
        abort_unless(in_array($file->mime_type, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
            'video/mp4', 'video/webm', 'audio/mpeg', 'audio/ogg', 'audio/wav',
        ], true), 404);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        // Revalidate on every request so unpublishing a post revokes this URL.
        return $disk->response($file->path, $file->name, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
