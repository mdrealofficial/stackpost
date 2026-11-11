<?php

namespace Modules\AppBulkPosts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminUser\Models\User;

class BulkPostBatch extends Model
{
    protected $table = 'bulk_post_batches';

    protected $guarded = [];

    public function displayStatus(): string
    {
        if ($this->status === 'completed' && ((int) data_get($this->stats, 'invalid_rows', 0) + (int) data_get($this->stats, 'failed_rows', 0)) > 0) {
            return 'partial';
        }

        return $this->status;
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'stats' => 'array',
            'last_processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(BulkPostRow::class, 'batch_id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owner_user_id', $userId);
    }
}
