<?php

namespace Modules\AppAutomation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Services\AutomationWorkflowRecovery;
use Modules\AppAutomation\Support\AutomationAccess;
use RuntimeException;

#[Title('Workflow runs')]
class AutomationWorkflowRuns extends Component
{
    use WithPagination;
    public ?int $selectedRunId = null;
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';
    #[Url(as: 'workflow', except: '')]
    public string $workflowFilter = '';
    #[Url(as: 'period', except: '')]
    public string $period = '';
    protected AutomationAccess $access;
    public function boot(AutomationAccess $access): void { $this->access = $access; }
    public function mount(): void { abort_unless($this->access->canView(auth()->user()), 404); }
    public function updated($property): void
    {
        if (in_array($property, ['statusFilter', 'workflowFilter', 'period'], true)) { $this->resetPage(); $this->selectedRunId = null; }
    }
    public function clearFilters(): void { $this->statusFilter = $this->workflowFilter = $this->period = ''; $this->resetPage(); $this->selectedRunId = null; }
    public function selectRun(int $id): void { $this->selectedRunId = $this->filteredQuery()->findOrFail($id)->id; }
    public function recoverRun(int $id, string $action): void
    {
        try {
            app(AutomationWorkflowRecovery::class)->recover($id, auth()->user(), $action);
            $this->dispatch('app-toast', type: 'success', title: __('Workflow runs'), message: $action === 'retry' ? __('Run queued from its last unfinished step.') : __('Run stopped. Existing publishing drafts were kept.'));
        } catch (RuntimeException $error) {
            $this->dispatch('app-toast', type: 'warning', title: __('Run needs attention'), message: \Modules\AppAutomation\Support\AutomationWorkflowCapabilities::userMessage($error->getMessage()));
        }
    }
    public function render(): View
    {
        abort_unless($this->access->canView(auth()->user()), 404);
        $runs = $this->filteredQuery()->with('workflow:id,name')->latest('id')->paginate(20);
        $selectedRun = $this->selectedRunId ? $this->filteredQuery()->with(['workflow', 'steps'])->find($this->selectedRunId) : $runs->first();
        $this->selectedRunId = $selectedRun?->id;
        $steps = $selectedRun?->steps()->orderBy('id')->get() ?? collect();
        return view('appautomation::livewire.workflow-runs', [
            'runs' => $runs, 'selectedRun' => $selectedRun, 'steps' => $steps,
            'canExecute' => $this->access->canExecute(auth()->user()),
            'workflows' => AutomationWorkflow::where('owner_user_id', $this->access->workspaceOwnerUserId(auth()->user()))->where('team_id', $this->access->teamId(auth()->user()))->orderBy('name')->get(['id','name']),
            'inputPreview' => $this->contentPreview((array) $selectedRun?->input),
            'outputPreview' => $this->contentPreview((array) data_get($selectedRun?->output, 'last', [])),
        ])->layout(theme_view('layouts.app', 'app'), ['title' => __('Workflow runs')]);
    }
    protected function filteredQuery()
    {
        return $this->runsQuery()
            ->when($this->statusFilter === 'attention', fn ($q) => $q->whereIn('status', ['failed', 'waiting_approval']))
            ->when($this->statusFilter !== '' && $this->statusFilter !== 'attention', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->workflowFilter !== '', fn ($q) => $q->where('workflow_id', (int) $this->workflowFilter))
            ->when(in_array($this->period, ['7', '30', '90'], true), fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $this->period)));
    }
    protected function runsQuery()
    {
        return AutomationWorkflowRun::where('owner_user_id', $this->access->workspaceOwnerUserId(auth()->user()))->where('team_id', $this->access->teamId(auth()->user()));
    }
    protected function contentPreview(array $payload): array
    {
        // Explicit content allowlist: never render credentials or arbitrary event metadata.
        $result = [];
        foreach (['file_name', 'name', 'mime_type', 'caption', 'content', 'text', 'post_ids', 'scheduled_count', 'status'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) || is_numeric($value)) { $result[$key] = mb_substr((string) $value, 0, 3000); }
            elseif ($key === 'post_ids' && is_array($value)) { $result[$key] = implode(', ', array_slice(array_map('intval', $value), 0, 30)); }
        }
        return $result;
    }
}
