<?php

namespace Modules\AppTeams\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Modules\AppFiles\Models\AppFile;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class ReviewAttachmentController
{
    public function __invoke(int $review, int $file)
    {
        $item = TeamPostReview::with('team', 'post')->findOrFail($review);
        abort_unless($item->team && $item->post && (int) $item->post->team_id === (int) $item->team_id, 404);
        abort_unless(TeamWorkspaceAccess::hasPermission(auth()->user(), 'post.approve', $item->team), 403);
        abort_unless(in_array((int) $item->post->account_id, TeamWorkspaceAccess::accessibleAccountIds(auth()->user(), $item->team), true), 403);
        $asset = AppFile::where('owner_user_id', $item->team->owner_user_id)
            ->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $item->team_id))->findOrFail($file);
        $attached = collect((array) data_get($item->post->data, 'medias', []))->contains(function ($media) use ($asset) {
            return is_array($media) && ((int) ($media['id'] ?? 0) === (int) $asset->id || (filled($asset->id_secure) && ($media['id_secure'] ?? $media['idSecure'] ?? null) === $asset->id_secure));
        });
        abort_unless($attached && ! $asset->is_folder && filled($asset->path), 404);
        $inline = in_array($asset->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'], true);

        return Storage::disk($asset->disk)->response($asset->path, $asset->name, [
            'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }
}
