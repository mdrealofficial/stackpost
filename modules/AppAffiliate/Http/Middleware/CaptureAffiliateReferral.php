<?php

namespace Modules\AppAffiliate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AppAffiliate\Support\AffiliateService;

class CaptureAffiliateReferral
{
    public function __construct(
        protected AffiliateService $affiliate,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $this->affiliate->captureReferral($request);

        return $next($request);
    }
}
