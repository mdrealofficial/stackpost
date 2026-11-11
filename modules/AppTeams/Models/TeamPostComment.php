<?php

namespace Modules\AppTeams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppPublishing\Models\PublishingPost;

class TeamPostComment extends Model
{
    protected $fillable = [
        'team_id',
        'post_id',
        'user_id',
        'message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(PublishingPost::class, 'post_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
