<?php

namespace Modules\AppAutomation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Services\AutomationQueueReadiness;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveApiService;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveConnectionService;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkflowNodeCatalog;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Workflows')]
class AutomationWorkflowLibrary extends Component
{
    public ?int $selectedRunId = null;

    protected AutomationAccess $access;

    public function boot(AutomationAccess $access): void
    {
        $this->access = $access;
    }

    public function mount(): void
    {
        abort_unless($this->access->canView(auth()->user()), 404);
    }

    public function selectRun(int $runId): void
    {
        $run = $this->runsQuery()->find($runId);
        $this->selectedRunId = $run ? (int) $run->id : null;
    }

    public function pauseWorkflow(int $workflowId): void
    {
        abort_unless($this->access->canManage(auth()->user()), 403);
        $workflow = $this->workflowsQuery()->findOrFail($workflowId);
        $workflow->update(['status' => 'paused']);
        app(GoogleDriveConnectionService::class)->syncWorkflowFolderWatches($workflow->fresh(), (array) $workflow->definition);
        $this->dispatch('app-toast', type: 'success', title: __('Workflow paused'), message: __('New events will not start this workflow.'));
    }

    public function render(): View
    {
        abort_unless($this->access->canView(auth()->user()), 404);

        $schemaReady = $this->schemaReady();
        $workflows = $schemaReady ? $this->workflowsQuery()->withCount('runs')->latest('updated_at')->get() : collect();
        $runs = $schemaReady ? $this->runsQuery()->with('workflow:id,name')->latest('id')->limit(12)->get() : collect();
        $selectedRun = $this->selectedRunId ? $runs->firstWhere('id', $this->selectedRunId) : $runs->first();
        $this->selectedRunId = $selectedRun ? (int) $selectedRun->id : null;
        $driveStorageReady = Schema::hasTable('automation_drive_channels') && Schema::hasTable('automation_drive_folder_watches');
        $connections = collect();
        if ($schemaReady && Schema::hasTable('automation_connections')) {
            $connectionQuery = AutomationConnection::query()
                ->where('owner_user_id', $this->access->workspaceOwnerUserId(auth()->user()))
                ->when($this->access->teamId(auth()->user()), fn ($query, $teamId) => $query->where('team_id', $teamId), fn ($query) => $query->whereNull('team_id'));
            if ($driveStorageReady) {
                $connectionQuery->with(['driveChannel', 'folderWatches']);
            }
            $connections = $connectionQuery->get();
        }
        $channelCount = Schema::hasTable('social_accounts')
            ? TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())->where('is_active', true)->where(function ($query) {
                $keys = publishable_channel_capability_keys(auth()->user());
                $query->whereIn('capability_key', $keys)->orWhere(fn ($q) => $q->where(fn ($missing) => $missing->whereNull('capability_key')->orWhere('capability_key', ''))->whereIn('provider_key', $keys));
            })->count()
            : 0;

        return view('appautomation::livewire.workflow-library', [
            'schemaReady' => $schemaReady,
            'canManage' => $this->access->canManage(auth()->user()),
            'workflows' => $workflows,
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'connections' => $connections,
            'driveStorageReady' => $driveStorageReady,
            'driveConfiguration' => app(GoogleDriveApiService::class)->readiness(),
            'queueReadiness' => app(AutomationQueueReadiness::class)->status(),
            'canManageConnections' => $this->access->canManageCredentials(auth()->user()),
            'channelCount' => $channelCount,
            'summary' => [
                'workflows' => $workflows->count(),
                'active' => $workflows->where('status', 'active')->count(),
                'drafts' => $workflows->where('status', 'draft')->count(),
                'attention' => $schemaReady ? $this->runsQuery()->whereIn('status', ['failed', 'waiting_approval'])->count() : 0,
            ],
            'templates' => $this->templates(),
            'nodeCatalog' => AutomationWorkflowNodeCatalog::definitions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Workflows'),
        ]);
    }

    protected function workflowsQuery()
    {
        return AutomationWorkflow::query()
            ->where('owner_user_id', $this->access->workspaceOwnerUserId(auth()->user()))
            ->when($this->access->teamId(auth()->user()), fn ($query, $teamId) => $query->where('team_id', $teamId), fn ($query) => $query->whereNull('team_id'));
    }

    protected function runsQuery()
    {
        return AutomationWorkflowRun::query()
            ->where('owner_user_id', $this->access->workspaceOwnerUserId(auth()->user()))
            ->when($this->access->teamId(auth()->user()), fn ($query, $teamId) => $query->where('team_id', $teamId), fn ($query) => $query->whereNull('team_id'));
    }

    protected function schemaReady(): bool
    {
        return Schema::hasTable('automation_workflows')
            && Schema::hasTable('automation_workflow_revisions')
            && Schema::hasTable('automation_workflow_runs')
            && Schema::hasTable('automation_workflow_step_runs');
    }

    protected function templates(): array
    {
        return \Modules\AppAutomation\Support\AutomationWorkflowCapabilities::templates();
    }
}
