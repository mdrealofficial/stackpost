<?php

namespace Modules\AppAIContentPlanner\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Throwable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppAIContentPlanner\Models\AIContentPlan;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Content Planner')]
class ContentPlannerIndex extends Component
{
    use AIStudioAccess;

    public string $planName = '';
    public string $calendarBrief = '';
    public string $plannerStartDate = '';
    public int $plannerDays = 14;
    public array $result = [];
    public ?int $savedPlanId = null;

    public function mount(): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);
        $this->plannerStartDate = now($this->currentTimezone())->toDateString();
        $this->plannerDays = max(3, min(31, (int) $this->aiStudioSetting('planner_days', $this->plannerDays)));
        $this->restoreLatestSavedPlan();
    }

    public function plan(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);

        $validated = $this->validate([
            'calendarBrief' => ['required', 'string', 'max:5000'],
            'plannerDays' => ['required', 'integer', 'min:3', 'max:31'],
            'plannerStartDate' => ['required', 'date'],
        ]);

        try {
            $planOwner = $this->aiStudioPlanOwner();
            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'ai_studio_plan_calendar');
            }

            $plan = $studio->planCalendar($validated['calendarBrief'], [
                'days' => $validated['plannerDays'],
                'language' => (string) $this->aiStudioSetting('default_language', (string) (app()->getLocale() ?: 'en')),
                ...$this->aiStudioWorkspacePromptConfig(),
            ]);

            $start = Carbon::parse($validated['plannerStartDate'], $this->currentTimezone())->startOfDay();
            $this->result = [
                ...$plan,
                'items' => collect((array) ($plan['items'] ?? []))->map(fn ($item) => [
                    ...$item,
                    'date' => $start->copy()->addDays((int) ($item['date_offset'] ?? 0))->toDateString(),
                ])->all(),
            ];

            $studio->recordPromptHistory(
                auth()->user(),
                'content_planner',
                $validated['calendarBrief'],
                [
                    'days' => $validated['plannerDays'],
                    'start_date' => $validated['plannerStartDate'],
                    'plan_name' => $this->planName,
                ],
                $this->result,
                [
                    'title' => trim($this->planName) !== '' ? trim($this->planName) : __('Planner · :days days', ['days' => $validated['plannerDays']]),
                ],
            );

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'ai_studio_plan_calendar', [
                    'feature' => 'ai-studio.content-planner',
                    'metadata' => [
                        'days' => $validated['plannerDays'],
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            $this->addError('calendarBrief', $exception->getMessage());
        }
    }

    public function savePlan(): void
    {
        $this->createPlan();
    }

    public function createPlan(): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);

        if ($this->result === [] || empty($this->result['items'])) {
            return;
        }

        $validated = $this->validate([
            'planName' => ['required', 'string', 'max:120'],
        ]);

        $payload = [
            'owner_user_id' => $this->workspaceOwnerUserId(),
            'requested_by_user_id' => (int) auth()->id(),
            'team_id' => TeamWorkspaceAccess::activeTeam(auth()->user())?->id,
            'title' => trim($validated['planName']),
            'brief' => trim($this->calendarBrief),
            'start_date' => $this->plannerStartDate,
            'days' => $this->plannerDays,
            'source' => (string) ($this->result['source'] ?? 'ai'),
            'overview' => trim((string) ($this->result['overview'] ?? '')),
            'items' => array_values((array) ($this->result['items'] ?? [])),
            'metadata' => [
                'saved_at' => now()->toDateTimeString(),
            ],
        ];

        $plan = AIContentPlan::query()->create($payload);

        if ($plan) {
            $this->savedPlanId = (int) $plan->id;
        }

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Plan saved'),
            message: __('A new content plan has been saved.')
        );
    }

    public function updateSavedPlan(): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);

        $plan = $this->currentPlan();

        if (! $plan || $this->result === [] || empty($this->result['items'])) {
            return;
        }

        $validated = $this->validate([
            'planName' => ['required', 'string', 'max:120'],
        ]);

        $plan->update([
            'title' => trim($validated['planName']),
            'brief' => trim($this->calendarBrief),
            'start_date' => $this->plannerStartDate,
            'days' => $this->plannerDays,
            'source' => (string) ($this->result['source'] ?? 'ai'),
            'overview' => trim((string) ($this->result['overview'] ?? '')),
            'items' => array_values((array) ($this->result['items'] ?? [])),
            'metadata' => array_merge((array) ($plan->metadata ?? []), [
                'saved_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]),
        ]);

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Plan updated'),
            message: __('The selected content plan has been updated.')
        );
    }

    public function loadPlan(int $planId): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);

        $plan = $this->basePlanQuery()->whereKey($planId)->first();

        if (! $plan) {
            return;
        }

        $this->fillFromPlan($plan);

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Plan loaded'),
            message: __('Saved content plan loaded into the workspace.')
        );
    }

    public function deletePlan(int $planId): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);

        $plan = $this->basePlanQuery()->whereKey($planId)->first();

        if (! $plan) {
            return;
        }

        $wasCurrent = $this->savedPlanId === (int) $plan->id;

        $plan->delete();

        if ($wasCurrent) {
            $this->savedPlanId = null;
            $this->planName = '';
            $this->calendarBrief = '';
            $this->plannerStartDate = now($this->currentTimezone())->toDateString();
            $this->plannerDays = 14;
            $this->result = [];
            $this->restoreLatestSavedPlan();
        }

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Plan deleted'),
            message: __('The saved content plan has been removed.')
        );
    }

    public function newPlan(): void
    {
        $this->savedPlanId = null;
        $this->planName = '';
        $this->calendarBrief = '';
        $this->plannerStartDate = now($this->currentTimezone())->toDateString();
        $this->plannerDays = 14;
        $this->result = [];
        $this->resetErrorBag();
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->planName = (string) data_get($history->input_payload, 'plan_name', $this->planName);
        $this->calendarBrief = (string) $history->prompt;
        $this->plannerDays = (int) data_get($history->input_payload, 'days', $this->plannerDays);
        $this->plannerStartDate = (string) data_get($history->input_payload, 'start_date', $this->plannerStartDate);
        $this->result = is_array($history->output_payload) ? $history->output_payload : [];
        $this->savedPlanId = null;
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_content_planner'), 404);

        return view('appaicontentplanner::index', [
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_plan_calendar'),
            'savedPlan' => $this->currentPlan(),
            'savedPlans' => $this->basePlanQuery()->latest('updated_at')->limit(12)->get(),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Content Planner'),
            'showLoadingBackdrop' => false,
        ]);
    }

    protected function restoreLatestSavedPlan(): void
    {
        $plan = $this->basePlanQuery()->latest('id')->first();

        if (! $plan) {
            return;
        }

        $this->fillFromPlan($plan);
    }

    protected function currentPlan(): ?AIContentPlan
    {
        if ($this->savedPlanId) {
            return $this->basePlanQuery()->whereKey($this->savedPlanId)->first();
        }

        return $this->basePlanQuery()->latest('id')->first();
    }

    protected function basePlanQuery()
    {
        return AIContentPlan::query()
            ->ownedBy($this->workspaceOwnerUserId());
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'content_planner');
    }

    protected function fillFromPlan(AIContentPlan $plan): void
    {
        $this->savedPlanId = (int) $plan->id;
        $this->planName = (string) ($plan->title ?? '');
        $this->calendarBrief = (string) $plan->brief;
        $this->plannerStartDate = optional($plan->start_date)->toDateString() ?: $this->plannerStartDate;
        $this->plannerDays = (int) ($plan->days ?: $this->plannerDays);
        $this->result = [
            'overview' => (string) ($plan->overview ?? ''),
            'items' => (array) ($plan->items ?? []),
            'source' => (string) ($plan->source ?? 'ai'),
        ];
    }
}
