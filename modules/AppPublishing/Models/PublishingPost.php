<?php

namespace Modules\AppPublishing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;
use Modules\AppChannels\Models\SocialAccount;

class PublishingPost extends Model
{
    public const STATUS_DRAFT = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_SCHEDULED = 3;
    public const STATUS_PUBLISHED = 4;
    public const STATUS_FAILED = 5;

    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'data' => 'array',
            'result' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'account_id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDueToDispatch(Builder $query, ?int $timestamp = null): Builder
    {
        return $query
            ->where('function', 'post')
            ->where('status', self::STATUS_SCHEDULED)
            ->whereNotNull('time_post')
            ->where('time_post', '<=', $timestamp ?? now()->timestamp);
    }
}
