<?php

namespace Modules\AppPayments\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PortalPackagesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $pricing = \Pricing::plansWithFeatures();
        $planTypes = collect(\Plan::getTypes())
            ->filter(fn ($label, $key) => ! empty($pricing[$key] ?? []));
        $plans = collect($pricing)
            ->flatten(1)
            ->values();

        $startingAt = $plans
            ->filter(fn (array $plan): bool => ! (bool) ($plan['free_plan'] ?? false))
            ->min('price');

        return view('apppayments::portal.packages', [
            'user' => $user,
            'pricing' => $pricing,
            'planTypes' => $planTypes,
            'defaultType' => (int) ($planTypes->keys()->first() ?? 1),
            'summary' => [
                'total' => $plans->count(),
                'featured' => $plans->where('featured', true)->count(),
                'free' => $plans->where('free_plan', true)->count(),
                'startingAt' => $startingAt,
            ],
        ]);
    }
}
