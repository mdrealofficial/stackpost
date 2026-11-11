<?php

use Illuminate\Support\Facades\Route;
use Modules\AppTeams\Http\Controllers\ReviewAttachmentController;
use Modules\AppTeams\Http\Controllers\SwitchWorkspaceController;
use Modules\AppTeams\Http\Controllers\TeamConversationStreamController;
use Modules\AppTeams\Livewire\Workspace;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appteams.route_prefix', 'portal/teams'))
    ->group(function (): void {
        Route::get('/reviews/{review}/attachments/{file}', ReviewAttachmentController::class)->whereNumber(['review', 'file'])->name('portal.teams.review-attachment');
        Route::get('/stream', TeamConversationStreamController::class)->name('portal.teams.stream');
        Route::get('/switch', SwitchWorkspaceController::class)->name('portal.teams.switch');
        Route::livewire('/', Workspace::class)->name('portal.teams');
        Route::livewire('/join/{inviteCode}', Workspace::class)->name('portal.teams.join');
    });
