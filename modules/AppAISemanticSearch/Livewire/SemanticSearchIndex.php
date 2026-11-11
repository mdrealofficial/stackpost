<?php

namespace Modules\AppAISemanticSearch\Livewire;

use Illuminate\Contracts\View\View;
use Throwable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;

#[Title('AI Semantic Search')]
class SemanticSearchIndex extends Component
{
    use AIStudioAccess;

    public string $searchQuery = '';
    public array $result = [];
    public bool $hasSearched = false;

    public function search(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_semantic_search'), 404);

        $validated = $this->validate([
            'searchQuery' => ['required', 'string', 'max:255'],
        ]);

        try {
            $planOwner = $this->aiStudioPlanOwner();
            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_semantic_search');
            }

            $this->result = $studio->semanticSearch($this->workspaceOwnerUserId(), $validated['searchQuery'], 12);
            $this->hasSearched = true;

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_semantic_search', [
                    'feature' => 'ai-studio.semantic-search',
                    'metadata' => ['query' => $validated['searchQuery']],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('searchQuery', $exception->getMessage());
        }
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_semantic_search'), 404);

        return view('appaisemanticsearch::index', [
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_semantic_search'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Semantic Search'),
        ]);
    }
}
