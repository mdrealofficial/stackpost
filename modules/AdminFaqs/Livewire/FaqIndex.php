<?php

namespace Modules\AdminFaqs\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminBlogs\Support\MultilingualContentGenerator;
use Modules\AdminFaqs\Models\Faq;

#[Title('Frequently Asked Questions')]
class FaqIndex extends Component
{
    use WithPagination;

    protected MultilingualContentGenerator $contentGenerator;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'range', except: 'all')]
    public string $dateFilter = 'all';

    #[Url(as: 'sort', except: 'recent')]
    public string $sortFilter = 'recent';

    #[Url(as: 'show', except: '15')]
    public string $perPage = '15';

    public ?Faq $faq = null;

    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public string $status = '1';

    /** @var array<string, string> */
    public array $titleTranslations = [];

    /** @var array<string, string> */
    public array $contentTranslations = [];

    public bool $autoTranslateMissing = true;

    public ?string $statusMessage = null;

    public bool $isEditing = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSortFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function boot(MultilingualContentGenerator $contentGenerator): void
    {
        $this->contentGenerator = $contentGenerator;
    }

    public function delete(int $faqId): void
    {
        $faq = Faq::query()->findOrFail($faqId);

        $metadata = [
            'title' => $faq->title,
            'slug' => $faq->slug,
        ];

        $faq->delete();

        log_activity('admin.faqs.delete', 'Deleted an FAQ.', [
            'subject_type' => Faq::class,
            'subject_id' => $faqId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('FAQ deleted successfully.');

        $this->resetPageIfNeeded();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->dispatch('faq-form-hydrated', form: $this->formPayload());
    }

    public function startEdit(int $faqId): void
    {
        $this->resetValidation();

        $this->faq = Faq::query()->findOrFail($faqId);
        $this->isEditing = true;
        $this->title = (string) $this->faq->title;
        $this->slug = (string) ($this->faq->slug ?? '');
        $this->content = (string) $this->faq->content;
        $this->status = $this->faq->status ? '1' : '0';
        $this->fillTranslationState();
        $this->dispatch('faq-form-hydrated', form: $this->formPayload());
    }

    public function saveForm(array $form): void
    {
        $this->titleTranslations = $this->normalizeTranslationInput($form['titleTranslations'] ?? []);
        $this->contentTranslations = $this->normalizeTranslationInput($form['contentTranslations'] ?? []);
        $this->slug = Str::slug(trim((string) ($form['slug'] ?? '')));
        $this->status = (string) ($form['status'] ?? '1');
        $this->autoTranslateMissing = (bool) ($form['autoTranslateMissing'] ?? true);

        $defaultLocale = $this->defaultLocale();
        $this->title = (string) ($this->titleTranslations[$defaultLocale] ?? '');
        $this->content = (string) ($this->contentTranslations[$defaultLocale] ?? '');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'titleTranslations' => ['required', 'array'],
            'titleTranslations.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('faqs', 'slug')->ignore($this->faq?->id)],
            'content' => ['required', 'string'],
            'contentTranslations' => ['required', 'array'],
            'contentTranslations.*' => ['nullable', 'string'],
            'status' => ['required', 'in:0,1'],
            'autoTranslateMissing' => ['nullable', 'boolean'],
        ]);

        $titleTranslations = Faq::cleanTranslations($validated['titleTranslations'] ?? [], 255);
        $contentTranslations = Faq::cleanTranslations($validated['contentTranslations'] ?? []);

        if (($titleTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "titleTranslations.{$defaultLocale}" => __('The default language title is required.'),
            ]);
        }

        if (($contentTranslations[$defaultLocale] ?? '') === '') {
            throw ValidationException::withMessages([
                "contentTranslations.{$defaultLocale}" => __('The default language content is required.'),
            ]);
        }

        if ($this->autoTranslateMissing) {
            $generated = $this->contentGenerator->completeFields([
                'title' => $titleTranslations,
                'content' => $contentTranslations,
            ], [
                'title' => 255,
            ]);

            $titleTranslations = $generated['title'];
            $contentTranslations = $generated['content'];
            $this->titleTranslations = $titleTranslations;
            $this->contentTranslations = $contentTranslations;
        }

        $defaultTitle = $titleTranslations[$defaultLocale];
        $defaultContent = $contentTranslations[$defaultLocale];
        $resolvedSlug = Str::slug(trim($validated['slug'] ?: $validated['title']));

        if ($resolvedSlug !== '' && $this->slugExists($resolvedSlug)) {
            $this->addError('slug', __('Slug ":slug" already exists.', ['slug' => $resolvedSlug]));

            return;
        }

        $payload = [
            'title' => $defaultTitle,
            'title_translations' => $titleTranslations,
            'slug' => $resolvedSlug,
            'content' => $defaultContent,
            'content_translations' => $contentTranslations,
            'status' => (int) $validated['status'],
            'id_secure' => $this->faq?->id_secure ?: Str::random(32),
            'changed' => time(),
            'created' => $this->faq?->created ?: time(),
        ];

        if ($this->faq) {
            $this->faq->update($payload);

            log_activity('admin.faqs.update', 'Updated an FAQ.', [
                'subject_type' => Faq::class,
                'subject_id' => $this->faq->id,
                'metadata' => [
                    'title' => $this->faq->title,
                    'slug' => $this->faq->slug,
                ],
            ]);

            $this->statusMessage = __('FAQ updated successfully.');
        } else {
            $this->faq = Faq::query()->create($payload);
            $this->isEditing = true;

            log_activity('admin.faqs.create', 'Created an FAQ.', [
                'subject_type' => Faq::class,
                'subject_id' => $this->faq->id,
                'metadata' => [
                    'title' => $this->faq->title,
                    'slug' => $this->faq->slug,
                ],
            ]);

            $this->statusMessage = __('FAQ created successfully.');
        }

        $this->dispatch('faq-form-saved');
        $this->resetForm();
    }

    public function render(): View
    {
        $faqs = $this->filteredQuery()->paginate((int) $this->perPage);
        $allFaqs = Faq::query()->get(['status']);

        return view('adminfaqs::livewire.index', [
            'faqs' => $faqs,
            'languages' => $this->languages(),
            'defaultLocale' => $this->defaultLocale(),
            'filterOptions' => [
                'dateRanges' => $this->dateRangeOptions(),
                'sorts' => $this->sortOptions(),
                'perPage' => $this->perPageOptions(),
            ],
            'summary' => [
                'total' => $allFaqs->count(),
                'enabled' => $allFaqs->where('status', true)->count(),
                'disabled' => $allFaqs->where('status', false)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('FAQs'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && $this->filteredQuery()
                ->paginate((int) $this->perPage, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->dateFilter = 'all';
        $this->sortFilter = 'recent';
        $this->perPage = '15';
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->resetValidation();
        $this->faq = null;
        $this->isEditing = false;
        $this->title = '';
        $this->slug = '';
        $this->content = '';
        $this->status = '1';
        $this->titleTranslations = [];
        $this->contentTranslations = [];
        $this->autoTranslateMissing = true;
        $this->fillTranslationState();
    }

    protected function slugExists(string $slug): bool
    {
        return Faq::query()
            ->where('slug', $slug)
            ->when($this->faq?->id, fn ($query, $faqId) => $query->where('id', '!=', $faqId))
            ->exists();
    }

    protected function filteredQuery()
    {
        $query = Faq::query()
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter));

        $now = time();

        match ($this->dateFilter) {
            'today' => $query->where('created', '>=', strtotime('today')),
            '7d' => $query->where('created', '>=', $now - (7 * 86400)),
            '30d' => $query->where('created', '>=', $now - (30 * 86400)),
            '90d' => $query->where('created', '>=', $now - (90 * 86400)),
            default => null,
        };

        match ($this->sortFilter) {
            'oldest' => $query->orderBy('created')->orderBy('id'),
            'title_asc' => $query->orderBy('title')->orderByDesc('id'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
            default => $query->orderByDesc('changed')->orderByDesc('id'),
        };

        return $query;
    }

    protected function dateRangeOptions(): array
    {
        return [
            ['value' => 'all', 'label' => __('Any time')],
            ['value' => 'today', 'label' => __('Today')],
            ['value' => '7d', 'label' => __('Last 7 days')],
            ['value' => '30d', 'label' => __('Last 30 days')],
            ['value' => '90d', 'label' => __('Last 90 days')],
        ];
    }

    protected function sortOptions(): array
    {
        return [
            ['value' => 'recent', 'label' => __('Newest first')],
            ['value' => 'oldest', 'label' => __('Oldest first')],
            ['value' => 'title_asc', 'label' => __('Title A-Z')],
            ['value' => 'title_desc', 'label' => __('Title Z-A')],
        ];
    }

    protected function perPageOptions(): array
    {
        return [
            ['value' => '15', 'label' => __('15 rows')],
            ['value' => '30', 'label' => __('30 rows')],
            ['value' => '50', 'label' => __('50 rows')],
        ];
    }

    protected function formPayload(): array
    {
        return [
            'slug' => $this->slug,
            'status' => $this->status,
            'titleTranslations' => $this->titleTranslations,
            'contentTranslations' => $this->contentTranslations,
            'autoTranslateMissing' => $this->autoTranslateMissing,
        ];
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

    protected function fillTranslationState(): void
    {
        $defaultLocale = $this->defaultLocale();
        $titleTranslations = is_array($this->faq?->title_translations) ? $this->faq->title_translations : [];
        $contentTranslations = is_array($this->faq?->content_translations) ? $this->faq->content_translations : [];

        foreach ($this->languages() as $language) {
            $code = strtolower((string) data_get($language, 'code', $defaultLocale));
            $this->titleTranslations[$code] = (string) data_get($titleTranslations, $code, $code === $defaultLocale ? $this->title : '');
            $this->contentTranslations[$code] = (string) data_get($contentTranslations, $code, $code === $defaultLocale ? $this->content : '');
        }
    }

    protected function normalizeTranslationInput(array $translations): array
    {
        $normalized = [];

        foreach ($this->languages() as $language) {
            $code = strtolower((string) data_get($language, 'code', $this->defaultLocale()));
            $normalized[$code] = trim((string) ($translations[$code] ?? ''));
        }

        return $normalized;
    }
}
