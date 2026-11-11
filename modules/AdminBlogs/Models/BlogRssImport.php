<?php

namespace Modules\AdminBlogs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogRssImport extends Model
{
    protected $table = 'blog_rss_imports';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source_published_at' => 'integer',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BlogRssSource::class, 'blog_rss_source_id');
    }

    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class, 'blog_id');
    }
}
