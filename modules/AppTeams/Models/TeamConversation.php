<?php

namespace Modules\AppTeams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class TeamConversation extends Model
{
    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'type',
        'title',
        'description',
        'last_message_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_conversation_participants', 'conversation_id', 'user_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamMessage::class, 'conversation_id');
    }
}
