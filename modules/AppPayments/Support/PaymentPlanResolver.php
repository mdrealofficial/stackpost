<?php

namespace Modules\AppPayments\Support;

use Modules\AdminPlans\Models\AdminPlan;

class PaymentPlanResolver
{
    public function resolve(string|int $source): ?AdminPlan
    {
        return AdminPlan::query()
            ->where('status', true)
            ->where(function ($query) use ($source): void {
                $query->where('slug', (string) $source);

                if (is_numeric($source)) {
                    $query->orWhere('id', (int) $source);
                }

                if (\Schema::hasColumn('plans', 'id_secure')) {
                    $query->orWhere('id_secure', (string) $source);
                }
            })
            ->first();
    }
}
