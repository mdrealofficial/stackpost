<?php

namespace Modules\AdminSettings\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    public function __invoke()
    {
        Session::forget([
            'impersonator_id',
            'impersonator_name',
            'impersonator_redirect',
        ]);

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
