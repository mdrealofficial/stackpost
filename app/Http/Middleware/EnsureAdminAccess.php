<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAdminRequest($request)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! method_exists($user, 'canAccessAdmin') || ! $user->canAccessAdmin()) {
            return redirect()
                ->route('portal.dashboard')
                ->with('error', __('Your account does not have access to the admin workspace.'));
        }

        $routeName = $request->route()?->getName();

        if (method_exists($user, 'canAccessAdminRoute') && ! $user->canAccessAdminRoute($routeName)) {
            abort(403, __('You do not have permission to access this admin page.'));
        }

        return $next($request);
    }

    protected function isAdminRequest(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === 'dashboard' || str_starts_with($routeName, 'dashboard.')) {
            return true;
        }

        if (str_starts_with($routeName, 'admin.') || str_starts_with($routeName, 'admin-')) {
            return true;
        }

        if (str_starts_with($routeName, 'settings.')) {
            return true;
        }

        return $request->is('admin') || $request->is('admin/*');
    }
}
