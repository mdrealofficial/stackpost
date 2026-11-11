<?php

namespace Modules\AdminPlans\Facades;

use Illuminate\Support\Facades\Facade;

class Plan extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Modules\AdminPlans\Support\PlanService::class;
    }
}
