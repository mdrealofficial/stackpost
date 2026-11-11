<?php

namespace Modules\AdminPaymentHistory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

class PaymentHistory extends Model
{
    protected $table = 'payment_history';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => 'integer',
            'created' => 'integer',
            'changed' => 'integer',
            'meta' => 'array',
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
            1 => __('Success'),
            0 => __('Refund'),
            default => __('Pending'),
        };
    }

    public function statusVariant(): string
    {
        return match ((int) $this->status) {
            1 => 'success',
            0 => 'danger',
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
}
