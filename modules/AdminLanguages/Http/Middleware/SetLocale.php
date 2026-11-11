<?php

namespace Modules\AdminLanguages\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AdminLanguages\Support\LocaleManager;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(
        protected LocaleManager $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locales->resolve($request);

        $this->locales->apply($locale);

        view()->share('currentLocale', $locale);
        app()->instance(LocaleManager::class, $this->locales);

        return $next($request);
    }
}
