<?php

namespace Modules\AppCredits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

class CreditUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'action_key',
        'feature',
        'amount',
        'unit_cost',
        'quantity',
        'credits_before',
        'credits_after',
        'is_unlimited',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'unit_cost' => 'integer',
            'quantity' => 'integer',
            'credits_before' => 'integer',
            'credits_after' => 'integer',
            'is_unlimited' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AdminPlan::class, 'plan_id');
    }
}
