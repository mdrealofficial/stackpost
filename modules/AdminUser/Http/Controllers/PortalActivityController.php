<?php

namespace Modules\AdminUser\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\AdminUser\Models\AuditLog;

class PortalActivityController extends Controller
{
    public function index(): View
    {
        return view('adminuser::portal.activity', [
            'logs' => AuditLog::query()
                ->with('causer')
                ->where('causer_user_id', auth()->id())
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }
}
