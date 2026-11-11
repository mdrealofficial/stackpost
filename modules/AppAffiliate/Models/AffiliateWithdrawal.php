<?php

namespace Modules\AppAffiliate\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class AffiliateWithdrawal extends Model
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $table = 'affiliate_withdrawals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function affiliateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function statusLabel(): string
    {
        return match ((int) $this->status) {
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
            default => __('Pending'),
        };
    }

    public function statusVariant(): string
    {
        return match ((int) $this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'primary',
        };
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        return $this->created_at ? Carbon::parse($this->created_at)->format($format) : null;
    }
}
