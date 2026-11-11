<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminCaptcha\Livewire\CaptchaSettings;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('settings/captcha', CaptchaSettings::class);
});

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('captcha', CaptchaSettings::class)->name('admin-captcha.index');
});
