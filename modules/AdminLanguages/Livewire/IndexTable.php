<?php

namespace Modules\AdminLanguages\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\AdminLanguages\Models\Language;
use Modules\AdminLanguages\Support\WorldLanguageCatalog;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminLanguages\Support\TranslationCatalog;
use Modules\AdminLanguages\Support\LanguageMaintenanceService;

#[Title('Admin Languages')]
class IndexTable extends Component
{
    use WithFileUploads;
    use WithPagination;

    protected string $pageName = 'page';

    protected LanguageMaintenanceService $maintenance;

    protected OptionStore $options;

    protected TranslationCatalog $catalog;

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 10;

    /** @var array<int> */
    public array $selected = [];

    public ?Language $editingLanguage = null;

    public bool $isEditing = false;

    public string $name = '';

    public string $native_name = '';

    public string $selectedLanguage = '';

    public string $code = '';

    public string $icon = '';

    public string $direction = 'ltr';

    public string $is_active = '1';

    public string $auto_translate = '1';

    public string $is_default = '0';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public mixed $catalogImportFile = null;

    public ?int $importLanguageId = null;

    public function boot(LanguageMaintenanceService $maintenance, OptionStore $options, TranslationCatalog $catalog): void
    {
        $this->maintenance = $maintenance;
        $this->options = $options;
        $this->catalog = $catalog;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedSelected($value): void
    {
        $this->selected = collect($this->selected)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function toggleSelectAllCurrentPage(): void
    {
        $pageIds = $this->currentPageIds();

        if ($pageIds === []) {
            return;
        }

        $selectedOnPage = array_intersect($pageIds, $this->selected);

        if (count($selectedOnPage) === count($pageIds)) {
            $this->selected = array_values(array_diff($this->selected, $pageIds));

            return;
        }

        $this->selected = collect([...$this->selected, ...$pageIds])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->perPage = 10;
        $this->resetPage($this->pageName);
        $this->clearSelection();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->dispatch('language-modal-open');
    }

    public function openEditModal(int $languageId): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $this->editingLanguage = Language::query()->findOrFail($languageId);
        $this->isEditing = true;
        $this->name = (string) $this->editingLanguage->name;
        $this->native_name = (string) ($this->editingLanguage->native_name ?? '');
        $this->selectedLanguage = (string) $this->editingLanguage->code;
        $this->code = (string) $this->editingLanguage->code;
        $this->icon = (string) ($this->editingLanguage->icon ?? '');
        $this->direction = (string) $this->editingLanguage->direction;
        $this->is_active = $this->editingLanguage->is_active ? '1' : '0';
        $this->auto_translate = $this->editingLanguage->auto_translate ? '1' : '0';
        $this->is_default = $this->editingLanguage->is_default ? '1' : '0';

        $this->dispatch('language-modal-open');
    }

    public function updatedSelectedLanguage(string $value): void
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            $this->code = '';
            $this->native_name = '';
            $this->icon = '';

            return;
        }

        $language = collect(WorldLanguageCatalog::all())
            ->firstWhere('code', $value);

