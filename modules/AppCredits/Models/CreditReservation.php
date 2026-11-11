<?php

namespace Modules\AppCredits\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class CreditReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'plan_id',
        'usage_log_id',
        'action_key',
        'idempotency_key',
        'status',
        'unit_cost',
        'quantity',
        'amount',
        'metadata',
        'expires_at',
        'committed_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'quantity' => 'integer',
            'amount' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(CreditUsageLog::class, 'usage_log_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_RESERVED)
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
