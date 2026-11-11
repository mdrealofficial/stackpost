<?php

namespace Modules\AppAIRepurpose\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppCaptions\Models\CaptionLibraryItem;
use Modules\AdminUser\Models\Team;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Repurpose')]
class RepurposeIndex extends Component
{
    use AIStudioAccess;

    public string $sourceContent = '';
    public string $tone = 'professional';
    public string $language = 'vi';
    public array $selectedPlatforms = ['facebook', 'instagram', 'linkedin'];
    public array $result = [];
    public array $savedResultIndexes = [];

    public function mount(): void
    {
        $this->tone = (string) $this->aiStudioSetting('default_tone', $this->tone);
        $this->language = (string) $this->aiStudioSetting('default_language', $this->language);
        $this->selectedPlatforms = collect((array) $this->aiStudioSetting('default_caption_platforms', $this->selectedPlatforms))
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->values()
            ->all();

        $availablePlatforms = $this->availablePlatformOptions()->pluck('value')->all();

        $this->selectedPlatforms = collect($this->selectedPlatforms)
            ->intersect($availablePlatforms)
            ->values()
            ->all();

        if (empty($this->selectedPlatforms)) {
            $this->selectedPlatforms = collect($availablePlatforms)
                ->take(3)
                ->values()
                ->all();
        }
    }

    public function repurpose(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_repurpose'), 404);

        $validated = $this->validate([
            'sourceContent' => ['required', 'string', 'max:12000'],
            'selectedPlatforms' => ['required', 'array', 'min:1'],
            'selectedPlatforms.*' => ['string'],
        ]);

        try {
            $planOwner = $this->aiStudioPlanOwner();
            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_repurpose_content');
            }

            $this->result = $studio->repurposeContent($validated['sourceContent'], $this->selectedPlatforms, [
                'tone' => $this->tone,
                'language' => $this->language,
                ...$this->aiStudioWorkspacePromptConfig(),
            ]);
            $this->savedResultIndexes = [];

            $studio->recordPromptHistory(
                auth()->user(),
                'repurpose',
                $validated['sourceContent'],
                [
                    'platforms' => $this->selectedPlatforms,
                    'tone' => $this->tone,
                    'language' => $this->language,
                ],
                $this->result,
                [
                    'title' => __('Repurpose - :tone - :language', ['tone' => ucfirst($this->tone), 'language' => strtoupper($this->language)]),
                    'language' => $this->language,
                    'tone' => $this->tone,
                ],
            );

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_repurpose_content', [
                    'feature' => 'ai-studio.repurpose',
                    'metadata' => [
                        'platforms' => $this->selectedPlatforms,
                        'language' => $this->language,
                        'tone' => $this->tone,
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('sourceContent', $exception->getMessage());
        }
    }

    public function saveResultCaption(AiContentStudioService $studio, int $index): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_repurpose'), 404);

        $items = collect($this->result['items'] ?? [])->values();
        $item = $items->get($index);

        if (! is_array($item) || empty($item['caption'])) {
            $this->addError('sourceContent', __('The selected repurpose result is no longer available.'));

            return;
        }

        if (in_array($index, $this->savedResultIndexes, true)) {
            return;
        }

        $user = auth()->user();
        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $target = (string) ($item['target'] ?? 'caption');
        $title = trim((string) ($item['title'] ?? ''));
        $caption = CaptionLibraryItem::query()->create([
            'owner_user_id' => $workspaceOwnerUserId,
            'team_id' => $this->currentTeamId($user),
            'name' => $this->captionLabel($title !== '' ? $title : __('AI Repurpose :target', ['target' => Str::title($target)])),
            'slug' => $this->uniqueSlug($title !== '' ? $title : __('AI Repurpose :target', ['target' => Str::title($target)]), $workspaceOwnerUserId),
            'source_type' => 'ai',
            'status' => 'active',
            'content' => trim((string) $item['caption']),
            'notes' => trim((string) ($item['notes'] ?? '')) ?: null,
            'tags' => collect([
                Str::slug((string) ($item['target'] ?? '')),
                Str::slug((string) ($item['format'] ?? '')),
                Str::slug($this->tone),
            ])->filter()->unique()->values()->all(),
            'metadata' => [
                'ai' => [
                    'module' => 'repurpose',
                    'target' => $target,
                    'format' => (string) ($item['format'] ?? ''),
                    'tone' => $this->tone,
                    'language' => $this->language,
                    'saved_from_result_index' => $index,
                    'saved_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $studio->annotateCaption($caption);
        $this->savedResultIndexes[] = $index;

        session()->flash('status', __('Repurposed caption saved to the caption library.'));
    }

    public function saveAllResults(AiContentStudioService $studio): void
    {
        $items = collect($this->result['items'] ?? [])->values();

        foreach ($items as $index => $item) {
            if (! in_array($index, $this->savedResultIndexes, true)) {
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

        $this->resetValidation();
        $this->sourceContent = (string) $history->prompt;
        $this->tone = (string) data_get($history->input_payload, 'tone', $this->tone);
        $this->language = (string) data_get($history->input_payload, 'language', $this->language);
        $this->selectedPlatforms = collect((array) data_get($history->input_payload, 'platforms', $this->selectedPlatforms))
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
        $this->result = is_array($history->output_payload) ? $history->output_payload : [];
        $this->savedResultIndexes = [];
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_repurpose'), 404);

        $platformOptions = $this->availablePlatformOptions();
        $availablePlatformKeys = $platformOptions->pluck('value')->all();

        $this->selectedPlatforms = collect($this->selectedPlatforms)
            ->intersect($availablePlatformKeys)
            ->values()
            ->all();

        return view('appairepurpose::index', [
            'platformOptions' => $platformOptions->all(),
            'languageLabel' => collect(world_languages())
                ->firstWhere('code', $this->language)['name']
                ?? strtoupper((string) $this->language),
            'toneOptions' => [
                ['value' => 'professional', 'label' => __('Professional')],
                ['value' => 'friendly', 'label' => __('Friendly')],
                ['value' => 'sales', 'label' => __('Sales')],
                ['value' => 'educational', 'label' => __('Educational')],
                ['value' => 'bold', 'label' => __('Bold')],
                ['value' => 'casual', 'label' => __('Casual')],
            ],
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_repurpose_content'),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Repurpose'),
        ]);
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'repurpose');
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

    protected function captionLabel(string $base): string
    {
        return $base.' - '.now()->format('Y-m-d H:i');
    }
}
