<?php

namespace App\Installer\Http\Middleware;

use App\Installer\Support\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function __construct(
        protected InstallerState $state,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->isInstalled() && (string) $request->query('step') !== 'finish') {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
