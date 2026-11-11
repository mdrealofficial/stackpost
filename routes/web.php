<?php

use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\GuestBlogMediaController;
use App\Http\Controllers\GuestMarketingController;
use App\Http\Controllers\GuestStaticPageController;
use App\Livewire\Portal\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth_landing_enabled()) {
        return auth()->check()
            ? redirect()->route('portal.dashboard')
            : redirect()->route('login');
    }

    return app(GuestMarketingController::class)->home();
})->name('home');
Route::get('/pricing', [GuestMarketingController::class, 'pricing'])->name('guest.pricing');
Route::get('/faqs', [GuestMarketingController::class, 'faqs'])->name('guest.faqs');
Route::get('/blogs', [GuestMarketingController::class, 'blogs'])->name('guest.blogs');
Route::get('/contact', [GuestMarketingController::class, 'contact'])->name('guest.contact');
Route::get('/blogs/{slug}', [GuestMarketingController::class, 'blogShow'])->name('guest.blog-show');
Route::get('/blogs/{slug}/media/{fileKey}', GuestBlogMediaController::class)->name('guest.blog-media');
Route::get('/privacy-policy', [GuestStaticPageController::class, 'privacyPolicy'])->name('guest.privacy-policy');
Route::get('/terms-of-use', [GuestStaticPageController::class, 'termsOfUse'])->name('guest.terms-of-use');
Route::get('/social-pages', [GuestStaticPageController::class, 'socialPages'])->name('guest.social-pages');

Route::middleware('guest')->prefix('auth/login')->group(function (): void {
    Route::get('/{provider}', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook', 'x'])
        ->name('auth.social.redirect');
    Route::get('/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook', 'x'])
        ->name('auth.social.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('portal/dashboard', Dashboard::class)->name('portal.dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/public-storage.php';
