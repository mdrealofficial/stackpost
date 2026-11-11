<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ValidateSignature;

class ValidateEmailVerificationSignature extends ValidateSignature
{
    public function handle($request, Closure $next, ...$args)
    {
        [, $ignore] = $this->parseArguments($args);

        if ($request->route()?->named('verification.verify')
            && $request->hasValidSignatureWhileIgnoring($ignore, false)
        ) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$args);
    }
}
