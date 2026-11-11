<?php

namespace Modules\AppWatermark\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;

class PublishingWatermark extends Model
{
    protected $table = 'publishing_watermarks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_global' => 'boolean',
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

    public function file(): BelongsTo
    {
        return $this->belongsTo(AppFile::class, 'file_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(SocialAccount::class, 'publishing_watermark_social_account', 'watermark_id', 'social_account_id')
            ->withTimestamps();
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owner_user_id', $userId);
    }
}
