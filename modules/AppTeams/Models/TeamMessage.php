<?php

namespace Modules\AppTeams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class TeamMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'attachments',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TeamConversation::class, 'conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
