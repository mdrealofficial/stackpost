<?php

namespace Modules\AppAIReview\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Throwable;

#[Title('AI Review')]
class AIReviewIndex extends Component
{
    use AIStudioAccess;

    public string $reviewDraft = '';
    public string $language = '';
    public array $selectedPlatforms = ['facebook', 'instagram'];
    public array $result = [];

    public function mount(): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_review'), 404);

        $this->language = (string) $this->aiStudioSetting(
            'review_language',
            $this->aiStudioSetting('default_language', (string) (app()->getLocale() ?: 'en'))
        );
    }

    public function review(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_review'), 404);

        $validated = $this->validate([
            'reviewDraft' => ['required', 'string', 'max:8000'],
            'language' => ['required', 'string', 'max:12'],
        ]);

        try {
            $planOwner = $this->aiStudioPlanOwner();

            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_review_content');
            }

            $this->result = $studio->reviewDraft($validated['reviewDraft'], $this->selectedPlatforms, [
                'tone' => 'professional',
                'language' => $validated['language'],
                ...$this->aiStudioWorkspacePromptConfig(),
            ]);

            $studio->recordPromptHistory(
                auth()->user(),
                'review',
                $validated['reviewDraft'],
                [
                    'platforms' => $this->selectedPlatforms,
                    'language' => $validated['language'],
                ],
                $this->result,
                [
                    'title' => __('Review - :language', ['language' => $this->languageLabel($validated['language'])]),
                    'language' => $validated['language'],
                    'tone' => 'professional',
                ],
            );

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_review_content', [
                    'feature' => 'ai-studio.review',
                    'metadata' => [
                        'platforms' => $this->selectedPlatforms,
                        'language' => $validated['language'],
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('reviewDraft', $exception->getMessage());
        }
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->resetValidation();
        $this->reviewDraft = (string) $history->prompt;
        $this->language = (string) data_get($history->input_payload, 'language', $this->language);
        $this->result = is_array($history->output_payload) ? $history->output_payload : [];
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_review'), 404);

        return view('appaireview::index', [
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_review_content'),
            'languageLabel' => $this->languageLabel($this->language),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Review'),
        ]);
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'review');
    }

    protected function languageLabel(string $code): string
    {
        return collect(world_languages())->firstWhere('code', $code)['name'] ?? strtoupper($code);
    }
}