        $this->code = $value;
        $this->native_name = (string) data_get($language, 'native_name', '');
        $this->icon = $this->defaultFlagForCode($value);
    }

    public function saveLanguage(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['nullable', 'string', 'max:255'],
            'selectedLanguage' => ['required', 'string', 'max:20'],
            'code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('languages', 'code')->ignore($this->editingLanguage?->id),
            ],
            'icon' => ['nullable', 'string', 'max:16'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'is_active' => ['required', Rule::in(['0', '1'])],
            'auto_translate' => ['required', Rule::in(['0', '1'])],
            'is_default' => ['required', Rule::in(['0', '1'])],
        ]);

        $payload = [
            'name' => $validated['name'],
            'native_name' => $validated['native_name'] ?: null,
            'code' => strtolower($validated['selectedLanguage']),
            'icon' => $validated['icon'] ?: $this->defaultFlagForCode((string) $validated['selectedLanguage']),
            'direction' => $validated['direction'],
            'is_active' => $validated['is_active'] === '1',
            'auto_translate' => $validated['auto_translate'] === '1',
            'is_default' => $validated['is_default'] === '1',
        ];

        if ($this->editingLanguage) {
            $this->editingLanguage->update($payload);
            $language = $this->editingLanguage;
        } else {
            $language = Language::query()->create($payload);
            $this->maintenance->createLanguage($language);
        }

        if ($language->is_default) {
            Language::query()->whereKeyNot($language->id)->update(['is_default' => false]);
            $language->forceFill(['is_default' => true, 'is_active' => true])->save();
            $this->options->set(config('localization.option_key'), $language->code);
        }

        $this->setStatus(__('Language saved successfully.'));
        $this->dispatch('language-modal-close');
        $this->resetForm();
    }

    public function bulkEnable(): void
    {
        $count = Language::query()->whereKey($this->selected)->update(['is_active' => true]);

        $this->setStatus(
            $count > 0
                ? __('Enabled :count languages.', ['count' => $count])
                : __('No languages were selected.')
        );

        $this->clearSelection();
    }

    public function bulkDisable(): void
    {
        $defaultCount = Language::query()
            ->whereKey($this->selected)
            ->where('is_default', true)
            ->count();

        $count = Language::query()
            ->whereKey($this->selected)
            ->where('is_default', false)
            ->update(['is_active' => false]);

        if ($count > 0) {
            $this->setStatus(__('Disabled :count languages.', ['count' => $count]));
        }

        if ($defaultCount > 0) {
            $this->setError(__('The default language cannot be disabled.'));
        }

        if ($count === 0 && $defaultCount === 0) {
            $this->setStatus(__('No languages were selected.'));
        }

        $this->clearSelection();
    }

    public function bulkDelete(): void
    {
        $defaultCount = Language::query()
            ->whereKey($this->selected)
            ->where('is_default', true)
            ->count();

        $count = Language::query()
            ->whereKey($this->selected)
            ->where('is_default', false)
            ->delete();

        if ($count > 0) {
            $this->setStatus(__('Deleted :count languages.', ['count' => $count]));
        }

        if ($defaultCount > 0) {
            $this->setError(__('The default language cannot be deleted.'));
        }

        if ($count === 0 && $defaultCount === 0) {
            $this->setStatus(__('No languages were selected.'));
        }

        $this->clearSelection();
        $this->resetPageIfNeeded();
    }

    public function setDefault(int $languageId): void
    {
        $language = Language::query()->findOrFail($languageId);

        Language::query()->whereKeyNot($language->id)->update(['is_default' => false]);
        $language->forceFill(['is_default' => true, 'is_active' => true])->save();
        $this->options->set(config('localization.option_key'), $language->code);

        $this->setStatus(__('Language saved successfully.'));
    }

    public function toggleActive(int $languageId): void
    {
        $language = Language::query()->findOrFail($languageId);

        if ($language->is_default && $language->is_active) {
            $this->setError(__('The default language cannot be disabled.'));

            return;
        }

        $language->update(['is_active' => ! $language->is_active]);

        $this->setStatus(__('Language saved successfully.'));
    }

    public function updateMissing(int $languageId): void
    {
        $language = Language::query()->findOrFail($languageId);
        $count = $this->maintenance->updateMissing($language);

        $this->setStatus(
            $count > 0
                ? __('Updated :count missing translations.', ['count' => $count])
                : __('No missing translations found.')
        );
    }

    public function autoTranslate(int $languageId): void
    {
        $language = Language::query()->findOrFail($languageId);
        $this->maintenance->createLanguage($language);

        $this->setStatus(__('Auto translation completed successfully.'));
    }

    public function importCatalog(): void
    {
        $validated = $this->validate([
            'catalogImportFile' => ['required', 'file', 'mimes:json'],
        ]);

        $language = $this->importLanguageId
            ? Language::query()->find($this->importLanguageId)
            : null;

        if (! $language) {
            $filename = pathinfo($validated['catalogImportFile']->getClientOriginalName(), PATHINFO_FILENAME);
            $locale = strtolower((string) $filename);
            $language = Language::query()->where('code', $locale)->first();
        }

        if (! $language) {
            $this->setError(__('No language exists for this import file. Choose a language first or use an exported locale filename.'));

            return;
        }

        $contents = file_get_contents($validated['catalogImportFile']->getRealPath());
        $payload = json_decode($contents ?: '{}', true);

        if (! is_array($payload)) {
            $this->setError(__('Import translations'));

            return;
        }

        $this->catalog->import($language->code, $payload);
        $this->reset('catalogImportFile', 'importLanguageId');
        $this->setStatus(__('Language saved successfully.'));
        $this->dispatch('language-import-modal-close');
    }

    public function openImportModal(int $languageId): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->reset('catalogImportFile');
        $this->importLanguageId = $languageId;
        $this->dispatch('language-import-modal-open');
    }

    public function exportLanguage(int $languageId)
    {
        $language = Language::query()->findOrFail($languageId);
        $json = json_encode(
            $this->catalog->export($language->code),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        return response()->streamDownload(
            static fn () => print($json ?: '{}'),
            $language->code.'.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function deleteLanguage(int $languageId): void
    {
        $language = Language::query()->findOrFail($languageId);

        if ($language->is_default) {
            $this->setError(__('The default language cannot be deleted.'));

            return;
        }

        $language->delete();

        $this->selected = array_values(array_diff($this->selected, [$languageId]));
        $this->setStatus(__('Language deleted successfully.'));
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $allLanguages = Language::query()->get(['is_active', 'is_default', 'auto_translate']);
        $languages = $this->query()
            ->paginate($this->perPage);

        $pageIds = $languages->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedOnPage = array_values(array_intersect($pageIds, $this->selected));

        return view('adminlanguages::livewire.index-table', [
            'languages' => $languages,
            'pageIds' => $pageIds,
            'allSelectedOnPage' => $pageIds !== [] && count($selectedOnPage) === count($pageIds),
            'someSelectedOnPage' => count($selectedOnPage) > 0 && count($selectedOnPage) < count($pageIds),
            'summary' => [
                'total' => $allLanguages->count(),
                'active' => $allLanguages->where('is_active', true)->count(),
                'default' => $allLanguages->where('is_default', true)->count(),
                'auto_translate' => $allLanguages->where('auto_translate', true)->count(),
            ],
            'languageCatalog' => collect(WorldLanguageCatalog::all())
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Admin Languages'),
        ]);
    }

    protected function query(): Builder
    {
        return Language::query()
            ->ordered()
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $nested) {
                    $nested
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('native_name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($this->statusFilter === 'default', fn (Builder $query) => $query->where('is_default', true));
    }

    protected function currentPageIds(): array
    {
        return $this->query()
            ->paginate($this->perPage, ['*'], $this->pageName, $this->getPage($this->pageName))
            ->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->getPage($this->pageName) > 1
            && $this->query()->paginate($this->perPage, ['*'], $this->pageName, $this->getPage($this->pageName))->isEmpty()) {
            $this->previousPage($this->pageName);
        }
    }

    protected function setStatus(string $message): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
    }

    protected function setError(string $message): void
    {
        $this->errorMessage = $message;
    }

    protected function resetForm(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->editingLanguage = null;
        $this->isEditing = false;
        $this->name = '';
        $this->native_name = '';
        $this->selectedLanguage = '';
        $this->code = '';
        $this->icon = '';
        $this->direction = config('localization.default_direction', 'ltr');
        $this->is_active = '1';
        $this->auto_translate = '1';
        $this->is_default = '0';
        $this->importLanguageId = null;
    }

    protected function defaultFlagForCode(string $code): string
    {
        $code = strtolower(trim($code));

        return match ($code) {
            'en' => 'us',
            'vi' => 'vn',
            'zh', 'zh-cn' => 'cn',
            'zh-tw' => 'tw',
            'ja' => 'jp',
            'ko' => 'kr',
            'ar' => 'ae',
            'pt-br' => 'br',
            'pt' => 'pt',
            'hi' => 'in',
            'uk' => 'ua',
            default => str((string) $code)->before('-')->lower()->value(),
        };
    }
}
