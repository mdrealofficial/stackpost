<?php

namespace Modules\AppAIContent\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminAI\Support\AiOptionCatalog;
use Modules\AdminAITemplate\Models\AiTemplate;
use Modules\AdminAITemplateCategories\Models\AiTemplateCategory;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\Team;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppCaptions\Models\CaptionLibraryItem;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Throwable;

#[Title('AI Content')]
class AIContentIndex extends Component
{
    use AIStudioAccess;

    public string $promptTemplate = '';
    public string $categorySearch = '';
    public string $templateSearch = '';
    public string $tone = 'professional';
    public string $language = 'vi';
    public string $creativity = 'economic';
    public string $hashtagMode = 'auto';
    public int $approximateWords = 100;
    public int $totalResults = 3;
    public string $selectedCategoryId = '';
    public ?int $selectedTemplateId = null;
    public array $selectedPlatforms = ['facebook', 'instagram', 'linkedin'];
    public array $result = [];
    public array $tags = [];
    public array $savedResultIndexes = [];

    public function mount(OptionStore $options): void
    {
        $this->tone = (string) $this->aiStudioSetting('default_tone', $this->tone);
        $this->language = (string) $this->aiStudioSetting('default_language', $this->language);
        $this->selectedPlatforms = collect((array) $this->aiStudioSetting('default_caption_platforms', $this->selectedPlatforms))
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->values()
            ->all();
        $this->creativity = (string) $options->get('ai_default_creativity', $this->creativity);

        $availablePlatforms = $this->availablePlatformOptions()->pluck('value')->all();
        $this->selectedPlatforms = collect($this->selectedPlatforms)
            ->intersect($availablePlatforms)
            ->values()
            ->all();

        if ($this->selectedPlatforms === []) {
            $this->selectedPlatforms = collect($availablePlatforms)->take(3)->values()->all();
        }

        $this->selectedCategoryId = '';
        $this->selectedTemplateId = null;
    }

    public function updatedSelectedCategoryId(): void
    {
        $this->selectedTemplateId = null;
        $this->templateSearch = '';
    }

    public function selectCategory(int $categoryId): void
    {
        $this->selectedCategoryId = (string) $categoryId;
        $this->selectedTemplateId = null;
        $this->templateSearch = '';
    }

    public function selectTemplate(int $templateId): void
    {
        $template = AiTemplate::query()
            ->where('status', true)
            ->whereKey($templateId)
            ->first();

        if (! $template) {
            return;
        }

        $this->selectedCategoryId = (string) $template->cate_id;
        $this->applyTemplate($template);
        $this->promptTemplate = $template->cleanContent();
    }

    public function clearTemplate(): void
    {
        $this->selectedTemplateId = null;
        $this->promptTemplate = '';
    }

    public function backToCategories(): void
    {
        $this->selectedCategoryId = '';
        $this->selectedTemplateId = null;
        $this->templateSearch = '';
    }

