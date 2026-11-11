<?php

namespace Modules\AppAutomation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRevision;
use Modules\AppAutomation\Services\AutomationWorkflowCreditRunner;
use Modules\AppAutomation\Services\AutomationWorkflowHandlerRegistry;
use Modules\AppAutomation\Services\AutomationWorkflowRuntimeReadiness;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveConnectionService;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkflowGraph;
use Modules\AppAutomation\Support\AutomationWorkflowNodeCatalog;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Workflow Builder')]
class AutomationWorkflowBuilder extends Component
{
    #[Locked]
    public ?int $workflowId = null;

    public string $flowCanvas = '';

    public ?array $testResult = null;

    protected AutomationAccess $access;

    public function boot(AutomationAccess $access): void
    {
        $this->access = $access;
    }

    public function mount(?int $workflow = null): void
    {
        abort_unless($this->access->canView(auth()->user()), 404);
        abort_unless($this->schemaReady(), 503);

        if ($workflow) {
            $record = $this->ownedWorkflowsQuery()->findOrFail($workflow);
            $this->workflowId = (int) $record->id;
            $graph = AutomationWorkflowGraph::sanitize((array) data_get($record->settings, 'draft_definition', $record->definition));
        } else {
            $template = (string) request()->query('template', 'drive_draft');
            $graph = AutomationWorkflowGraph::template($template);
        }

        $this->flowCanvas = json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function preview(): void
    {
        abort_unless($this->access->canView(auth()->user()), 403);
        $graph = $this->decodedGraph();
        $issues = app(AutomationWorkflowRuntimeReadiness::class)->issues(
            $graph,
            $this->access->workspaceOwnerUserId(auth()->user()),
            $this->access->teamId(auth()->user()),
            (int) auth()->id(),
        );
        $notices = collect(AutomationWorkflowGraph::runtimeNotices($graph))
            ->reject(fn (string $notice): bool => in_array($notice, $issues, true))
            ->values()->all();

        $this->testResult = [
            'valid' => $issues === [],
            'issues' => array_values(array_unique(array_map([\Modules\AppAutomation\Support\AutomationWorkflowCapabilities::class, 'userMessage'], $issues))),
            'notices' => $notices,
            'steps' => collect($graph['nodes'])->map(fn (array $node): array => [
                'label' => (string) data_get(AutomationWorkflowNodeCatalog::definitions(), $node['type'].'.label', __('Workflow step')),
                'runtime' => (string) data_get(AutomationWorkflowNodeCatalog::definitions(), $node['type'].'.runtime', 'engine'),
            ])->all(),
        ];

        $this->dispatch('open-automation-test-result');
    }

    public function saveGraph(string $graphJson, bool $publish = false): void
    {
        abort_unless($this->access->canManage(auth()->user()), 403);
        if ($publish) {
            abort_unless($this->access->canExecute(auth()->user()), 403);
        }

        $this->resetErrorBag('flowCanvas');
        $decoded = json_decode($graphJson, true);
        if (! is_array($decoded)) {
            $this->addError('flowCanvas', __('The workflow graph is invalid.'));
            return;
        }

        $graph = AutomationWorkflowGraph::sanitize($decoded);
        $issues = $publish ? app(AutomationWorkflowRuntimeReadiness::class)->issues(
            $graph,
            $this->access->workspaceOwnerUserId(auth()->user()),
            $this->access->teamId(auth()->user()),
            (int) auth()->id(),
        ) : [];
        if ($issues !== []) {
            $this->addError('flowCanvas', implode(' ', $issues));
            $this->flowCanvas = $graphJson;
            $this->preview();
            $this->toast(\Modules\AppAutomation\Support\AutomationWorkflowCapabilities::userMessage((string) $issues[0]), 'danger');
            return;
        }

        if (! $this->workflowId) {
            $this->assertWorkflowLimit();
        }

        $workflow = app(\Modules\AppAutomation\Services\AutomationWorkflowDrafts::class)->save(
            $this->workflowId,
            $this->access->workspaceOwnerUserId(auth()->user()),
            $this->access->teamId(auth()->user()),
            (int) auth()->id(), $graph, $publish,
        );

        $this->workflowId = (int) $workflow->id;
        $this->flowCanvas = json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $this->toast($publish ? __('Workflow activated.') : ($workflow->published_at ? __('Draft saved. The published version is unchanged.') : __('Workflow draft saved.')));
        $this->dispatch('automation-saved');
        $this->redirectRoute('portal.automation.workflows.edit', ['workflow' => $workflow->id], navigate: true);
    }

    public function render(): View
    {
        abort_unless($this->access->canView(auth()->user()), 404);
        $workflow = $this->workflowId ? $this->ownedWorkflowsQuery()->find($this->workflowId) : null;
        $team = TeamWorkspaceAccess::activeTeam(auth()->user());
        $planOwner = $team?->owner ?: auth()->user();
        $workspaceOwnerUserId = $this->access->workspaceOwnerUserId(auth()->user());
        $driveConnections = Schema::hasTable('automation_connections')
            ? AutomationConnection::query()
                ->where('owner_user_id', $workspaceOwnerUserId)
                ->when(
                    $this->access->teamId(auth()->user()),
                    fn ($query, $teamId) => $query->where('team_id', $teamId),
                    fn ($query) => $query->whereNull('team_id')
                )
                ->where('provider', 'google_drive')
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('label')
                ->get(['id', 'label', 'status', 'last_verified_at'])
                ->map(fn (AutomationConnection $connection): array => [
                    'id' => (string) $connection->id,
                    'label' => trim((string) $connection->label) ?: __('Google Drive'),
                    'status' => (string) $connection->status,
                    'verified_at' => $connection->last_verified_at?->diffForHumans(),
                ])
                ->values()
                ->all()
            : [];
        $publishingCampaigns = Schema::hasTable('publishing_campaigns')
            ? PublishingCampaign::query()
                ->ownedBy($workspaceOwnerUserId)
                ->whereIn('status', ['active', 'draft'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'color'])
                ->map(fn (PublishingCampaign $campaign): array => [
                    'id' => (string) $campaign->id,
                    'name' => (string) $campaign->name,
                    'status' => (string) $campaign->status,
                    'color' => (string) $campaign->color,
                ])->values()->all()
            : [];
        $publishingLabels = Schema::hasTable('publishing_labels')
            ? PublishingLabel::query()
                ->ownedBy($workspaceOwnerUserId)
                ->whereIn('status', ['active', 'draft'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'color'])
                ->map(fn (PublishingLabel $label): array => [
                    'id' => (string) $label->id,
                    'name' => (string) $label->name,
                    'status' => (string) $label->status,
                    'color' => (string) $label->color,
                ])->values()->all()
            : [];
        $publishingAccounts = Schema::hasTable('social_accounts')
            ? TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
                ->where('is_active', true)
                ->where(function ($query): void {
                    $capabilityKeys = publishable_channel_capability_keys(auth()->user());
                    $query->whereIn('capability_key', $capabilityKeys)
                        ->orWhere(function ($fallback) use ($capabilityKeys): void {
                            $fallback->where(function ($missingCapability): void {
                                $missingCapability->whereNull('capability_key')->orWhere('capability_key', '');
                            })->whereIn('provider_key', $capabilityKeys);
                        });
                })
                ->orderBy('provider_key')
                ->orderBy('display_name')
                ->get(['id', 'provider_key', 'display_name', 'username'])
                ->map(fn ($account): array => [
                    'id' => (string) $account->id,
                    'provider' => (string) $account->provider_key,
                    'name' => trim((string) $account->display_name) ?: trim((string) $account->username) ?: __('Social account'),
                    'username' => trim((string) $account->username),
                ])->values()->all()
            : [];
        $socialAccounts = Schema::hasTable('social_accounts')
            ? TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
                ->where('is_active', true)
                ->orderBy('provider_key')
                ->orderBy('display_name')
                ->get(['id', 'provider_key', 'display_name', 'username'])
                ->map(fn ($account): array => [
                    'id' => (string) $account->id,
                    'provider' => (string) $account->provider_key,
                    'name' => trim((string) $account->display_name) ?: trim((string) $account->username) ?: __('Social account'),
                    'username' => trim((string) $account->username),
                ])->values()->all()
            : [];
        $connectedProviders = Schema::hasTable('social_accounts')
            ? TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
                ->where('is_active', true)
                ->pluck('provider_key')
                ->map(function ($provider): string {
                    $provider = strtolower(trim((string) $provider));

                    return match (true) {
                        str_starts_with($provider, 'facebook') => 'facebook',
                        str_starts_with($provider, 'instagram') => 'instagram',
                        str_starts_with($provider, 'youtube') => 'youtube',
                        default => $provider,
                    };
                })
                ->intersect(['facebook', 'instagram', 'youtube'])
                ->unique()->values()->all()
            : [];
        $aiCreditPolicy = app(AutomationWorkflowCreditRunner::class)->preview($planOwner, $workflow);

        return view('appautomation::livewire.workflow-builder', [
            'availableNodeTypes' => \Modules\AppAutomation\Support\AutomationWorkflowCapabilities::availableNodeTypes(),
            'hasTeam' => (bool) $team,
            'nodeCatalog' => AutomationWorkflowNodeCatalog::definitions(),
            'nodeGroups' => AutomationWorkflowNodeCatalog::groups(),
            'driveConnections' => $driveConnections,
            'publishingCampaigns' => $publishingCampaigns,
            'publishingLabels' => $publishingLabels,
            'publishingAccounts' => $publishingAccounts,
            'socialAccounts' => $socialAccounts,
            'connectedProviders' => $connectedProviders,
            'aiCreditPolicy' => $aiCreditPolicy,
            'runtimeHandlerTypes' => app(AutomationWorkflowHandlerRegistry::class)->registeredTypes(),
            'workflow' => $workflow,
            'workflowStatusLabel' => $workflow ? str((string) $workflow->status)->headline() : __('New draft'),
            'canManage' => $this->access->canManage(auth()->user()),
            'canExecute' => $this->access->canExecute(auth()->user()),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Workflow Builder'),
            'fullWorkspace' => true,
            'fullWorkspacePaddingBottom' => false,
            'fullWorkspaceNaturalHeight' => true,
        ]);
    }

    protected function decodedGraph(): array
    {
        $decoded = json_decode($this->flowCanvas, true);

        return AutomationWorkflowGraph::sanitize(is_array($decoded) ? $decoded : []);
    }

    protected function ownedWorkflowsQuery()
    {
        return AutomationWorkflow::query()
            ->where('owner_user_id', $this->access->workspaceOwnerUserId(auth()->user()))
            ->when($this->access->teamId(auth()->user()), fn ($query, $teamId) => $query->where('team_id', $teamId), fn ($query) => $query->whereNull('team_id'));
    }

    protected function descriptionFor(array $graph): string
    {
        $labels = collect($graph['nodes'])->map(fn (array $node): string => (string) data_get(AutomationWorkflowNodeCatalog::definitions(), $node['type'].'.label'))->filter()->values();

        return $labels->take(4)->implode(' → ');
    }

    protected function schemaReady(): bool
    {
        return Schema::hasTable('automation_workflows') && Schema::hasTable('automation_workflow_revisions');
    }

    protected function assertWorkflowLimit(): void
    {
        $team = TeamWorkspaceAccess::activeTeam(auth()->user());
        $planOwner = $team?->owner ?: auth()->user();
        if (! $planOwner?->plan) {
            return;
        }

        $limit = max(0, (int) $planOwner->planLimit('max_automation_workflows', 10));
        if ($limit > 0 && $this->ownedWorkflowsQuery()->count() >= $limit) {
            throw ValidationException::withMessages([
                'flowCanvas' => __('Your current plan allows up to :count AI workflows.', ['count' => $limit]),
            ]);
        }
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('app-toast', type: $type, title: __('AI Workflows'), message: $message);
    }
}
