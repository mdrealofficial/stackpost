<?php

namespace Modules\AdminBlogs\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AdminBlogs\Models\BlogRssSource;
use Modules\AdminBlogs\Support\RssImportService;

#[Title('Blog RSS')]
class RssIndex extends Component
{
    protected const AI_PROMPT_PRESETS = [
        'Rewrite the imported article into a cleaner editorial post with a stronger introduction, tighter transitions, and a neutral professional tone.',
        'Preserve the facts, but improve structure for SEO with clearer headings, shorter paragraphs, and more skimmable summaries.',
        'Turn each imported item into a concise newsroom digest, remove repetitive phrasing, and avoid copying the original wording too closely.',
    ];

    public string $name = '';
    public string $feed_url = '';
    public ?string $blog_category_id = '';
    public array $tag_ids = [];
    public ?int $editingSourceId = null;
    public int $sync_interval_minutes = 60;
    public int $max_items_per_run = 5;
    public string $ai_prompt = '';
    public bool $status = true;
    public bool $auto_publish = true;
    public bool $ai_improve = false;
    public bool $ai_auto_translate = false;

    public array $selectedSourceIds = [];

    public bool $selectAllSources = false;

    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    protected RssImportService $rssImportService;

    public function boot(RssImportService $rssImportService): void
    {
        $this->rssImportService = $rssImportService;
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->statusMessage = null;
        $this->errorMessage = null;
        $this->dispatch('rss-source-form-open');
    }

