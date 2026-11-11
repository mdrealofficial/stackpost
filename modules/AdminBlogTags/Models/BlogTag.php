<?php

namespace Modules\AdminBlogTags\Models;

use App\Concerns\HasLocalizedAttributes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\AdminBlogs\Models\Blog;

class BlogTag extends Model
{
    use HasLocalizedAttributes;

    protected $table = 'blog_tags';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'description_translations' => 'array',
            'status' => 'boolean',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function blogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_tag_maps', 'blog_tag_id', 'blog_id');
    }

    public function nameForLocale(?string $locale = null): string
    {
        return $this->localized('name', $locale);
    }

    public function descriptionForLocale(?string $locale = null): string
    {
        return $this->localized('description', $locale);
    }

    public function statusLabel(): string
    {
        return $this->status ? __('Enabled') : __('Disabled');
    }

    public function statusVariant(): string
    {
        return $this->status ? 'success' : 'neutral';
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        return $this->created ? Carbon::createFromTimestamp((int) $this->created)->format($format) : null;
    }

    public static function cleanTranslations(array $translations, int $max = 65535): array
    {
        return self::sanitizeTranslations($translations, $max);
    }
}
