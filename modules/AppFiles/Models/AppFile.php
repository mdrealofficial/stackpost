<?php

namespace Modules\AppFiles\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppFile extends Model
{
    public const EDITABLE_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected $table = 'files';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'is_image' => 'boolean',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $file): void {
            if (blank($file->id_secure)) {
                $file->id_secure = Str::random(32);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id_secure';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        $activeTeam = TeamWorkspaceAccess::activeTeam($user);
        $ownerUserId = TeamWorkspaceAccess::workspaceOwnerUserId($user);
        $teamId = $activeTeam?->id ?: ($user->ownedTeams()->value('id') ?: $user->teams()->value('teams.id'));

        return $query->where(function (Builder $builder) use ($ownerUserId, $teamId): void {
            $builder->where('owner_user_id', $ownerUserId);

            if ($teamId) {
                $builder->orWhere('team_id', $teamId);
            }
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('extension', 'like', "%{$term}%")
                ->orWhere('mime_type', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }

    public function scopeInFolder(Builder $query, ?int $parentId): Builder
    {
        return $parentId
            ? $query->where('parent_id', $parentId)
            : $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_folder')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($this->is_folder) {
            return __('Folder');
        }

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function typeLabel(): string
    {
        if ($this->is_folder) {
            return __('Folder');
        }

        return $this->extension ? strtoupper($this->extension) : strtoupper($this->category);
    }

    public function breadcrumbChain(): array
    {
        $chain = [];
        $current = $this->parent;

        while ($current) {
            $chain[] = $current;
            $current = $current->parent;
        }

        return array_reverse($chain);
    }

    public function isEditableImage(): bool
    {
        return $this->is_image
            && ! $this->is_folder
            && in_array(strtolower((string) $this->extension), self::EDITABLE_IMAGE_EXTENSIONS, true);
    }
}