    public function save(): void
    {
        $this->blog_category_id = $this->normalizeCategoryId($this->blog_category_id);
        $this->tag_ids = collect($this->tag_ids)->map(fn ($id) => (string) $id)->filter()->values()->all();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'feed_url' => ['required', 'url', 'max:2000'],
            'blog_category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('blog_tags', 'id')],
            'sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'max_items_per_run' => ['required', 'integer', 'min:1', 'max:50'],
            'ai_prompt' => ['nullable', 'string'],
        ]);

        $this->validateFeedUrl($validated['feed_url']);

        $payload = [
            'name' => trim((string) $validated['name']),
            'feed_url' => trim((string) $validated['feed_url']),
            'blog_category_id' => filled($validated['blog_category_id'] ?? null) ? (int) $validated['blog_category_id'] : null,
            'tag_ids' => array_values(array_map('intval', $validated['tag_ids'] ?? [])),
            'status' => $this->status,
            'auto_publish' => $this->auto_publish,
            'ai_improve' => $this->ai_improve,
            'ai_auto_translate' => $this->ai_auto_translate,
            'ai_prompt' => trim((string) $validated['ai_prompt']) ?: null,
            'sync_interval_minutes' => (int) $validated['sync_interval_minutes'],
            'max_items_per_run' => (int) $validated['max_items_per_run'],
            'changed' => time(),
        ];

        $isUpdating = $this->editingSourceId !== null;

        if ($isUpdating) {
            BlogRssSource::query()->findOrFail($this->editingSourceId)->update($payload);
        } else {
            BlogRssSource::query()->create($payload + [
                'id_secure' => Str::random(32),
                'created' => time(),
            ]);
        }

        $this->resetForm();
        $this->statusMessage = $isUpdating
            ? __('RSS source updated successfully.')
            : __('RSS source created successfully.');
        $this->errorMessage = null;
        $this->dispatch('rss-source-form-saved');
    }

    public function updated($property): void
    {
        if ($property === 'feed_url') {
            $this->resetErrorBag('feed_url');
        }
    }

    public function applyPromptPreset(int $index): void
    {
        $preset = self::AI_PROMPT_PRESETS[$index] ?? null;

        if (! is_string($preset)) {
            return;
        }

        $this->ai_prompt = __($preset);
    }

    public function runSource(int $sourceId): void
    {
        $source = BlogRssSource::query()->findOrFail($sourceId);
        $result = $this->rssImportService->importSource($source, true);
        $this->statusMessage = __('RSS sync finished. Created :created, skipped :skipped.', [
            'created' => $result['created'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
        ]);
        $this->errorMessage = null;
    }

    public function editSource(int $sourceId): void
    {
        $source = BlogRssSource::query()->findOrFail($sourceId);

        $this->editingSourceId = $source->id;
        $this->name = (string) $source->name;
        $this->feed_url = (string) $source->feed_url;
        $this->blog_category_id = $source->blog_category_id ? (string) $source->blog_category_id : null;
        $this->tag_ids = collect($source->tag_ids ?? [])->map(fn ($id) => (string) $id)->all();
        $this->sync_interval_minutes = (int) $source->sync_interval_minutes;
        $this->max_items_per_run = (int) $source->max_items_per_run;
        $this->ai_prompt = (string) ($source->ai_prompt ?? '');
        $this->status = (bool) $source->status;
        $this->auto_publish = (bool) $source->auto_publish;
        $this->ai_improve = (bool) $source->ai_improve;
        $this->ai_auto_translate = (bool) $source->ai_auto_translate;
        $this->resetValidation();
        $this->errorMessage = null;
        $this->dispatch('rss-source-form-open');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->statusMessage = null;
        $this->errorMessage = null;
        $this->dispatch('rss-source-form-close');
    }

    public function activateSource(int $sourceId): void
    {
        $source = BlogRssSource::query()->findOrFail($sourceId);
        $source->update([
            'status' => true,
            'changed' => time(),
        ]);

        $this->statusMessage = __('RSS source activated successfully.');
        $this->errorMessage = null;
    }

    public function pauseSource(int $sourceId): void
    {
        $source = BlogRssSource::query()->findOrFail($sourceId);
        $source->update([
            'status' => false,
            'changed' => time(),
        ]);

        $this->statusMessage = __('RSS source paused successfully.');
        $this->errorMessage = null;
    }

    public function runAll(): void
    {
        $created = 0;
        $skipped = 0;

        BlogRssSource::query()->where('status', true)->get()->each(function (BlogRssSource $source) use (&$created, &$skipped): void {
            $result = $this->rssImportService->importSource($source, true);
            $created += (int) ($result['created'] ?? 0);
            $skipped += (int) ($result['skipped'] ?? 0);
        });

        $this->statusMessage = __('RSS sync finished. Created :created, skipped :skipped.', compact('created', 'skipped'));
        $this->errorMessage = null;
    }

    public function delete(int $sourceId): void
    {
        BlogRssSource::query()->findOrFail($sourceId)->delete();
        $this->selectedSourceIds = array_values(array_diff($this->selectedSourceIds, [$sourceId]));
        $this->selectAllSources = count($this->selectedSourceIds) > 0
            && count($this->selectedSourceIds) === BlogRssSource::query()->count();
        $this->statusMessage = __('RSS source deleted successfully.');
        $this->errorMessage = null;
    }

    public function updatedSelectAllSources(bool $value): void
    {
        $this->selectedSourceIds = $value
            ? BlogRssSource::query()->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    public function updatedSelectedSourceIds(): void
    {
        $total = BlogRssSource::query()->count();
        $this->selectAllSources = $total > 0 && count($this->selectedSourceIds) === $total;
    }

    public function deleteSelected(): void
    {
        $sourceIds = collect($this->selectedSourceIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($sourceIds->isEmpty()) {
            return;
        }

        $sources = BlogRssSource::query()->whereIn('id', $sourceIds)->get(['id', 'name', 'feed_url']);

        if ($sources->isEmpty()) {
            $this->resetSelection();

            return;
        }

        BlogRssSource::query()->whereIn('id', $sources->pluck('id'))->delete();

        $this->statusMessage = trans_choice(
            '{1} :count RSS source deleted successfully.|[2,*] :count RSS sources deleted successfully.',
            $sources->count(),
            ['count' => $sources->count()]
        );
        $this->errorMessage = null;
        $this->resetSelection();
    }

    public function activateSelected(): void
    {
        $this->updateSelectedStatus(true);
    }

    public function pauseSelected(): void
    {
        $this->updateSelectedStatus(false);
    }

    public function runSelected(): void
    {
        $sourceIds = collect($this->selectedSourceIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($sourceIds->isEmpty()) {
            return;
        }

        $created = 0;
        $skipped = 0;

        BlogRssSource::query()
            ->whereIn('id', $sourceIds)
            ->get()
            ->each(function (BlogRssSource $source) use (&$created, &$skipped): void {
                $result = $this->rssImportService->importSource($source, true);
                $created += (int) ($result['created'] ?? 0);
                $skipped += (int) ($result['skipped'] ?? 0);
            });

        $this->statusMessage = __('Selected RSS sync finished. Created :created, skipped :skipped.', compact('created', 'skipped'));
        $this->errorMessage = null;
        $this->resetSelection();
    }

    public function render(): View
    {
        $sources = BlogRssSource::query()
            ->with('category:id,name,name_translations')
            ->withCount('imports')
            ->orderByDesc('id')
            ->get();

        return view('adminblogs::livewire.rss', [
            'sources' => $sources,
            'categories' => BlogCategory::query()->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'selectedTagOptions' => BlogTag::query()
                ->whereIn('id', collect($this->tag_ids)->map(fn ($id) => (int) $id)->filter()->values())
                ->orderBy('name')
                ->get()
                ->map(fn (BlogTag $tag) => [
                    'key' => (string) $tag->id,
                    'label' => trim((string) ($tag->nameForLocale() ?: $tag->name)),
                ])
                ->values()
                ->all(),
            'promptPresets' => collect(self::AI_PROMPT_PRESETS)->map(fn (string $preset) => __($preset))->all(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Blog RSS'),
        ]);
    }

    protected function validateFeedUrl(string $feedUrl): void
    {
        try {
            $response = Http::timeout(20)
                ->accept('application/rss+xml, application/xml, text/xml, application/atom+xml')
                ->get($feedUrl);
        } catch (ConnectionException $exception) {
            $message = str_contains(strtolower($exception->getMessage()), 'ssl')
                ? __('Could not validate this feed because the remote SSL certificate is invalid or self-signed.')
                : __('Could not connect to this feed URL.');

            throw ValidationException::withMessages([
                'feed_url' => $message,
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'feed_url' => __('Could not fetch this feed URL.'),
            ]);
        }

        $body = trim((string) $response->body());

        if ($body === '') {
            throw ValidationException::withMessages([
                'feed_url' => __('This feed URL returned an empty response.'),
            ]);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $isValidFeed = $xml instanceof \SimpleXMLElement
            && (isset($xml->channel->item) || isset($xml->entry));

        if (! $isValidFeed) {
            throw ValidationException::withMessages([
                'feed_url' => __('This URL is reachable but does not appear to be a valid RSS or Atom feed.'),
            ]);
        }
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingSourceId',
            'name',
            'feed_url',
            'blog_category_id',
            'tag_ids',
            'ai_prompt',
        ]);

        $this->sync_interval_minutes = 60;
        $this->max_items_per_run = 5;
        $this->status = true;
        $this->auto_publish = true;
        $this->ai_improve = false;
        $this->ai_auto_translate = false;
        $this->resetValidation();
    }

    protected function normalizeCategoryId(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '0') {
            return null;
        }

        return $value;
    }

    protected function resetSelection(): void
    {
        $this->selectedSourceIds = [];
        $this->selectAllSources = false;
    }

    protected function updateSelectedStatus(bool $status): void
    {
        $sourceIds = collect($this->selectedSourceIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($sourceIds->isEmpty()) {
            return;
        }

        BlogRssSource::query()->whereIn('id', $sourceIds)->update([
            'status' => $status,
            'changed' => time(),
        ]);

        $this->statusMessage = $status
            ? trans_choice('{1} :count RSS source activated.|[2,*] :count RSS sources activated.', $sourceIds->count(), ['count' => $sourceIds->count()])
            : trans_choice('{1} :count RSS source paused.|[2,*] :count RSS sources paused.', $sourceIds->count(), ['count' => $sourceIds->count()]);

        $this->errorMessage = null;
        $this->resetSelection();
    }
}
