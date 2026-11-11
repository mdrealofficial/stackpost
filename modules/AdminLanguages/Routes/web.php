<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminLanguages\Livewire\IndexTable;
use Modules\AdminLanguages\Livewire\TranslationsPage;
use Modules\AdminLanguages\Models\Language;
use Modules\AdminLanguages\Support\LocaleManager;
use Modules\AdminLanguages\Support\TranslationCatalog;

Route::middleware('web')->group(function () {
    Route::get('lang/{locale}', function (string $locale, LocaleManager $locales) {
        $locale = strtolower($locale);

        if (! in_array($locale, $locales->supportedCodes(), true)) {
            abort(404);
        }

        $locales->persist(request(), $locale);
        $locales->apply($locale);

        return redirect()->to(url()->previous() ?: route('home'))
            ->with('status', __('Language switched successfully.'));
    })->name('language.switch');
});

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::prefix('admin/languages')->group(function () {
        Route::get('/', IndexTable::class)->name('admin-languages.index');
        Route::get('{language}/translations', TranslationsPage::class)->name('admin-languages.translations');
        Route::get('{language}/export', function (Language $language, TranslationCatalog $catalog) {
            $json = json_encode(
                $catalog->export($language->code),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );

            return response($json ?: '{}', 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$language->code.'.json"',
            ]);
        })->name('admin-languages.export');
    });
});
