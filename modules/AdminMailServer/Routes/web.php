<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminMailServer\Livewire\MailServerSettings;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('settings/mail-server', MailServerSettings::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('mail-server', MailServerSettings::class)->name('settings.mail-server');
});
