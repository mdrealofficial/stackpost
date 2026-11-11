<?php

namespace Modules\AppAIAssistant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssistantAuditLog extends Model
{
    protected $table = 'ai_assistant_audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credit_cost' => 'integer',
            'credits_before' => 'integer',
            'credits_after' => 'integer',
            'payload' => 'array',
            'result' => 'array',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $ownerUserId, ?int $teamId): Builder
    {
        return $query
            ->where('owner_user_id', $ownerUserId)
            ->when(
                $teamId,
                fn (Builder $builder, int $id): Builder => $builder->where('team_id', $id),
                fn (Builder $builder): Builder => $builder->whereNull('team_id')
            );
    }
}
