<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminNotifications\Http\Controllers\NotificationPanelController;
use Modules\AdminNotifications\Livewire\NotificationIndex;

Route::middleware(['web', 'auth', 'verified'])
    ->group(function (): void {
        Route::prefix('admin/notifications')
            ->name('admin-notifications.')
            ->group(function (): void {
                Route::get('/', NotificationIndex::class)->name('index');

                Route::get('/panel/feed', [NotificationPanelController::class, 'panel'])->name('panel');
                Route::post('/panel/read-all', [NotificationPanelController::class, 'markAllRead'])->name('read-all');
                Route::post('/panel/archive-all', [NotificationPanelController::class, 'archiveAll'])->name('archive-all');
                Route::post('/panel/{notificationKey}/read', [NotificationPanelController::class, 'markRead'])->name('read');
            });

        Route::prefix('portal/notifications')
            ->name('portal-notifications.')
            ->group(function (): void {
                Route::get('/panel/feed', [NotificationPanelController::class, 'panel'])->name('panel');
                Route::post('/panel/read-all', [NotificationPanelController::class, 'markAllRead'])->name('read-all');
                Route::post('/panel/archive-all', [NotificationPanelController::class, 'archiveAll'])->name('archive-all');
                Route::post('/panel/{notificationKey}/read', [NotificationPanelController::class, 'markRead'])->name('read');
            });
    });
