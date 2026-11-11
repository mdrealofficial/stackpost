<?php

use Illuminate\Support\Facades\Route;
use Modules\AppFiles\Http\Controllers\AdminFileController;
use Modules\AppFiles\Http\Controllers\PortalFileController;
use Modules\AppFiles\Livewire\AdminImageEditor;
use Modules\AppFiles\Livewire\AdminIndex;
use Modules\AppFiles\Livewire\PortalIndex;
use Modules\AppFiles\Livewire\PortalImageEditor;
use Modules\AppFiles\Livewire\PortalSearchOnline;
use Modules\AppFiles\Livewire\Settings;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('settings/files', Settings::class);
    Route::view('/appfiles/picker/onedrive/callback', 'appfiles::onedrive-callback')->name('appfiles.onedrive-callback');
    Route::view('/appfiles/editor/picker-callback', 'appfiles::editor-callback')->name('appfiles.editor-callback');
    // Keep the image editor return endpoints out of Livewire's own /livewire-*/update namespace.
    Route::get('/appfiles/editor/livewire-{asset}/update', function () {
        $target = session('appfiles.image_editor_return_url');

        if (is_string($target) && str_starts_with($target, '/')) {
            return redirect($target);
        }

        return redirect()->route('portal.files.index');
    })->where('asset', '[^/]+');
    Route::get('/{prefix}/appfiles/editor/livewire-{asset}/update', function () {
        $target = session('appfiles.image_editor_return_url');

        if (is_string($target) && str_starts_with($target, '/')) {
            return redirect($target);
        }

        return redirect()->route('portal.files.index');
    })->where('prefix', '.*')->where('asset', '[^/]+');
});

Route::middleware(['web', 'signed'])
    ->prefix(config('modules.appfiles.portal_route_prefix', 'portal/files'))
    ->name('portal.files.')
    ->group(function (): void {
        Route::get('/publish/{file}/preview', [PortalFileController::class, 'publishPreview'])->name('publish-preview');
    });

Route::prefix('admin/settings')->middleware(['web', 'auth'])->group(function (): void {
    Route::livewire('files', Settings::class)->name('settings.files');
});

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appfiles.admin_route_prefix', 'admin/files'))
    ->name('admin-files.')
    ->group(function (): void {
        Route::get('/', AdminIndex::class)->name('index');
        Route::get('/browse', [AdminFileController::class, 'browse'])->name('browse');
        Route::get('/picker/images', [AdminFileController::class, 'pickerImages'])->name('picker-images');
        Route::post('/picker/images/folder', [AdminFileController::class, 'pickerCreateFolder'])->name('picker-create-folder');
        Route::delete('/picker/images/{fileKey}', [AdminFileController::class, 'pickerDestroy'])->name('picker-destroy');
        Route::post('/picker/images/upload', [AdminFileController::class, 'pickerUpload'])->name('picker-upload');
        Route::post('/picker/images/upload-from-url', [AdminFileController::class, 'pickerUploadFromUrl'])->name('picker-upload-from-url');
        Route::post('/picker/images/search-online', [AdminFileController::class, 'pickerSearchOnline'])->name('picker-search-online');
        Route::post('/picker/images/import-google-drive', [AdminFileController::class, 'pickerImportGoogleDrive'])->name('picker-import-google-drive');
        Route::post('/picker/images/import-adobe-express', [AdminFileController::class, 'pickerImportAdobeExpress'])->name('picker-import-adobe-express');
        Route::get('/{file}/preview', [AdminFileController::class, 'preview'])->name('preview');
        Route::get('/{file}/download', [AdminFileController::class, 'download'])->name('download');
        Route::livewire('/{file}/edit-image', AdminImageEditor::class)->name('edit-image');
        Route::post('/{file}/edit-image', [AdminFileController::class, 'updateImage'])->name('update-image');
    });

Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('modules.appfiles.portal_route_prefix', 'portal/files'))
    ->name('portal.files.')
    ->group(function (): void {
        Route::livewire('/', PortalIndex::class)->name('index');
        Route::livewire('/search-online', PortalSearchOnline::class)->name('search-online');
        Route::get('/picker/media', [PortalFileController::class, 'pickerMedia'])->name('picker-media');
        Route::post('/picker/media/folder', [PortalFileController::class, 'pickerCreateFolder'])->name('picker-create-folder');
        Route::delete('/picker/media/{fileKey}', [PortalFileController::class, 'pickerDestroy'])->name('picker-destroy');
        Route::post('/picker/media/upload', [PortalFileController::class, 'pickerUpload'])->name('picker-upload');
        Route::post('/picker/media/upload-from-url', [PortalFileController::class, 'pickerUploadFromUrl'])->name('picker-upload-from-url');
        Route::post('/picker/media/search-online', [PortalFileController::class, 'pickerSearchOnline'])->name('picker-search-online');
        Route::post('/picker/media/import-google-drive', [PortalFileController::class, 'pickerImportGoogleDrive'])->name('picker-import-google-drive');
        Route::post('/picker/media/import-adobe-express', [PortalFileController::class, 'pickerImportAdobeExpress'])->name('picker-import-adobe-express');
        Route::get('/{file}/preview', [PortalFileController::class, 'preview'])->name('preview');
        Route::get('/{file}/download', [PortalFileController::class, 'download'])->name('download');
        Route::get('/{file}/edit-image', [PortalFileController::class, 'edit'])->name('edit-image');
        Route::post('/{file}/edit-image', [PortalFileController::class, 'updateImage'])->name('update-image');
        Route::delete('/{file}', [PortalFileController::class, 'destroy'])->name('destroy');
    });
