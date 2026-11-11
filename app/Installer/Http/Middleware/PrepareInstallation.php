<?php

namespace App\Installer\Http\Middleware;

use App\Installer\Support\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class PrepareInstallation
{
    public function __construct(
        protected InstallerState $state,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->state->isInstalled()) {
            Config::set('session.driver', 'file');
            Config::set('session.connection', null);
            Config::set('cache.default', 'file');

            if (! $this->state->isInstallerRequest($request)) {
                return redirect()->route('installer.index');
            }
        }

        return $next($request);
    }
}
