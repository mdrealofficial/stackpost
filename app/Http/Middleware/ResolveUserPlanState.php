<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AppPayments\Support\UserPlanTransitionService;
use Symfony\Component\HttpFoundation\Response;

class ResolveUserPlanState
{
    public function __construct(
        protected UserPlanTransitionService $planTransitions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $this->planTransitions->activateScheduledPlanIfDue($user);
        }

        return $next($request);
    }
}
