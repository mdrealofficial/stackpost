<?php

namespace Modules\AppAIStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class AIPromptHistory extends Model
{
    protected $table = 'ai_prompt_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'output_payload' => 'array',
            'metadata' => 'array',
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
