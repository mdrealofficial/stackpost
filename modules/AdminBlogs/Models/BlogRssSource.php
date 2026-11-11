<?php

namespace Modules\AdminBlogs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminBlogCategories\Models\BlogCategory;

class BlogRssSource extends Model
{
    protected $table = 'blog_rss_sources';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tag_ids' => 'array',
            'status' => 'boolean',
            'auto_publish' => 'boolean',
            'ai_improve' => 'boolean',
            'ai_auto_translate' => 'boolean',
            'sync_interval_minutes' => 'integer',
            'max_items_per_run' => 'integer',
            'last_checked_at' => 'integer',
            'last_imported_at' => 'integer',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(BlogRssImport::class, 'blog_rss_source_id');
    }

    public function shouldRunAt(int $timestamp): bool
    {
        if (! $this->status) {
            return false;
        }

        if (! $this->last_checked_at) {
            return true;
        }

        return ((int) $this->last_checked_at + ((int) $this->sync_interval_minutes * 60)) <= $timestamp;
    }
}
