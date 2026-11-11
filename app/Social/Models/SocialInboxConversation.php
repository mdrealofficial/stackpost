<?php

namespace App\Social\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;

class SocialInboxConversation extends Model
{
    protected $table = 'social_inbox_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'automation_paused_until' => 'datetime',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $ownerUserId, ?int $teamId): Builder
    {
        return $query->where('owner_user_id', $ownerUserId)
            ->when(
                $teamId,
                fn (Builder $builder, int $id) => $builder->where(fn (Builder $scope) => $scope->where('team_id', $id)->orWhereNull('team_id')),
                fn (Builder $builder) => $builder->whereNull('team_id')
            );
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SocialInboxMessage::class, 'conversation_id');
    }
}
