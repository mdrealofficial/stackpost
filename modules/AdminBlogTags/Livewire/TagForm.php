<?php

namespace Modules\AdminBlogTags\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;
use Modules\AdminBlogTags\Models\BlogTag;

class TagForm extends Component
{
    protected MultilingualContentGenerator $contentGenerator;

    public ?BlogTag $tag = null;

    /** @var array<string, string> */
    public array $nameTranslations = [];

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $icon = '';

    public string $color = '#0f766e';

    public string $status = '1';

    public bool $autoTranslateMissing = true;

    public ?string $statusMessage = null;

    public bool $isEditing = false;

    public function boot(MultilingualContentGenerator $contentGenerator): void
    {
        $this->contentGenerator = $contentGenerator;
    }

    public function mount(?BlogTag $blogTag = null): void
    {
        $this->tag = $blogTag?->exists ? $blogTag : null;
        $this->isEditing = $this->tag !== null;

        foreach ($this->languages() as $language) {
            $code = strtolower((string) data_get($language, 'code', $this->defaultLocale()));
            $this->nameTranslations[$code] = '';
        }

        if ($this->tag) {
            foreach ($this->nameTranslations as $code => $value) {
                $this->nameTranslations[$code] = (string) data_get($this->tag->name_translations, $code, $code === $this->defaultLocale() ? $this->tag->name : '');
            }

            $this->slug = (string) $this->tag->slug;
            $this->slugManuallyEdited = $this->slug !== '';
            $this->icon = (string) ($this->tag->icon ?? '');
            $this->color = (string) ($this->tag->color ?: '#0f766e');
            $this->status = $this->tag->status ? '1' : '0';

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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_tags', 'slug')->ignore($this->tag?->id)],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:32'],
            'status' => ['required', 'in:0,1'],
            'autoTranslateMissing' => ['nullable', 'boolean'],
        ]);

        $defaultLocale = $this->defaultLocale();
        $nameTranslations = BlogTag::cleanTranslations($validated['nameTranslations'] ?? [], 255);

        if (($nameTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "nameTranslations.{$defaultLocale}" => __('The default language name is required.'),
            ]);
        }

        if ($this->autoTranslateMissing) {
            $generated = $this->contentGenerator->completeFields([
                'name' => $nameTranslations,
            ], [
                'name' => 255,
            ]);

            $nameTranslations = $generated['name'];
            $this->nameTranslations = $nameTranslations;
        }

        $defaultName = $nameTranslations[$defaultLocale];

        $payload = [
            'id_secure' => $this->tag?->id_secure ?: Str::random(32),
            'name' => $defaultName,
            'name_translations' => $nameTranslations,
            'slug' => Str::slug(trim($validated['slug'] ?: $defaultName)),
            'icon' => trim((string) $validated['icon']) ?: null,
            'color' => trim((string) $validated['color']),
            'status' => (bool) ((int) $validated['status']),
            'changed' => time(),
            'created' => $this->tag?->created ?: time(),
        ];

        if ($this->tag) {
            $this->tag->update($payload);

            log_activity('admin.blog-tags.update', 'Updated a blog tag.', [
                'subject_type' => BlogTag::class,
                'subject_id' => $this->tag->id,
                'metadata' => [
                    'name' => $this->tag->name,
                    'slug' => $this->tag->slug,
                ],
            ]);

            $this->statusMessage = __('Blog tag updated successfully.');

            return null;
        }

        $this->tag = BlogTag::query()->create($payload);
        $this->isEditing = true;

        log_activity('admin.blog-tags.create', 'Created a blog tag.', [
            'subject_type' => BlogTag::class,
            'subject_id' => $this->tag->id,
            'metadata' => [
                'name' => $this->tag->name,
                'slug' => $this->tag->slug,
            ],
        ]);

        return $this->redirect(route('admin-blog-tags.edit', $this->tag), navigate: true);
    }

    public function delete(): mixed
    {
        abort_unless($this->tag, 404);

        $tagId = $this->tag->id;
        $metadata = ['name' => $this->tag->name, 'slug' => $this->tag->slug];

        $this->tag->delete();

        log_activity('admin.blog-tags.delete', 'Deleted a blog tag.', [
            'subject_type' => BlogTag::class,
            'subject_id' => $tagId,
            'metadata' => $metadata,
        ]);

        return $this->redirect(route('admin-blog-tags.index'), navigate: true);
    }

    public function render(): View
    {
        return view('adminblogtags::livewire.form', [
            'languages' => $this->languages(),
            'defaultLocale' => $this->defaultLocale(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->isEditing ? __('Edit Blog Tag') : __('Create Blog Tag'),
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
