<?php

namespace Modules\AdminManualPayments\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

class ManualPayment extends Model
{
    protected $table = 'payment_manual';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'plan_id' => 'integer',
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
            1 => __('Approved'),
            2 => __('Cancelled'),
            default => __('Pending'),
        };
    }

    public function statusVariant(): string
    {
        return match ((int) $this->status) {
            1 => 'success',
            2 => 'danger',
            default => 'primary',
        };
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->created) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->created)->format($format);
    }

    public function amountLabel(): string
    {
        return number_format((float) $this->amount, 2).' '.strtoupper((string) $this->currency);
    }
}
