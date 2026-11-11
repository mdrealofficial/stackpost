<?php

namespace Modules\AdminPlans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AdminPlans\Support\CurrencyCatalog;
use Modules\AdminUser\Models\User;

class AdminPlan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'featured',
        'currency',
        'price',
        'type',
        'free_plan',
        'default_signup_plan',
        'trial_day',
        'position',
        'desc',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'featured' => 'boolean',
            'price' => 'decimal:2',
            'free_plan' => 'boolean',
            'default_signup_plan' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function getCurrencyAttribute($value): string
    {
        return CurrencyCatalog::normalizeCode($value);
    }

    public function setCurrencyAttribute($value): void
    {
        $this->attributes['currency'] = CurrencyCatalog::normalizeCode($value);
    }

    public function getCurrencySymbolAttribute(): string
    {
        return CurrencyCatalog::symbolFor($this->attributes['currency'] ?? 'USD');
    }

    public function getCurrencyNameAttribute(): string
    {
        return CurrencyCatalog::nameFor($this->attributes['currency'] ?? 'USD');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'plan_id');
    }

    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class, 'plan_id');
    }
}
