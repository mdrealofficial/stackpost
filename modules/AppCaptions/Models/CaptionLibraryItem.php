<?php

namespace Modules\AppCaptions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;

class CaptionLibraryItem extends Model
{
    protected $table = 'publishing_captions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owner_user_id', $userId);
    }
}
