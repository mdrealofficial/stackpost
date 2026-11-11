<?php

namespace Modules\AppAffiliate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class AffiliateProfile extends Model
{
    protected $table = 'affiliate_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
            'conversions' => 'integer',
            'total_approved' => 'decimal:2',
            'total_withdrawal' => 'decimal:2',
            'total_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
