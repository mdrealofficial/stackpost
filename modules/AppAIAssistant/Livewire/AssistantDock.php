<?php

namespace Modules\AppAIAssistant\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Modules\AppAIAssistant\Models\AssistantAuditLog;
use Modules\AppAIAssistant\Models\AssistantIdea;
use Modules\AppAIAssistant\Support\AssistantAuditService;
use Modules\AppAIAssistant\Support\AssistantCreditActionRunner;
use Modules\AppAIAssistant\Support\AssistantPublishingWorkflow;
use Modules\AppAIAssistant\Support\AssistantWorkspaceContext;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AIStudioPlanFeature;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Throwable;

class AssistantDock extends Component
{
    use AIStudioAccess;

    public string $tab = 'assistant';
    public string $pageRouteName = '';
    public string $pagePath = '';
    public string $pageTitle = '';
    public string $question = '';
    public array $assistantMessages = [];
    public string $workspaceSearch = '';
    public array $searchResults = [];
    public string $ideaTitle = '';
    public string $ideaBrief = '';
    public array $ideaPlatforms = ['facebook', 'instagram', 'linkedin'];
    public ?int $activeIdeaId = null;
    public array $variantTexts = [];
    public array $selectedAccountIds = [];
    public string $contentTool = 'review';
    public array $toolResult = [];
    public string $publishMode = 'draft';
    public string $scheduleAt = '';
    public bool $confirmPublish = false;
    public string $panelMessage = '';
    public string $panelMessageTone = 'success';
    public string $turnRequestKey = '';
    public string $ideaRequestKey = '';
    public string $postRequestKey = '';
    public string $toolRequestKey = '';

