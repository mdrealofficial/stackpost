<?php

namespace Modules\AdminDashboard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminDashboardController extends Controller
{
    public function updateLayout(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['string'],
        ]);

        save_admin_dashboard_layout($request->user(), $payload['item_ids']);

        return response()->json([
            'status' => 'ok',
            'layout' => admin_dashboard_layout($request->user()),
        ]);
    }
}
