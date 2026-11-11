<?php

namespace Modules\AppTeams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class TeamInvitation extends Model
{
    protected $fillable = [
        'team_id',
        'invited_by_user_id',
        'accepted_by_user_id',
        'email',
        'invite_code',
        'role',
        'permissions',
        'status',
        'message',
        'expires_at',
        'accepted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }
}
