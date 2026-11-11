<?php

namespace Modules\AppAIStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class AIStudioWorkspaceSetting extends Model
{
    protected $table = 'ai_studio_workspace_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeOwnedBy(Builder $query, int $ownerUserId): Builder
    {
        return $query->where('owner_user_id', $ownerUserId);
    }

    public function scopeForTeam(Builder $query, ?int $teamId): Builder
    {
        return $teamId ? $query->where('team_id', $teamId) : $query->whereNull('team_id');
    }
}
