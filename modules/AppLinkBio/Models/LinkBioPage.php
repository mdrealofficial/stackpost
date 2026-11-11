<?php

namespace Modules\AppLinkBio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Modules\AdminUser\Models\User;
use Modules\AppLinkBio\Support\LinkBioTemplateCatalog;

class LinkBioPage extends Model
{
    protected static ?bool $statusColumnExists = null;

    protected $table = 'link_bio_pages';

    protected $fillable = [
        'owner_user_id',
        'team_id',
        'title',
        'slug',
        'headline',
        'description',
        'accent_color',
        'avatar_url',
        'cover_url',
        'template_key',
        'status',
        'is_published',
        'blocks',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'blocks' => 'array',
            'settings' => 'array',
        ];
    }

    public function scopeOwnedBy(Builder $query, User|int|null $owner): Builder
    {
        $ownerId = $owner instanceof User ? (int) $owner->id : (int) $owner;

        return $query->where('owner_user_id', $ownerId);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LinkBioEvent::class, 'link_bio_page_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        $query->where('is_published', true);

        if (self::hasStatusColumn()) {
            $query->where('status', 'published');
        }

        return $query;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function brandingText(): ?string
    {
        $value = trim((string) $this->setting('branding_text', ''));

        return $value !== '' ? $value : null;
    }

    public function avatarStyle(): string
    {
        return (string) $this->setting('avatar_style', 'circle');
    }

    public function buttonStyle(): string
    {
        return (string) $this->setting('button_style', 'rounded');
    }

    public function contentAlign(): string
    {
        return (string) $this->setting('content_align', 'left');
    }

    public function backgroundImage(): string
    {
        return trim((string) $this->setting('background_image', ''));
    }

    public function backgroundOverlay(): int
    {
        return max(0, min(85, (int) $this->setting('background_overlay', 28)));
    }

    public function backgroundPosition(): string
    {
        $value = (string) $this->setting('background_position', 'center');

        return in_array($value, ['top', 'center', 'bottom'], true) ? $value : 'center';
    }

    public function backgroundFit(): string
    {
        $value = (string) $this->setting('background_fit', 'cover');

        return in_array($value, ['cover', 'contain', 'pattern'], true) ? $value : 'cover';
    }

    public function templateMeta(): array
    {
        return LinkBioTemplateCatalog::find((string) $this->template_key);
    }

    public static function hasStatusColumn(): bool
    {
        if (self::$statusColumnExists !== null) {
            return self::$statusColumnExists;
        }

        self::$statusColumnExists = Schema::hasColumn('link_bio_pages', 'status');

        return self::$statusColumnExists;
    }
}
