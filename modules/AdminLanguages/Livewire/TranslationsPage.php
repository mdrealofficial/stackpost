<?php

namespace Modules\AdminLanguages\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\AdminLanguages\Models\Language;
use Modules\AdminLanguages\Support\LanguageMaintenanceService;
use Modules\AdminLanguages\Support\TranslationCatalog;

#[Title('Translation overrides')]
class TranslationsPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    protected string $pageName = 'page';

    public Language $language;

    public string $search = '';

    public array $translations = [];

    public array $fieldMap = [];

    public mixed $importFile = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    protected TranslationCatalog $catalog;

    protected LanguageMaintenanceService $maintenance;

    public function boot(TranslationCatalog $catalog, LanguageMaintenanceService $maintenance): void
    {
        $this->catalog = $catalog;
        $this->maintenance = $maintenance;
    }

    public function mount(Language $language): void
    {
        $this->language = $language;
    }

    public function updatingSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    public function saveTranslations(): void
    {
        $payload = [];

        foreach ($this->translations as $field => $value) {
            $key = $this->fieldMap[$field] ?? null;

            if (! is_string($key)) {
                continue;
            }

            $payload[$key] = (string) $value;
        }

        $this->catalog->saveOverrides($this->language->code, $payload);
        $this->statusMessage = __('Language saved successfully.');
        $this->errorMessage = null;
    }

    public function importTranslations(): void
    {
        $validated = $this->validate([
            'importFile' => ['required', 'file', 'mimes:json'],
        ]);

        $contents = file_get_contents($validated['importFile']->getRealPath());
        $payload = json_decode($contents ?: '{}', true);

        if (! is_array($payload)) {
            $this->errorMessage = __('Import translations');
            $this->statusMessage = null;

            return;
        }

        $this->catalog->import($this->language->code, $payload);
        $this->reset('importFile');
        $this->statusMessage = __('Language saved successfully.');
        $this->errorMessage = null;
        $this->resetPage($this->pageName);
    }

    public function updateLanguages(): void
    {
        $count = $this->maintenance->updateMissing($this->language);

        $this->statusMessage = $count > 0
            ? __('Updated :count missing translations.', ['count' => $count])
            : __('No missing translations found.');

        $this->errorMessage = null;
    }

    public function autoTranslate(): void
    {
        $this->maintenance->createLanguage($this->language);

        $this->statusMessage = __('Auto translation completed successfully.');
        $this->errorMessage = null;
    }

    public function render(): View
    {
        $items = $this->catalog->paginatedForLocale(
            $this->language->code,
            $this->search,
            30
        );

        $this->fieldMap = [];

        foreach ($items as $item) {
            $field = $this->fieldName((string) $item['key']);
            $this->fieldMap[$field] = (string) $item['key'];

            if (! array_key_exists($field, $this->translations)) {
                $this->translations[$field] = (string) ($item['override'] ?? $item['value']);
            }
        }

        return view('adminlanguages::livewire.translations-page', [
            'items' => $items,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Translation overrides'),
        ]);
    }

    public function fieldName(string $key): string
    {
        return 'k_'.md5($key);
    }
}