    public function generate(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_caption_generator'), 404);

        $validated = $this->validate([
            'promptTemplate' => ['required', 'string', 'max:5000'],
            'selectedPlatforms' => ['required', 'array', 'min:1'],
            'selectedPlatforms.*' => ['string'],
            'approximateWords' => ['required', 'integer', 'min:40', 'max:320'],
            'totalResults' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        try {
            $placeholders = $this->unresolvedPromptPlaceholders($validated['promptTemplate']);

            if ($placeholders !== []) {
                $this->result = [];
                $this->tags = [];
                $this->addError('promptTemplate', __('Replace these placeholders before generating content: :placeholders', [
                    'placeholders' => implode(', ', $placeholders),
                ]));

                return;
            }

            $planOwner = $this->aiStudioPlanOwner();

            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_generate_captions');
            }

            $this->result = $this->normalizeResultPayload($studio->generatePlatformCaptions($validated['promptTemplate'], $this->selectedPlatforms, [
                'template' => '',
                'tone' => $this->tone,
                'language' => $this->language,
                'creativity' => $this->creativity,
                'hashtag_mode' => $this->hashtagMode,
                'approximate_words' => $this->approximateWords,
                'total_results' => $this->totalResults,
                ...$this->aiStudioWorkspacePromptConfig(),
            ]));

            $this->tags = $studio->suggestTags(trim($validated['promptTemplate']), [
                'platforms' => $this->selectedPlatforms,
            ])['tags'] ?? [];
            $this->savedResultIndexes = [];

            $template = $this->selectedTemplate();

            $studio->recordPromptHistory(
                auth()->user(),
                'caption_generator',
                $validated['promptTemplate'],
                [
                    'template_id' => $template?->id,
                    'template' => '',
                    'prompt_template' => $validated['promptTemplate'],
                    'platforms' => $this->selectedPlatforms,
                    'tone' => $this->tone,
                    'language' => $this->language,
                    'creativity' => $this->creativity,
                    'hashtag_mode' => $this->hashtagMode,
                    'approximate_words' => $this->approximateWords,
                    'total_results' => $this->totalResults,
                ],
                $this->result,
                [
                    'title' => $this->historyTitle($template),
                    'language' => $this->language,
                    'tone' => $this->tone,
                    'metadata' => [
                        'template_title' => $template?->contentPreview(80),
                        'category_id' => $template?->cate_id,
                    ],
                ],
            );

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_generate_captions', [
                    'feature' => 'ai-studio.caption-generator',
                    'metadata' => [
                        'template_id' => $template?->id,
                        'platforms' => $this->selectedPlatforms,
                        'language' => $this->language,
                        'tone' => $this->tone,
                        'creativity' => $this->creativity,
                        'total_results' => $this->totalResults,
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('promptTemplate', $exception->getMessage());
        }
    }

    public function saveResultCaption(AiContentStudioService $studio, int $index): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_caption_generator'), 404);

        $item = collect($this->result['variants'] ?? [])->values()->get($index);

        if (! is_array($item) || empty($item['caption'])) {
            $this->addError('promptTemplate', __('The selected caption result is no longer available.'));

            return;
        }

        if (in_array($index, $this->savedResultIndexes, true)) {
            return;
        }

        $user = auth()->user();
        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $platform = (string) ($item['platform'] ?? 'caption');
        $hashtags = collect((array) ($item['hashtags'] ?? []))
            ->map(fn ($tag) => '#'.ltrim((string) $tag, '#'))
            ->filter()
            ->implode(' ');

        $content = trim(implode("\n\n", array_filter([
            (string) ($item['hook'] ?? ''),
            (string) ($item['caption'] ?? ''),
            $hashtags,
        ])));

        $caption = CaptionLibraryItem::query()->create([
            'owner_user_id' => $workspaceOwnerUserId,
            'team_id' => $this->currentTeamId($user),
            'name' => $this->uniqueSlugLabel(__('AI :platform Caption', ['platform' => Str::title($platform)])),
            'slug' => $this->uniqueSlug(__('AI :platform Caption', ['platform' => Str::title($platform)]), $workspaceOwnerUserId),
            'source_type' => 'ai',
            'status' => 'active',
            'content' => $content,
            'notes' => trim((string) ($item['cta'] ?? '')) ?: null,
            'tags' => collect($this->tags)
                ->merge([(string) Str::slug($platform)])
                ->map(fn ($tag) => trim((string) $tag))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'metadata' => [
                'ai' => [
                    'module' => 'caption_generator',
                    'platform' => $platform,
                    'tone' => $this->tone,
                    'language' => $this->language,
                    'saved_from_result_index' => $index,
                    'saved_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $studio->annotateCaption($caption);
        $this->savedResultIndexes[] = $index;

        session()->flash('status', __('Caption saved to the caption library.'));
    }

    public function saveAllResults(AiContentStudioService $studio): void
    {
        foreach (collect($this->result['variants'] ?? [])->keys() as $index) {
            if (! in_array((int) $index, $this->savedResultIndexes, true)) {
                $this->saveResultCaption($studio, (int) $index);
            }
        }
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $inputPayload = (array) ($history->input_payload ?? []);
        $this->promptTemplate = (string) data_get($inputPayload, 'prompt_template', data_get($inputPayload, 'template', (string) $history->prompt));
        $this->tone = (string) data_get($inputPayload, 'tone', $this->tone);
        $this->language = (string) data_get($inputPayload, 'language', $this->language);
        $this->creativity = (string) data_get($inputPayload, 'creativity', $this->creativity);
        $this->hashtagMode = (string) data_get($inputPayload, 'hashtag_mode', $this->hashtagMode);
        $this->approximateWords = (int) data_get($inputPayload, 'approximate_words', $this->approximateWords);
        $this->totalResults = (int) data_get($inputPayload, 'total_results', $this->totalResults);
        $this->selectedPlatforms = collect((array) data_get($inputPayload, 'platforms', $this->selectedPlatforms))
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
        $this->selectedTemplateId = data_get($inputPayload, 'template_id')
            ? (int) data_get($inputPayload, 'template_id')
            : null;

        if ($this->selectedTemplateId) {
            $template = $this->selectedTemplate();
            $this->selectedCategoryId = $template?->cate_id ? (string) $template->cate_id : $this->selectedCategoryId;
        }

        $this->result = $this->normalizeResultPayload(is_array($history->output_payload) ? $history->output_payload : []);
        $this->tags = collect((array) ($this->result['variants'] ?? []))
            ->flatMap(fn ($item) => (array) ($item['hashtags'] ?? []))
            ->map(fn ($tag) => ltrim((string) $tag, '#'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->savedResultIndexes = [];
    }

    public function render(AiOptionCatalog $catalog): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_caption_generator'), 404);

        $platformOptions = $this->availablePlatformOptions();
        $availablePlatformKeys = $platformOptions->pluck('value')->all();
        $categoryOptions = $this->categoryOptions();
        $templates = $this->templateQuery()->get();
        $selectedTemplate = $this->selectedTemplate();

        $this->selectedPlatforms = collect($this->selectedPlatforms)
            ->intersect($availablePlatformKeys)
            ->values()
            ->all();

        return view('appaicontent::index', [
            'platformOptions' => $platformOptions->map(fn (array $option) => [
                'key' => $option['value'],
                'label' => $option['label'],
            ])->values()->all(),
            'categoryOptions' => $categoryOptions,
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'toneOptions' => [
                ['value' => 'professional', 'label' => __('Professional')],
                ['value' => 'friendly', 'label' => __('Friendly')],
                ['value' => 'sales', 'label' => __('Sales')],
                ['value' => 'educational', 'label' => __('Educational')],
                ['value' => 'bold', 'label' => __('Bold')],
                ['value' => 'casual', 'label' => __('Casual')],
            ],
            'creativityOptions' => collect($catalog->creativityOptions())
                ->map(fn (array $option) => ['value' => $option['value'], 'label' => __($option['label'])])
                ->values()
                ->all(),
            'hashtagOptions' => [
                ['value' => 'off', 'label' => __('Off')],
                ['value' => 'light', 'label' => __('Light')],
                ['value' => 'auto', 'label' => __('Auto')],
                ['value' => 'heavy', 'label' => __('Heavy')],
            ],
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_generate_captions'),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(10)->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Content'),
        ]);
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'caption_generator');
    }

    protected function availablePlatformOptions(): Collection
    {
        $providerRegistry = collect(channel_provider_cards())->keyBy('key');

        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->get(['provider_key'])
            ->pluck('provider_key')
            ->filter()
            ->map(fn ($key) => (string) $key)
            ->unique()
            ->values()
            ->map(function (string $providerKey) use ($providerRegistry): array {
                $provider = $providerRegistry->get($providerKey, []);

                return [
                    'value' => $providerKey,
                    'label' => (string) ($provider['label'] ?? str($providerKey)->replace('_', ' ')->replace('-', ' ')->title()),
                ];
            });
    }

    protected function categoryOptions(): Collection
    {
        return AiTemplateCategory::query()
            ->where('status', true)
            ->when($this->categorySearch !== '', function ($query) {
                $search = trim($this->categorySearch);

                $query->where('name', 'like', '%'.$search.'%');
            })
            ->withCount(['templates' => fn ($query) => $query->where('status', true)])
            ->orderByRaw("CASE WHEN LOWER(name) = 'suggested' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    protected function templateQuery()
    {
        return AiTemplate::query()
            ->with('category')
            ->where('status', true)
            ->when($this->selectedCategoryId !== '', fn ($query) => $query->where('cate_id', (int) $this->selectedCategoryId))
            ->when($this->selectedCategoryId === '', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->templateSearch !== '', function ($query) {
                $search = trim($this->templateSearch);

                $query->where(function ($builder) use ($search) {
                    $builder->where('content', 'like', '%'.$search.'%')
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('id')
            ->limit(80);
    }

    protected function selectedTemplate(): ?AiTemplate
    {
        if (! $this->selectedTemplateId) {
            return null;
        }

        return AiTemplate::query()
            ->with('category')
            ->where('status', true)
            ->whereKey($this->selectedTemplateId)
            ->first();
    }

    protected function applyTemplate(AiTemplate $template): void
    {
        $this->selectedTemplateId = (int) $template->id;
        $this->syncPlatformsFromTemplate($template);
    }

    protected function syncPlatformsFromTemplate(AiTemplate $template): void
    {
        $platform = $this->templatePlatformKey($template);

        if ($platform === null) {
            return;
        }

        $availablePlatforms = $this->availablePlatformOptions()->pluck('value')->all();

        if (in_array($platform, $availablePlatforms, true)) {
            $this->selectedPlatforms = [$platform];
        }
    }

    protected function templatePlatformKey(AiTemplate $template): ?string
    {
        return match (Str::lower(trim((string) $template->category?->name))) {
            'facebook' => 'facebook',
            'instagram' => 'instagram',
            'x (twitter)' => 'x',
            'linkedin' => 'linkedin',
            'tiktok' => 'tiktok',
            'youtube' => 'youtube',
            'pinterest' => 'pinterest',
            default => null,
        };
    }

    protected function historyTitle(?AiTemplate $template = null): string
    {
        if ($template?->category?->name) {
            return __(':category - :tone - :language', [
                'category' => $template->category->name,
                'tone' => ucfirst($this->tone),
                'language' => strtoupper($this->language),
            ]);
        }

        return __('Caption generator - :tone - :language', [
            'tone' => ucfirst($this->tone),
            'language' => strtoupper($this->language),
        ]);
    }

    protected function normalizeResultPayload(array $payload): array
    {
        $variants = collect((array) ($payload['variants'] ?? $payload['platforms'] ?? []))
            ->map(fn ($item) => [
                'platform' => strtolower(trim($this->stringValue($item['platform'] ?? ''))),
                'caption' => trim($this->stringValue($item['caption'] ?? '')),
                'hook' => trim($this->stringValue($item['hook'] ?? '')),
                'hashtags' => collect((array) ($item['hashtags'] ?? []))
                    ->map(fn ($tag) => trim($this->stringValue($tag)))
                    ->filter()
                    ->values()
                    ->all(),
                'cta' => trim($this->stringValue($item['cta'] ?? '')),
                'notes' => trim($this->stringValue($item['notes'] ?? '')),
            ])
            ->filter(fn ($item) => $item['platform'] !== '' && $item['caption'] !== '')
            ->values()
            ->all();

        return [
            'summary' => trim($this->stringValue($payload['summary'] ?? '')),
            'variants' => $variants,
            'source' => $this->stringValue($payload['source'] ?? 'ai'),
        ];
    }

    protected function unresolvedPromptPlaceholders(string $prompt): array
    {
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $prompt, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $text = data_get($value, 'text')
                ?? data_get($value, 'content')
                ?? data_get($value, 'caption')
                ?? data_get($value, 'value')
                ?? data_get($value, 'message');

            if ($text !== null && $text !== $value) {
                return $this->stringValue($text);
            }

            return trim(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    protected function currentTeamId($user): ?int
    {
        return TeamWorkspaceAccess::activeTeam($user)?->id;
    }

    protected function uniqueSlug(string $value, int $userId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'caption';
        $slug = $baseSlug;
        $counter = 2;

        while (CaptionLibraryItem::query()
            ->ownedBy($userId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    protected function uniqueSlugLabel(string $base): string
    {
        return $base.' - '.now()->format('Y-m-d H:i');
    }
}
