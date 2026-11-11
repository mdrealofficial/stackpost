<?php

namespace Modules\AppBulkPosts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkPostRow extends Model
{
    protected $table = 'bulk_post_rows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'validation_errors' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BulkPostBatch::class, 'batch_id');
    }

    public function scopeReadyForImport(Builder $query): Builder
    {
        return $query->where('status', 'valid');
    }
}
