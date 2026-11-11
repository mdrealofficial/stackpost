<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Support\Facades\DB;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRevision;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveConnectionService;
use Modules\AppAutomation\Support\AutomationWorkflowGraph;
use Modules\AppAutomation\Support\AutomationWorkflowNodeCatalog;

final class AutomationWorkflowDrafts
{
    // Published definition/current_revision remain authoritative for new triggers.
    // Unpublished edits live in settings until explicitly activated.
    public function save(?int $id, int $owner, ?int $team, int $actor, array $graph, bool $activate): AutomationWorkflow
    {
        return DB::transaction(function () use ($id, $owner, $team, $actor, $graph, $activate) {
            $workflow = $id ? AutomationWorkflow::where('owner_user_id', $owner)->where('team_id', $team)->lockForUpdate()->findOrFail($id) : new AutomationWorkflow();
            $revision = $workflow->exists ? max((int) $workflow->current_revision, (int) $workflow->revisions()->max('revision')) + 1 : 1;
            $keepPublished = $workflow->exists && $workflow->published_at && ! $activate;
            $settings = (array) $workflow->settings;
            if ($keepPublished) {
                $settings['draft_definition'] = $graph;
                $settings['draft_revision'] = $revision;
                $workflow->fill(['settings' => $settings])->save();
            } else {
                unset($settings['draft_definition'], $settings['draft_revision']);
                $labels = collect($graph['nodes'])->map(fn ($node) => data_get(AutomationWorkflowNodeCatalog::definitions(), $node['type'].'.label'))->filter();
                $workflow->fill([
                    'owner_user_id' => $owner, 'team_id' => $team,
                    'created_by_user_id' => $workflow->created_by_user_id ?: $actor,
                    'activated_by_user_id' => $activate ? $actor : $workflow->activated_by_user_id,
                    'name' => AutomationWorkflowGraph::name($graph), 'description' => $labels->take(4)->implode(' → '),
                    'status' => $activate ? 'active' : 'draft', 'trigger_key' => AutomationWorkflowGraph::triggerKey($graph),
                    'current_revision' => $revision, 'definition' => $graph,
                    'settings' => [...$settings, 'approval_required' => true, 'safe_mode' => true, 'credit_policy' => ['charge_mode' => 'successful_ai_output', 'retry_scope' => 'step_attempt', 'assistant_turn_charge' => false]],
                    'published_at' => $activate ? now() : $workflow->published_at,
                ])->save();
                app(GoogleDriveConnectionService::class)->syncWorkflowFolderWatches($workflow, $graph);
            }
            AutomationWorkflowRevision::create([
                'workflow_id' => $workflow->id, 'created_by_user_id' => $actor, 'revision' => $revision,
                'definition' => $graph, 'status' => $activate ? 'active' : 'draft', 'published_at' => $activate ? now() : null,
            ]);
            return $workflow;
        });
    }
}
