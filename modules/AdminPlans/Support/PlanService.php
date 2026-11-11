<?php

namespace Modules\AdminPlans\Support;

class PlanService
{
    public function getTypes(): array
    {
        return [
            1 => __('Monthly'),
            2 => __('Yearly'),
            3 => __('Lifetime'),
        ];
    }
}
