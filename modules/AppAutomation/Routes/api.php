<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAutomation\Http\Controllers\AutomationApiController;
use Modules\AppAutomation\Http\Controllers\GoogleDriveNotificationController;

Route::middleware(['api'])
    ->prefix(config('modules.appautomation.api_prefix', 'api/automation/v1'))
    ->name('api.automation.')
    ->group(function (): void {
        Route::get('/me', [AutomationApiController::class, 'me'])->name('me');
        Route::get('/accounts', [AutomationApiController::class, 'accounts'])->name('accounts');
        Route::get('/posts', [AutomationApiController::class, 'listPosts'])->name('posts.index');
        Route::get('/posts/{id}', [AutomationApiController::class, 'getPost'])->name('posts.show');
        Route::post('/posts', [AutomationApiController::class, 'createPost'])
            ->middleware('throttle:automation-api-posts')
            ->name('posts.store');
        Route::post('/google-drive/notifications', GoogleDriveNotificationController::class)
            ->middleware('throttle:120,1')
            ->name('google-drive.notifications');
    });
