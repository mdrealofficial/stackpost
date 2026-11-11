<?php

namespace Modules\AdminPlans\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\AdminPlans\Models\AdminPlan;

class DefaultSignupPlanResolver
{
    public function resolve(): ?AdminPlan
    {
        return AdminPlan::query()
            ->where('status', true)
            ->where(function ($query): void {
                $query->where('free_plan', true)
                    ->orWhere(function ($query): void {
                        $query->where('default_signup_plan', true)
                            ->where('price', '<=', 0)
                            ->where('trial_day', '>', 0);
                    });
            })
            ->orderByDesc('default_signup_plan')
            ->orderByDesc('featured')
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }

    public function expiresAt(?AdminPlan $plan, ?CarbonInterface $startedAt = null): ?Carbon
    {
        if (! $plan || $plan->free_plan || (int) $plan->trial_day <= 0) {
            return null;
        }

        return Carbon::parse($startedAt ?: now())->addDays((int) $plan->trial_day);
    }
}
