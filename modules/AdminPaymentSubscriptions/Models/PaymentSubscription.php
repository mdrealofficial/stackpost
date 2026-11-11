<?php

namespace Modules\AdminPaymentSubscriptions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;
use Modules\AppPayments\Support\PaymentManager;

class PaymentSubscription extends Model
{
    protected $table = 'payment_subscriptions';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'plan_id' => 'integer',
            'type' => 'integer',
            'amount' => 'decimal:2',
            'status' => 'integer',
            'created' => 'integer',
            'changed' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AdminPlan::class, 'plan_id');
    }

    public function statusLabel(): string
    {
        return match ((int) $this->status) {
            1 => __('Active'),
            2 => __('Cancelled'),
            default => __('Inactive'),
        };
    }

    public function statusVariant(): string
    {
        return match ((int) $this->status) {
            1 => 'success',
            2 => 'danger',
            default => 'neutral',
        };
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->created) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->created)->format($format);
    }

    public function changedAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->changed) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->changed)->format($format);
    }

    public static function userHasActive(int $userId): bool
    {
        return static::query()
            ->where('uid', $userId)
            ->where('status', 1)
            ->exists();
    }

    public static function activeRecurringForUser(int $userId): ?self
    {
        return static::query()
            ->with('plan')
            ->where('uid', $userId)
            ->where('status', 1)
            ->where('service', 'like', '%recurring%')
            ->orderByDesc('created')
            ->first();
    }

    public function isRecurring(): bool
    {
        return str_contains((string) $this->service, 'recurring');
    }

    public function canBeCancelledByUser(): bool
    {
        $service = (string) $this->service;

        return (int) $this->status === 1
            && $this->isRecurring()
            && app(PaymentManager::class)->has($service)
            && app(PaymentManager::class)->supports($service, 'user_cancel_recurring');
    }
}
