<?php

namespace Modules\AppCredits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminUser\Models\User;

class CreditTopupLedger extends Model
{
    protected $table = 'credit_topup_ledgers';

    protected $fillable = [
        'user_id',
        'credit_pack_id',
        'payment_history_id',
        'type',
        'amount',
        'remaining',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'remaining' => 'integer',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditPack(): BelongsTo
    {
        return $this->belongsTo(CreditPack::class);
    }

    public function paymentHistory(): BelongsTo
    {
        return $this->belongsTo(PaymentHistory::class);
    }
}