    public function mount(string $pageRouteName = '', string $pagePath = '', string $pageTitle = ''): void
    {
        $this->pageRouteName = $pageRouteName;
        $this->pagePath = $pagePath;
        $this->pageTitle = $pageTitle;
        $this->scheduleAt = now($this->currentTimezone())->addHour()->startOfHour()->format('Y-m-d\TH:i');
        $this->rotateRequestKeys();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['assistant', 'ideas', 'drafts', 'ready'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->panelMessage = '';
        if ($tab === 'assistant') {
            $this->activeIdeaId = null;
        }
    }

    public function askAssistant(AiContentStudioService $studio, AssistantCreditActionRunner $runner, AssistantWorkspaceContext $workspace): void
    {
        $this->assertAssistantAvailable();
        $question = trim((string) $this->validate(['question' => ['required', 'string', 'max:3000']])['question']);
        $navigation = collect($workspace->navigation())->keyBy('key');
        $snapshot = $workspace->snapshot(auth()->user());

        try {
            $result = $runner->run(
                auth()->user(),
                $this->aiStudioPlanOwner(),
                'ai_assistant_turn',
                $this->turnRequestKey,
                fn (): array => $studio->assistantTurn($question, $snapshot, [
                    'language' => (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en'),
                    'navigation_keys' => $navigation->keys()->all(),
                    'page' => [
                        'route' => $this->pageRouteName,
                        'path' => $this->pagePath,
                        'title' => $this->pageTitle,
                        'guide' => $workspace->pageGuide($this->pageRouteName),
                    ],
                    ...$this->aiStudioWorkspacePromptConfig(),
                ]),
                [
                    'action_key' => 'workspace_question',
                    'feature' => 'ai-assistant.workspace',
                    'payload' => ['question' => $question, 'page_route' => $this->pageRouteName],
                ],
            );
            $actions = collect((array) ($result['actions'] ?? []))->map(function ($action) use ($navigation): array {
                $action = is_array($action) ? $action : [];
                $target = $navigation->get((string) ($action['navigation_key'] ?? ''));

                return [
                    'label' => trim((string) ($action['label'] ?? '')),
                    'reason' => trim((string) ($action['reason'] ?? '')),
                    'url' => is_array($target) ? ($target['url'] ?? null) : null,
                ];
            })->filter(fn (array $action): bool => $action['label'] !== '')->values()->all();
            $this->assistantMessages = collect($this->assistantMessages)->push(
                ['role' => 'user', 'text' => $question],
                [
                    'role' => 'assistant',
                    'text' => (string) ($result['answer'] ?? ''),
                    'highlights' => (array) ($result['highlights'] ?? []),
                    'actions' => $actions,
                    'safety_note' => (string) ($result['safety_note'] ?? ''),
                    'source' => (string) ($result['source'] ?? 'fallback'),
                    'credit' => (array) ($result['_credit'] ?? []),
                ],
            )->take(-12)->values()->all();
            $this->setCreditMessage((array) ($result['_credit'] ?? []), __('Answer ready.'));
            $this->question = '';
        } catch (Throwable $exception) {
            $this->addError('question', $exception->getMessage());
        } finally {
            $this->turnRequestKey = (string) Str::uuid();
        }
    }

    public function searchWorkspace(AssistantWorkspaceContext $workspace): void
    {
        $this->assertAssistantAvailable();
        $term = (string) $this->validate(['workspaceSearch' => ['required', 'string', 'max:180']])['workspaceSearch'];
        $this->searchResults = $workspace->search(auth()->user(), $term);
        $this->panelMessageTone = $this->searchResults === [] ? 'neutral' : 'success';
        $this->panelMessage = $this->searchResults === []
            ? __('No workspace matches. Try a campaign name, caption phrase, or media filename. Search is free.')
            : trans_choice(':count workspace result found.|:count workspace results found.', count($this->searchResults), ['count' => count($this->searchResults)]).' '.__('Search is free.');
    }

    public function useQuickPrompt(string $promptKey): void
    {
        $prompts = [
            'failures' => __('Explain my recent publishing failures and tell me which one to fix first.'),
            'queue' => __('Summarize today\'s publishing queue and flag anything that needs attention.'),
            'drafts' => __('Review my draft workload and suggest the safest next actions.'),
            'campaign' => __('Help me turn one strong idea into a campaign plan for my connected channels.'),
        ];

        if (! isset($prompts[$promptKey])) {
            return;
        }

        $this->tab = 'assistant';
        $this->question = $prompts[$promptKey];
        $this->panelMessage = '';
        $this->dispatch('assistant-focus-question');
    }

    public function prepareFailureQuestion(int $postId, AssistantWorkspaceContext $workspace): void
    {
        $failure = collect((array) data_get($workspace->snapshot(auth()->user()), 'publishing.recent_failures', []))
            ->firstWhere('post_id', $postId);

        if (! is_array($failure)) {
            return;
        }

        $this->tab = 'assistant';
        $this->question = (string) ($failure['assistant_question'] ?? __('Explain this publishing failure and give me the safest next steps.'));
        $this->panelMessageTone = 'neutral';
        $this->panelMessage = __('The question is ready. Review it before using :cost credit.', [
            'cost' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_assistant_turn', 1),
        ]);
        $this->dispatch('assistant-focus-question');
    }

    public function clearPanelMessage(): void
    {
        $this->panelMessage = '';
    }

    public function newIdea(): void
    {
        $this->reset(['activeIdeaId', 'ideaTitle', 'ideaBrief', 'variantTexts', 'selectedAccountIds', 'toolResult', 'panelMessage']);
        $this->ideaPlatforms = ['facebook', 'instagram', 'linkedin'];
        $this->contentTool = 'review';
        $this->publishMode = 'draft';
        $this->confirmPublish = false;
        $this->resetErrorBag();
    }

    public function createIdea(AssistantAuditService $audit): void
    {
        $this->assertAssistantAvailable();
        $validated = $this->validate($this->ideaRules(true));
        $title = trim((string) $validated['ideaTitle']);
        $title = $title !== '' ? $title : trim((string) Str::limit(Str::squish((string) $validated['ideaBrief']), 90, ''));
        $idea = AssistantIdea::query()->create([
            'owner_user_id' => $this->workspaceOwnerUserId(),
            'requested_by_user_id' => (int) auth()->id(),
            'team_id' => $this->currentTeamId(),
            'title' => $title,
            'brief' => trim((string) $validated['ideaBrief']),
            'platforms' => array_values(array_unique((array) $validated['ideaPlatforms'])),
            'status' => 'idea',
            'metadata' => ['created_manually' => true],
        ]);
        $audit->record(auth()->user(), 'idea_created', 'completed', [
            'idea_id' => (int) $idea->id,
            'payload' => ['platforms' => (array) $idea->platforms],
            'result' => ['status' => 'idea'],
        ]);
        $this->selectIdea((int) $idea->id);
        $this->dispatchToast('success', __('Idea saved'), __('Generate platform-ready posts when the brief is ready.'));
    }

    public function suggestIdeas(AiContentStudioService $studio, AssistantCreditActionRunner $runner): void
    {
        $this->assertAssistantAvailable();
        $validated = $this->validate($this->ideaRules(false));

        try {
            $result = $runner->run(
                auth()->user(),
                $this->aiStudioPlanOwner(),
                'ai_assistant_turn',
                $this->ideaRequestKey,
                fn (): array => $studio->planCalendar((string) $validated['ideaBrief'], [
                    'days' => 5,
                    'language' => (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en'),
                    ...$this->aiStudioWorkspacePromptConfig(),
                ]),
                [
                    'action_key' => 'idea_suggestions',
                    'feature' => 'ai-assistant.ideas',
                    'payload' => ['platforms' => (array) $validated['ideaPlatforms']],
                ],
            );
            $createdIds = collect((array) ($result['items'] ?? []))->take(5)->map(function ($item) use ($validated, $result) {
                $item = is_array($item) ? $item : [];
                $theme = trim((string) ($item['theme'] ?? ''));
                $brief = trim(implode("\n\n", array_filter([
                    (string) ($item['caption_brief'] ?? ''),
                    filled($item['objective'] ?? null) ? __('Objective: :value', ['value' => $item['objective']]) : null,
                    filled($item['asset_brief'] ?? null) ? __('Creative direction: :value', ['value' => $item['asset_brief']]) : null,
                    filled($item['cta'] ?? null) ? __('Call to action: :value', ['value' => $item['cta']]) : null,
                ])));

                if ($theme === '' || $brief === '') {
                    return null;
                }

                return AssistantIdea::query()->create([
                    'owner_user_id' => $this->workspaceOwnerUserId(),
                    'requested_by_user_id' => (int) auth()->id(),
                    'team_id' => $this->currentTeamId(),
                    'title' => Str::limit($theme, 180, ''),
                    'brief' => $brief,
                    'platforms' => array_values(array_unique((array) $validated['ideaPlatforms'])),
                    'status' => 'idea',
                    'generated_source' => (string) ($result['source'] ?? 'fallback'),
                    'metadata' => ['assistant_suggestion' => true, 'overview' => trim((string) ($result['overview'] ?? ''))],
                ])->id;
            })->filter()->values();

            if ($createdIds->isEmpty()) {
                throw new \RuntimeException(__('No usable ideas were returned. Refine the brief and try again.'));
            }

            $studio->recordPromptHistory(auth()->user(), 'assistant_ideas', (string) $validated['ideaBrief'], [
                'platforms' => (array) $validated['ideaPlatforms'],
            ], $result, ['title' => __('AI Assistant ideas')]);
            $this->selectIdea((int) $createdIds->first());
            $this->setCreditMessage((array) ($result['_credit'] ?? []), __('Five directions added.'));
        } catch (Throwable $exception) {
            $this->addError('ideaBrief', $exception->getMessage());
        } finally {
            $this->ideaRequestKey = (string) Str::uuid();
        }
    }

    public function selectIdea(int $ideaId): void
    {
        $idea = $this->baseIdeaQuery()->find($ideaId);
        if (! $idea) {
            return;
        }

        $this->activeIdeaId = (int) $idea->id;
        $this->ideaTitle = (string) $idea->title;
        $this->ideaBrief = (string) $idea->brief;
        $this->ideaPlatforms = array_values((array) ($idea->platforms ?: ['facebook']));
        $this->variantTexts = collect((array) $idea->variants)->map(fn ($variant) => is_array($variant) ? $this->composeVariant($variant) : '')->values()->all();
        $this->selectedAccountIds = array_map('strval', (array) $idea->selected_account_ids);
        $this->contentTool = 'review';
        $this->toolResult = [];
        $this->confirmPublish = false;
        $this->panelMessage = '';
        $this->resetErrorBag();
    }

    public function generatePosts(AiContentStudioService $studio, AssistantCreditActionRunner $runner): void
    {
        $this->assertAssistantAvailable();
        $idea = $this->activeIdea();
        if (! $idea) {
            return;
        }

        try {
            $result = $runner->run(
                auth()->user(),
                $this->aiStudioPlanOwner(),
                'ai_studio_generate_captions',
                $this->postRequestKey,
                fn (): array => $studio->generatePlatformCaptions((string) $idea->brief, (array) ($idea->platforms ?: ['facebook']), [
                    'language' => (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en'),
                    'tone' => (string) $this->aiStudioSetting('default_tone', 'professional'),
                    'total_results' => max(1, count((array) $idea->platforms)),
                    ...$this->aiStudioWorkspacePromptConfig(),
                ]),
                [
                    'action_key' => 'post_generation',
                    'feature' => 'ai-assistant.posts',
                    'idea_id' => (int) $idea->id,
                    'payload' => ['platforms' => (array) $idea->platforms],
                ],
            );
            $variants = collect((array) ($result['variants'] ?? []))->filter(fn ($variant) => is_array($variant) && filled($variant['caption'] ?? null))->values()->all();
            if ($variants === []) {
                throw new \RuntimeException(__('No usable post drafts were returned.'));
            }

            $idea->update([
                'status' => 'draft',
                'ai_summary' => trim((string) ($result['summary'] ?? '')),
                'variants' => $variants,
                'generated_source' => (string) ($result['source'] ?? 'fallback'),
                'generated_at' => now(),
                'approved_at' => null,
            ]);
            $studio->recordPromptHistory(auth()->user(), 'assistant_posts', (string) $idea->brief, [
                'idea_id' => (int) $idea->id,
                'platforms' => (array) $idea->platforms,
            ], $result, ['title' => (string) $idea->title]);
            $this->selectIdea((int) $idea->id);
            $this->setCreditMessage((array) ($result['_credit'] ?? []), __('Drafts generated.'));
        } catch (Throwable $exception) {
            $this->addError('ideaBrief', $exception->getMessage());
        } finally {
            $this->postRequestKey = (string) Str::uuid();
        }
    }

    public function runContentTool(AiContentStudioService $studio, AssistantCreditActionRunner $runner, AssistantAuditService $audit): void
    {
        $this->assertAssistantAvailable();
        $idea = $this->activeIdea();
        if (! $idea || empty($idea->variants)) {
            return;
        }

        $this->validate(['contentTool' => ['required', 'in:review,repurpose,best_time']]);
        $content = trim(implode("\n\n---\n\n", array_filter($this->variantTexts)));
        if ($content === '') {
            $this->addError('contentTool', __('Save or enter post copy before running a content tool.'));
            return;
        }

        try {
            if ($this->contentTool === 'best_time') {
                $items = $studio->bestTimeSuggestions($this->workspaceOwnerUserId(), collect($this->selectedAccountIds)->map(fn ($id) => (int) $id)->filter()->all(), $this->currentTimezone(), 4);
                $this->toolResult = ['type' => 'best_time', 'items' => $items, 'source' => 'workspace_data'];
                $audit->record(auth()->user(), 'best_time_reviewed', 'completed', [
                    'idea_id' => (int) $idea->id,
                    'result' => ['count' => count($items), 'credit_cost' => 0],
                ]);
                $this->panelMessageTone = 'success';
                $this->panelMessage = __('Posting-time suggestions are based on workspace history. No credit was used.');
                return;
            }

            $action = $this->contentTool === 'review' ? 'ai_studio_review_content' : 'ai_studio_repurpose_content';
            $result = $runner->run(
                auth()->user(),
                $this->aiStudioPlanOwner(),
                $action,
                $this->toolRequestKey,
                fn (): array => $this->contentTool === 'review'
                    ? $studio->reviewDraft($content, (array) $idea->platforms, [
                        'review_language' => (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en'),
                        ...$this->aiStudioWorkspacePromptConfig(),
                    ])
                    : $studio->repurposeContent($content, (array) $idea->platforms, [
                        'language' => (string) $this->aiStudioSetting('default_language', app()->getLocale() ?: 'en'),
                        ...$this->aiStudioWorkspacePromptConfig(),
                    ]),
                [
                    'action_key' => $this->contentTool === 'review' ? 'post_review' : 'post_repurpose',
                    'feature' => 'ai-assistant.'.$this->contentTool,
                    'idea_id' => (int) $idea->id,
                    'payload' => ['platforms' => (array) $idea->platforms],
                ],
            );
            $this->toolResult = ['type' => $this->contentTool, ...$result];
            $this->setCreditMessage((array) ($result['_credit'] ?? []), __('Tool completed.'));
        } catch (Throwable $exception) {
            $this->addError('contentTool', $exception->getMessage());
        } finally {
            $this->toolRequestKey = (string) Str::uuid();
        }
    }

    public function saveDraftChanges(): void
    {
        if ($idea = $this->activeIdea()) {
            $this->persistVariantDrafts($idea);
            $this->dispatchToast('success', __('Drafts saved'), __('Your edited platform posts are ready for approval.'));
        }
    }

    public function approveIdea(AssistantAuditService $audit): void
    {
        $idea = $this->activeIdea();
        if (! $idea || empty($idea->variants)) {
            return;
        }

        $this->persistVariantDrafts($idea, false);
        $idea->refresh()->update(['status' => 'approved', 'approved_at' => now()]);
        $audit->record(auth()->user(), 'post_copy_confirmed', 'confirmed', [
            'idea_id' => (int) $idea->id,
            'payload' => ['platforms' => (array) $idea->platforms],
            'result' => ['status' => 'approved'],
        ]);
        $this->selectIdea((int) $idea->id);
        $this->panelMessageTone = 'success';
        $this->panelMessage = __('Approved. Choose connected accounts and a safe publishing action.');
    }

    public function executePublishing(AssistantPublishingWorkflow $workflow, AssistantAuditService $audit): void
    {
        $this->assertAssistantAvailable();
        $idea = $this->activeIdea();
        if (! $idea || ! in_array((string) $idea->status, ['approved', 'scheduled', 'published', 'failed'], true)) {
            $this->addError('selectedAccountIds', __('Approve the post drafts before creating publishing actions.'));
            return;
        }

        $this->validate([
            'selectedAccountIds' => ['required', 'array', 'min:1'],
            'selectedAccountIds.*' => ['required'],
            'publishMode' => ['required', 'in:draft,schedule,publish'],
            'scheduleAt' => ['nullable', 'string'],
        ]);
        if ($this->publishMode === 'publish' && ! $this->confirmPublish) {
            $this->addError('confirmPublish', __('Confirm that you want to publish now.'));
            return;
        }

        $requestKey = (string) Str::uuid();
        $payload = [
            'mode' => $this->publishMode,
            'account_ids' => array_map('intval', $this->selectedAccountIds),
            'schedule_at' => $this->scheduleAt,
        ];
        $audit->record(auth()->user(), 'publishing_action', 'confirmed', [
            'idea_id' => (int) $idea->id,
            'request_key' => $requestKey,
            'payload' => $payload,
        ]);

        try {
            $result = $workflow->execute(auth()->user(), $idea, $this->selectedAccountIds, $this->publishMode, $this->scheduleAt);
            $postIds = collect((array) $idea->publishing_post_ids)->merge((array) ($result['post_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
            $status = (string) ($result['status'] ?? 'draft');
            $idea->update([
                'status' => $status,
                'selected_account_ids' => collect($this->selectedAccountIds)->map(fn ($id) => (int) $id)->values()->all(),
                'publishing_post_ids' => $postIds,
                'scheduled_at' => in_array($status, ['scheduled', 'pending_approval'], true) ? now() : $idea->scheduled_at,
                'published_at' => $status === 'published' ? now() : $idea->published_at,
                'metadata' => [
                    ...(array) $idea->metadata,
                    'last_publishing_action' => $this->publishMode,
                    'last_publishing_status' => $status,
                ],
            ]);
            $audit->record(auth()->user(), 'publishing_action', $status === 'failed' ? 'failed' : 'executed', [
                'idea_id' => (int) $idea->id,
                'request_key' => $requestKey,
                'result' => ['status' => $status, 'post_ids' => $postIds, 'errors' => (array) ($result['errors'] ?? [])],
            ]);
            $this->selectIdea((int) $idea->id);
            $this->panelMessageTone = $status === 'failed' ? 'warning' : 'success';
            $this->panelMessage = (string) ($result['message'] ?? __('Publishing action created.'));
            if (! empty($result['errors'])) {
                $this->addError('selectedAccountIds', implode(' ', (array) $result['errors']));
            }
            $this->dispatchToast($status === 'failed' ? 'error' : 'success', $status === 'failed' ? __('Provider action failed') : __('Publishing action created'), (string) ($result['message'] ?? ''));
        } catch (Throwable $exception) {
            $message = $exception instanceof ValidationException ? (string) collect($exception->errors())->flatten()->first() : $exception->getMessage();
            $audit->record(auth()->user(), 'publishing_action', 'failed', [
                'idea_id' => (int) $idea->id,
                'request_key' => $requestKey,
                'error' => $message,
            ]);
            $this->addError('selectedAccountIds', $message);
        }
    }

    public function render(AssistantPublishingWorkflow $workflow, AssistantWorkspaceContext $workspace): View
    {
        $available = $this->assistantAvailable();
        $ideas = collect();
        $accounts = collect();
        $activeIdea = null;
        $workspaceSummary = [];
        $pageGuide = [];
        $navigation = [];
        $recentAudit = collect();
        $creditSummary = ['remaining' => null, 'reserved' => 0, 'unlimited' => true];
        $costs = ['assistant' => 0, 'caption' => 0, 'review' => 0, 'repurpose' => 0, 'image' => 0, 'video' => 0];
        $canPublishDirectly = false;

        if ($available) {
            $query = $this->baseIdeaQuery()->latest('updated_at');
            $query->when($this->tab === 'ideas', fn (Builder $builder) => $builder->where('status', 'idea'))
                ->when($this->tab === 'drafts', fn (Builder $builder) => $builder->where('status', 'draft'))
                ->when($this->tab === 'ready', fn (Builder $builder) => $builder->whereIn('status', ['approved', 'pending_approval', 'scheduled', 'published', 'failed']));
            $ideas = $query->limit((int) config('modules.appaiassistant.idea_limit', 40))->get();
            $activeIdea = $this->activeIdea();
            $accounts = $workflow->availableAccounts(auth()->user());
            $workspaceSummary = $workspace->snapshot(auth()->user());
            $pageGuide = $workspace->pageGuide($this->pageRouteName);
            $navigation = $workspace->navigation();
            $creditSummary = credit_service()->summary($this->aiStudioPlanOwner());
            $costs = [
                'assistant' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_assistant_turn', 1),
                'caption' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_studio_generate_captions', 1),
                'review' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_studio_review_content', 1),
                'repurpose' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_studio_repurpose_content', 1),
                'image' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_studio_generate_image', 3),
                'video' => credit_service()->costFor($this->aiStudioPlanOwner(), 'ai_studio_generate_video', 8),
            ];
            $recentAudit = AssistantAuditLog::query()->forWorkspace($this->workspaceOwnerUserId(), $this->currentTeamId())->latest('id')->limit(6)->get();
            $canPublishDirectly = TeamWorkspaceAccess::hasPermission(auth()->user(), 'post.publish', TeamWorkspaceAccess::activeTeam(auth()->user()));
        }

        return view('appaiassistant::livewire.dock', [
            'available' => $available,
            'ideas' => $ideas,
            'activeIdea' => $activeIdea,
            'accounts' => $accounts,
            'workspaceSummary' => $workspaceSummary,
            'pageGuide' => $pageGuide,
            'navigation' => $navigation,
            'recentAudit' => $recentAudit,
            'creditSummary' => $creditSummary,
            'costs' => $costs,
            'canPublishDirectly' => $canPublishDirectly,
            'platformOptions' => [
                'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X',
                'threads' => 'Threads', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest',
            ],
            'publishingRoute' => \Illuminate\Support\Facades\Route::has('portal.publishing.calendar') ? route('portal.publishing.calendar') : null,
        ]);
    }

    protected function persistVariantDrafts(AssistantIdea $idea, bool $markDraft = true): void
    {
        $this->validate(['variantTexts' => ['required', 'array', 'min:1'], 'variantTexts.*' => ['required', 'string', 'max:10000']]);
        $variants = collect((array) $idea->variants)->values()->map(function ($variant, int $index): array {
            return [
                ...(is_array($variant) ? $variant : []),
                'caption' => trim((string) ($this->variantTexts[$index] ?? '')),
                'hook' => '', 'cta' => '', 'hashtags' => [],
            ];
        })->all();
        $idea->update(['variants' => $variants, 'status' => $markDraft ? 'draft' : $idea->status, 'approved_at' => $markDraft ? null : $idea->approved_at]);
        $this->selectIdea((int) $idea->id);
    }

    protected function activeIdea(): ?AssistantIdea
    {
        return $this->activeIdeaId && $this->assistantAvailable() ? $this->baseIdeaQuery()->find($this->activeIdeaId) : null;
    }

    protected function baseIdeaQuery(): Builder
    {
        return AssistantIdea::query()->forWorkspace($this->workspaceOwnerUserId(), $this->currentTeamId());
    }

    protected function assistantAvailable(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return TeamWorkspaceAccess::teamHasModule($team, 'ai_assistant')
            && TeamWorkspaceAccess::teamHasModule($team, 'publishing')
            && TeamWorkspaceAccess::hasPermission($user, 'assistant.use', $team)
            && TeamWorkspaceAccess::hasPermission($user, 'post.create', $team)
            && ($planOwner?->canUsePlanFeature('ai_studio') ?? false)
            && ($planOwner?->canUsePlanFeature('publishing') ?? false)
            && AIStudioPlanFeature::enabled($planOwner, 'ai_assistant')
            && AIStudioPlanFeature::enabled($planOwner, 'ai_studio_caption_generator');
    }

    protected function assertAssistantAvailable(): void
    {
        abort_unless($this->assistantAvailable(), 403);
    }

    protected function currentTeamId(): ?int
    {
        return TeamWorkspaceAccess::activeTeam(auth()->user())?->id;
    }

    protected function ideaRules(bool $includeTitle): array
    {
        $rules = [
            'ideaBrief' => ['required', 'string', 'max:5000'],
            'ideaPlatforms' => ['required', 'array', 'min:1'],
            'ideaPlatforms.*' => ['required', 'string', 'in:facebook,instagram,linkedin,x,threads,tiktok,youtube,pinterest'],
        ];
        if ($includeTitle) {
            $rules['ideaTitle'] = ['nullable', 'string', 'max:180'];
        }

        return $rules;
    }

    protected function composeVariant(array $variant): string
    {
        $hashtags = collect((array) ($variant['hashtags'] ?? []))->map(fn ($tag) => '#'.ltrim(trim((string) $tag), '#'))->filter(fn ($tag) => $tag !== '#')->implode(' ');

        return trim(implode("\n\n", array_filter([
            trim((string) ($variant['hook'] ?? '')), trim((string) ($variant['caption'] ?? '')),
            trim((string) ($variant['cta'] ?? '')), $hashtags,
        ])));
    }

    protected function rotateRequestKeys(): void
    {
        $this->turnRequestKey = (string) Str::uuid();
        $this->ideaRequestKey = (string) Str::uuid();
        $this->postRequestKey = (string) Str::uuid();
        $this->toolRequestKey = (string) Str::uuid();
    }

    protected function setCreditMessage(array $credit, string $success): void
    {
        $this->panelMessageTone = ! empty($credit['charged']) ? 'success' : 'warning';
        $this->panelMessage = ! empty($credit['charged'])
            ? $success.' '.__(':cost credit used; balance is now :balance.', [
                'cost' => (int) ($credit['cost'] ?? 0),
                'balance' => $credit['after'] ?? __('Unlimited'),
            ])
            : $success.' '.__('Local fallback was used; no credit was charged.');
    }

    protected function dispatchToast(string $type, string $title, string $message): void
    {
        $this->dispatch('app-toast', type: $type, title: $title, message: $message);
    }
}
