<?php

namespace Modules\AppChannels\Models;

use App\Support\Storage\SocialAvatarStore;
use App\Support\Storage\StorageDriverManager;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;

class SocialAccount extends Model
{
    protected $table = 'social_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'auth_data' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $account): void {
            $rawAvatarUrl = trim((string) $account->getAttribute('avatar_url'));

            if ($rawAvatarUrl === '' || ! $account->isDirty('avatar_url')) {
                return;
            }

            $storedAvatar = app(SocialAvatarStore::class)->localize(
                $rawAvatarUrl,
                (string) $account->provider_key,
                (string) ($account->external_id ?: $account->username ?: $account->display_name ?: 'account')
            );

            if (! is_array($storedAvatar)) {
                return;
            }

            $oldDisk = $account->getOriginal('avatar_disk');
            $oldPath = $account->getOriginal('avatar_path');

            $account->setAttribute('avatar_disk', $storedAvatar['disk']);
            $account->setAttribute('avatar_path', $storedAvatar['path']);
            $account->setAttribute('avatar_url', $storedAvatar['url']);

            app(StorageDriverManager::class)->deleteIfPresent(
                is_string($oldDisk) ? $oldDisk : null,
                is_string($oldPath) ? $oldPath : null
            );
        });

        static::deleting(function (self $account): void {
            app(StorageDriverManager::class)->deleteIfPresent(
                (string) ($account->avatar_disk ?? ''),
                (string) ($account->avatar_path ?? '')
            );
        });
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            $hasStoredAvatar = filled($this->avatar_path);

            if (filled($this->avatar_path)) {
                $disk = trim((string) ($this->avatar_disk ?: 'public'));
                $path = $this->normalizeLocalStoragePath((string) $this->avatar_path);
                $url = $this->storedAvatarIsUsable($disk, $path)
                    ? app(StorageDriverManager::class)->publicUrl($disk, $path)
                    : null;

                if (filled($url)) {
                    return $url;
                }
            }

            $value = trim((string) $value);

            if ($value === '') {
                return null;
            }

            $localPath = $this->extractLocalStoragePath($value);

            if ($localPath !== null) {
                if ($hasStoredAvatar) {
                    return $this->sourceAvatarUrl();
                }

                return app(StorageDriverManager::class)->publicUrl('public', $localPath);
            }

            if (Str::startsWith($value, ['http://', 'https://', '//'])) {
                return $value;
            }

            return $this->sourceAvatarUrl();
        });
    }

    protected function storedAvatarIsUsable(string $disk, string $path): bool
    {
        try {
            if (! Storage::disk($disk)->exists($path)) {
                return false;
            }

            return (int) Storage::disk($disk)->size($path) >= 128;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function normalizeLocalStoragePath(string $path): string
    {
        $path = ltrim(trim($path), '/');
        $path = Str::replaceStart('public/storage/', '', $path);
        $path = Str::replaceStart('storage/', '', $path);
        $path = Str::replaceStart('public/', '', $path);

        return $path;
    }

    protected function extractLocalStoragePath(string $value): ?string
    {
        $path = trim((string) parse_url($value, PHP_URL_PATH));

        if ($path === '') {
            return null;
        }

        $basePath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath !== '' && Str::startsWith($path, '/'.$basePath.'/')) {
            $path = Str::after($path, '/'.$basePath.'/');
            $path = '/'.$path;
        }

        if (! Str::startsWith($path, '/storage/')) {
            return null;
        }

        return $this->normalizeLocalStoragePath($path);
    }

    protected function sourceAvatarUrl(): ?string
    {
        $authData = $this->auth_data ?? [];
        $candidates = [
            data_get($authData, 'profile_payload.profile.profile_picture_url'),
            data_get($authData, 'profile_payload.profile_picture_url'),
            data_get($authData, 'profile_payload.picture.data.url'),
            data_get($authData, 'page_payload.picture.data.url'),
            data_get($authData, 'profile.avatar_url'),
            data_get($authData, 'profile.avatar'),
        ];

        foreach ($candidates as $candidate) {
            $url = trim((string) $candidate);

            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        $thumbnails = data_get($authData, 'channel_payload.snippet.thumbnails', []);

        foreach (['high', 'medium', 'default'] as $size) {
            $url = trim((string) data_get($thumbnails, $size.'.url', ''));

            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
