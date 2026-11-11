<?php

use App\Installer\Http\Middleware\PrepareInstallation;
use App\Exceptions\DemoModeRestrictedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\InjectDemoThemeSwitcher;
use App\Http\Middleware\PreventDemoModeWriteOperations;
use App\Http\Middleware\ResolveUserPlanState;
use App\Http\Middleware\ValidateEmailVerificationSignature;
use Illuminate\Http\Request;
use Modules\AdminLanguages\Http\Middleware\SetLocale;
use Modules\AdminThemes\Http\Middleware\SetThemeContext;
use Modules\AppAffiliate\Http\Middleware\CaptureAffiliateReferral;

// Apache on Windows shares a process across requests. Keep dotenv values
// request-local so another Laravel project cannot supply this app's database.
if (PHP_OS_FAMILY === 'Windows' && PHP_SAPI === 'apache2handler') {
    \Illuminate\Support\Env::disablePutenv();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'livewire/upload-file',
            'livewire-*/upload-file',
        ]);

        $middleware->alias([
            'signed' => ValidateEmailVerificationSignature::class,
        ]);

        $middleware->web(prepend: [
            PrepareInstallation::class,
        ], append: [
            SetLocale::class,
            SetThemeContext::class,
            CaptureAffiliateReferral::class,
            ResolveUserPlanState::class,
            EnsureAdminAccess::class,
            PreventDemoModeWriteOperations::class,
            InjectDemoThemeSwitcher::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DemoModeRestrictedException $exception, Request $request) {
            if ($request->expectsJson()
                || $request->route()?->named('*livewire.update')
                || preg_match('#(^|/)livewire(?:-[^/]+)?/update$#', trim($request->path(), '/')) === 1
            ) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'demo_mode' => true,
                ], 403);
            }

            return back()->with('warning', $exception->getMessage());
        });
    })->create();
