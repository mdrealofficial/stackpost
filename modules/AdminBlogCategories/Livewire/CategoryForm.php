<?php

namespace Modules\AdminBlogCategories\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;

class CategoryForm extends Component
{
    protected MultilingualContentGenerator $contentGenerator;

    public ?BlogCategory $category = null;

    /** @var array<string, string> */
    public array $nameTranslations = [];

    /** @var array<string, string> */
    public array $descriptionTranslations = [];

    public string $slug = '';

    public string $icon = '';

    public string $color = '#0f766e';

    public string $sortOrder = '0';

    public string $status = '1';

    public bool $autoTranslateMissing = true;

    public ?string $statusMessage = null;

    public bool $isEditing = false;

    public bool $slugManuallyEdited = false;

    public function boot(MultilingualContentGenerator $contentGenerator): void
    {
        $this->contentGenerator = $contentGenerator;
    }

    public function mount(?BlogCategory $blogCategory = null): void
    {
        $this->category = $blogCategory?->exists ? $blogCategory : null;
        $this->isEditing = $this->category !== null;

        foreach ($this->languages() as $language) {
            $code = strtolower((string) data_get($language, 'code', $this->defaultLocale()));
            $this->nameTranslations[$code] = '';
            $this->descriptionTranslations[$code] = '';
        }

        if ($this->category) {
            foreach ($this->nameTranslations as $code => $value) {
                $this->nameTranslations[$code] = (string) data_get($this->category->name_translations, $code, $code === $this->defaultLocale() ? $this->category->name : '');
                $this->descriptionTranslations[$code] = (string) data_get($this->category->description_translations, $code, $code === $this->defaultLocale() ? $this->category->description : '');
            }

            $this->slug = (string) $this->category->slug;
            $this->slugManuallyEdited = $this->slug !== '';
            $this->icon = (string) ($this->category->icon ?? '');
            $this->color = (string) ($this->category->color ?: '#0f766e');
            $this->sortOrder = (string) ($this->category->sort_order ?? 0);
            $this->status = $this->category->status ? '1' : '0';

            return;
        }

        $this->status = '1';
        $this->slugManuallyEdited = false;
    }

    public function save(): mixed
    {
        $validated = $this->validate([
            'nameTranslations' => ['required', 'array'],
            'nameTranslations.*' => ['nullable', 'string', 'max:255'],
            'descriptionTranslations' => ['nullable', 'array'],
            'descriptionTranslations.*' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_categories', 'slug')->ignore($this->category?->id)],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:32'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:0,1'],
            'autoTranslateMissing' => ['nullable', 'boolean'],
        ]);

        $defaultLocale = $this->defaultLocale();
        $nameTranslations = BlogCategory::cleanTranslations($validated['nameTranslations'] ?? [], 255);

        if (($nameTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "nameTranslations.{$defaultLocale}" => __('The default language name is required.'),
            ]);
        }

        $descriptionTranslations = BlogCategory::cleanTranslations($validated['descriptionTranslations'] ?? []);

        if ($this->autoTranslateMissing) {
            $generated = $this->contentGenerator->completeFields([
                'name' => $nameTranslations,
                'description' => $descriptionTranslations,
            ], [
                'name' => 255,
            ]);

            $nameTranslations = $generated['name'];
            $descriptionTranslations = $generated['description'];
            $this->nameTranslations = $nameTranslations;
            $this->descriptionTranslations = $descriptionTranslations;
        }

        $defaultName = $nameTranslations[$defaultLocale];

        $payload = [
            'id_secure' => $this->category?->id_secure ?: Str::random(32),
            'name' => $defaultName,
            'name_translations' => $nameTranslations,
            'description' => $descriptionTranslations[$defaultLocale] ?? null,
            'description_translations' => $descriptionTranslations,
            'slug' => Str::slug(trim($validated['slug'] ?: $defaultName)),
            'icon' => trim((string) $validated['icon']) ?: null,
            'color' => trim((string) $validated['color']),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
            'status' => (bool) ((int) $validated['status']),
            'changed' => time(),
            'created' => $this->category?->created ?: time(),
        ];

        if ($this->category) {
            $this->category->update($payload);

            log_activity('admin.blog-categories.update', 'Updated a blog category.', [
                'subject_type' => BlogCategory::class,
                'subject_id' => $this->category->id,
                'metadata' => [
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ],
            ]);

            $this->statusMessage = __('Blog category updated successfully.');

            return null;
        }

        $this->category = BlogCategory::query()->create($payload);
        $this->isEditing = true;

        log_activity('admin.blog-categories.create', 'Created a blog category.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => $this->category->id,
            'metadata' => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
        ]);

        return $this->redirect(route('admin-blog-categories.edit', $this->category), navigate: true);
    }

    public function delete(): mixed
    {
        abort_unless($this->category, 404);

        $categoryId = $this->category->id;
        $metadata = ['name' => $this->category->name, 'slug' => $this->category->slug];

        $this->category->delete();

        log_activity('admin.blog-categories.delete', 'Deleted a blog category.', [
            'subject_type' => BlogCategory::class,
            'subject_id' => $categoryId,
            'metadata' => $metadata,
        ]);

        return $this->redirect(route('admin-blog-categories.index'), navigate: true);
    }

    public function render(): View
    {
        return view('adminblogcategories::livewire.form', [
            'languages' => $this->languages(),
            'defaultLocale' => $this->defaultLocale(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->isEditing ? __('Edit Blog Category') : __('Create Blog Category'),
        ]);
    }

    protected function defaultLocale(): string
    {
        return function_exists('locale_manager')
            ? locale_manager()->defaultCode()
            : (string) config('app.fallback_locale', 'en');
    }

    protected function languages(): Collection
    {
        $languages = function_exists('available_languages') ? available_languages() : collect();

        if ($languages->isEmpty()) {
            $languages = collect([(object) ['code' => $this->defaultLocale(), 'name' => strtoupper($this->defaultLocale())]]);
        }

        return $languages;
    }
}
