<?php

namespace Modules\AppAIContentPlanner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class AIContentPlan extends Model
{
    protected $table = 'ai_content_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'metadata' => 'array',
            'start_date' => 'date',
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

    public function scopeOwnedBy(Builder $query, int $ownerUserId): Builder
    {
        return $query->where('owner_user_id', $ownerUserId);
    }

    public function scopeRequestedBy(Builder $query, int $userId): Builder
    {
        return $query->where('requested_by_user_id', $userId);
    }
}
