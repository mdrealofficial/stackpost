<?php

namespace Modules\AppAIVideo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;

class AIVideoJob extends Model
{
    protected $table = 'ai_video_jobs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'metadata' => 'array',
            'last_polled_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(AppFile::class, 'file_id');
    }

    public function scopeOwnedBy(Builder $query, int $ownerUserId): Builder
    {
        return $query->where('owner_user_id', $ownerUserId);
    }

    public function scopeRequestedBy(Builder $query, int $userId): Builder
    {
        return $query->where('requested_by_user_id', $userId);
    }
}
