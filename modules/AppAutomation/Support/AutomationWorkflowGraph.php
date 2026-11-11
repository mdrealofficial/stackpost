<?php

namespace Modules\AppAutomation\Support;

use Illuminate\Support\Str;
use Modules\AppAutomation\Services\AutomationWorkflowHandlerRegistry;

final class AutomationWorkflowGraph
{
    public static function template(string $template = 'google_drive'): array
    {
        return match ($template) {
            'drive_draft' => self::linear([
                ['trigger_google_drive', ['name' => 'Drive to publishing draft']],
                ['import_drive_file', []], ['ai_write', []], ['create_draft', []], ['end', []],
            ]),
            'drive_review' => self::linear([
                ['trigger_google_drive', ['name' => 'Drive review queue']],
                ['import_drive_file', []], ['ai_write', []], ['approval', []], ['notify_team', []], ['end', []],
            ]),
            'social_engagement' => self::linear([
                ['trigger_social_engagement', ['name' => 'AI engagement guard']],
                ['ai_reply', []],
                ['approval', ['mode' => 'low_confidence']],
                ['send_response', []],
                ['end', []],
            ]),
            'webhook_draft' => self::linear([
                ['trigger_webhook', ['name' => 'Webhook to publishing draft']],
                ['condition', ['field' => 'caption', 'operator' => 'exists']],
                ['create_draft', []],
                ['approval', []],
                ['end', []],
            ]),
            'webhook_ai_review' => self::linear([
                ['trigger_webhook', ['name' => 'Webhook to AI review']],
                ['ai_write', []], ['approval', []], ['create_draft', []], ['end', []],
            ]),
            'comment_triage' => self::linear([
                ['trigger_social_engagement', ['name' => 'Comment triage']],
                ['condition', []], ['notify_team', []], ['approval', []], ['end', []],
            ]),
            'dm_assist' => self::linear([
                ['trigger_social_engagement', ['name' => 'DM assistant with approval']],
                ['ai_reply', []], ['approval', []], ['send_response', []], ['end', []],
            ]),
            default => self::linear([
                ['trigger_google_drive', ['name' => 'Drive to scheduled post']],
                ['import_drive_file', []],
                ['ai_write', []],
                ['create_draft', []],
                ['approval', []],
                ['schedule_post', []],
                ['end', []],
            ]),
        };
    }

    public static function sanitize(array $graph): array
    {
        $nodes = collect((array) ($graph['nodes'] ?? []))
            ->filter(fn ($node): bool => is_array($node))
            ->take(80)
            ->map(function (array $node): ?array {
                $type = (string) ($node['type'] ?? '');
                if (! AutomationWorkflowNodeCatalog::has($type)) {
                    return null;
                }

                $id = Str::limit(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($node['id'] ?? '')), 100, '');
                if ($id === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'type' => $type,
                    'x' => max(0, min(7800, (int) ($node['x'] ?? 0))),
                    'y' => max(0, min(7800, (int) ($node['y'] ?? 0))),
                    'pinned' => (bool) ($node['pinned'] ?? false),
                    'config' => AutomationWorkflowNodeCatalog::sanitizeConfig($type, (array) ($node['config'] ?? [])),
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        $ids = $nodes->pluck('id')->all();
        $edges = collect((array) ($graph['edges'] ?? []))
            ->filter(fn ($edge): bool => is_array($edge))
            ->take(160)
            ->map(fn (array $edge): array => [
                'from' => (string) ($edge['from'] ?? ''),
                'to' => (string) ($edge['to'] ?? ''),
                'branch' => in_array((string) ($edge['branch'] ?? ''), ['yes', 'no'], true) ? (string) $edge['branch'] : '',
            ])
            ->filter(fn (array $edge): bool => $edge['from'] !== $edge['to'] && in_array($edge['from'], $ids, true) && in_array($edge['to'], $ids, true))
            ->unique(fn (array $edge): string => $edge['from'].'|'.$edge['to'].'|'.$edge['branch'])
            ->values();

        return ['version' => 1, 'nodes' => $nodes->all(), 'edges' => $edges->all()];
    }

    public static function issues(array $graph): array
    {
        $graph = self::sanitize($graph);
        $nodes = collect($graph['nodes']);
        $edges = collect($graph['edges']);
        $issues = [];
        $triggers = $nodes->filter(fn (array $node): bool => AutomationWorkflowNodeCatalog::isTrigger($node['type']));

        if ($triggers->count() !== 1) {
            $issues[] = __('A workflow needs exactly one trigger.');
        }
        if ($nodes->where('type', 'end')->isEmpty()) {
            $issues[] = __('Add an End workflow node.');
        }

        foreach ($nodes as $node) {
            $incoming = $edges->where('to', $node['id'])->count();
            $outgoing = $edges->where('from', $node['id'])->count();
            if (AutomationWorkflowNodeCatalog::isTrigger($node['type']) && $incoming > 0) {
                $issues[] = __('The trigger cannot have an incoming connection.');
            }
            if (! AutomationWorkflowNodeCatalog::isTrigger($node['type']) && $incoming < 1) {
                $issues[] = __('Every step after the trigger needs an incoming connection.');
            }
            if (! AutomationWorkflowNodeCatalog::isTrigger($node['type']) && $incoming > 1) {
                $issues[] = __('Each step can have only one incoming path. Add a separate workflow instead of merging branches.');
            }
            if ($node['type'] !== 'end' && $outgoing < 1) {
                $issues[] = __('Every non-final step needs an outgoing connection.');
            }
            if ($node['type'] === 'end' && $outgoing > 0) {
                $issues[] = __('End workflow cannot connect to another step.');
            }

            $nodeEdges = $edges->where('from', $node['id'])->values();
            if ($node['type'] === 'condition') {
                $branches = $nodeEdges->pluck('branch')->map(fn ($branch): string => (string) $branch);
                if ($nodeEdges->count() > 2 || $branches->duplicates()->isNotEmpty()) {
                    $issues[] = __('A Filter can have at most one Yes path and one No path.');
                }
                if ($nodeEdges->count() > 1
                    && ($branches->sort()->values()->all() !== ['no', 'yes'])) {
                    $issues[] = __('A Filter with two outgoing paths must label them Yes and No.');
                }
            } elseif ($outgoing > 1) {
                $issues[] = __('Only a Filter can split into multiple outgoing paths.');
            } elseif ($nodeEdges->contains(fn (array $edge): bool => (string) ($edge['branch'] ?? '') !== '')) {
                $issues[] = __('Only Filter connections can use Yes or No branch labels.');
            }
        }

        if ($triggers->count() === 1) {
            $reachable = self::reachableIds((string) $triggers->first()['id'], $edges);
            if ($nodes->pluck('id')->diff($reachable)->isNotEmpty()) {
                $issues[] = __('Connect every node to the trigger path.');
            }

            if (self::hasCycle((string) $triggers->first()['id'], $edges)) {
                $issues[] = __('Workflow paths cannot contain a cycle. Use a new trigger event instead of looping nodes.');
            }
        }

        $trigger = $triggers->first();
        if (is_array($trigger) && trim((string) data_get($trigger, 'config.name')) === '') {
            $issues[] = __('Give this workflow a name.');
        }

        if ($nodes->contains(fn (array $node): bool => in_array($node['type'], ['schedule_post', 'send_response'], true))) {
            $hasApproval = $nodes->contains(fn (array $node): bool => $node['type'] === 'approval');
            if (! $hasApproval) {
                $issues[] = __('External publishing and response actions require an Approval gate.');
            } elseif ($triggers->count() === 1 && ! self::approvalDominatesExternalActions(
                (string) $triggers->first()['id'],
                $nodes->keyBy('id')->all(),
                $edges->all(),
            )) {
                $issues[] = __('Every path to scheduling or sending a response must pass through an Approval gate first.');
            }
        }

        foreach ($nodes->whereIn('type', ['approval', 'schedule_post']) as $action) {
            $cursor = $action['id'];
            $seen = [];
            $hasDraft = false;
            while ($cursor !== '' && ! in_array($cursor, $seen, true)) {
                $seen[] = $cursor;
                $parent = $edges->firstWhere('to', $cursor);
                $cursor = (string) ($parent['from'] ?? '');
                $upstream = $nodes->firstWhere('id', $cursor);
                if (($upstream['type'] ?? '') === 'create_draft') { $hasDraft = true; break; }
            }
            if (! $hasDraft) {
                $issues[] = __('Place Create publishing draft before approval and scheduling on the same path.');
            }
        }

        $issues = [...$issues, ...self::configurationIssues($graph)];

        return array_values(array_unique($issues));
    }

    public static function configurationIssues(array $graph): array
    {
        $graph = self::sanitize($graph);
        $nodes = collect($graph['nodes']);
        $trigger = $nodes->first(fn (array $node): bool => AutomationWorkflowNodeCatalog::isTrigger($node['type']));
        $triggerType = (string) ($trigger['type'] ?? '');
        $issues = [];

        foreach ($nodes as $node) {
            $config = (array) ($node['config'] ?? []);

            match ($node['type']) {
                'trigger_google_drive' => self::appendIssues($issues, [
                    empty($config['connection_id']) ? __('Choose a persistent Google Drive workspace connection.') : null,
                    empty($config['folder_id']) ? __('Paste the Google Drive folder link to watch.') : null,
                    empty(array_intersect(explode(',', (string) ($config['file_types'] ?? '')), ['image', 'video', 'document'])) ? __('Choose at least one accepted Drive file type.') : null,
                ]),
                'trigger_social_engagement' => self::appendIssues($issues, [
                    empty($config['account_ids']) ? __('Choose at least one connected social account.') : null,
                    empty($config['interaction_types']) ? __('Choose at least one social interaction type.') : null,
                ]),
                'trigger_webhook' => self::appendIssues($issues, [
                    empty($config['event_key']) ? __('Choose the incoming webhook event.') : null,
                ]),
                'condition' => self::appendIssues($issues, [
                    empty($config['field']) ? __('Choose the information the Filter should check.') : null,
                    ($config['operator'] ?? 'contains') !== 'exists' && trim((string) ($config['value'] ?? '')) === '' ? __('Enter the value the Filter should compare with.') : null,
                ]),
                'ai_write', 'ai_reply' => self::appendIssues($issues, [
                    trim((string) ($config['instruction'] ?? '')) === '' ? __('Tell the AI step what it should create.') : null,
                ]),
                'approval' => self::appendIssues($issues, [
                    ($config['mode'] ?? 'always') === 'low_confidence' && ! $nodes->contains(fn (array $candidate): bool => in_array($candidate['type'], ['ai_write', 'ai_reply'], true)) ? __('Low-confidence approval needs an AI step in this workflow.') : null,
                ]),
                'create_draft' => self::appendIssues($issues, [
                    empty($config['account_ids']) ? __('Choose at least one publishing account for Create publishing draft.') : null,
                ]),
                'schedule_post' => self::appendIssues($issues, [
                    ($config['strategy'] ?? '') === 'fixed_time' && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($config['fixed_time'] ?? '')) ? __('Choose a valid daily scheduling time.') : null,
                ]),
                'notify_team' => self::appendIssues($issues, [
                    trim((string) ($config['message'] ?? '')) === '' ? __('Enter the workspace notification message.') : null,
                ]),
                default => null,
            };
        }

        if ($nodes->where('type', 'import_drive_file')->isNotEmpty() && $triggerType !== 'trigger_google_drive') {
            $issues[] = __('Import to Files requires a Google Drive trigger.');
        }
        if ($nodes->contains(fn (array $node): bool => in_array($node['type'], ['ai_reply', 'send_response'], true)) && $triggerType !== 'trigger_social_engagement') {
            $issues[] = __('AI response and Reply, comment or DM require a Social interaction trigger.');
        }
        if ($nodes->where('type', 'schedule_post')->isNotEmpty() && $nodes->where('type', 'create_draft')->isEmpty()) {
            $issues[] = __('Schedule after approval requires a Create publishing draft step.');
        }

        return array_values(array_unique($issues));
    }

    public static function runtimeNotices(array $graph): array
    {
        $catalog = AutomationWorkflowNodeCatalog::definitions();
        $handlers = app(AutomationWorkflowHandlerRegistry::class);

        return collect(self::sanitize($graph)['nodes'])
            ->map(function (array $node) use ($catalog, $handlers): ?string {
                if ($handlers->supports((string) $node['type'])) {
                    return null;
                }

                $runtime = (string) data_get($catalog, $node['type'].'.runtime', 'engine');

                return match ($runtime) {
                    'connection' => __(':node needs a persistent Google Drive workspace connection.', ['node' => data_get($catalog, $node['type'].'.label')]),
                    'provider' => __(':node needs a provider-specific inbound and reply connector.', ['node' => data_get($catalog, $node['type'].'.label')]),
                    'engine' => __(':node does not have an available runtime handler.', ['node' => data_get($catalog, $node['type'].'.label')]),
                    default => null,
                };
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function name(array $graph): string
    {
        $trigger = collect(self::sanitize($graph)['nodes'])->first(fn (array $node): bool => AutomationWorkflowNodeCatalog::isTrigger($node['type']));

        return Str::limit(trim((string) data_get($trigger, 'config.name', __('Untitled workflow'))), 160, '');
    }

    public static function triggerKey(array $graph): string
    {
        $trigger = collect(self::sanitize($graph)['nodes'])->first(fn (array $node): bool => AutomationWorkflowNodeCatalog::isTrigger($node['type']));

        return (string) ($trigger['type'] ?? 'manual');
    }

    private static function linear(array $steps): array
    {
        $originX = 1260;
        $originY = 1780;
        $nodes = [];
        foreach ($steps as $index => [$type, $config]) {
            $nodes[] = [
                'id' => $type.'-'.Str::lower(Str::random(8)),
                'type' => $type,
                'x' => $originX + ($index * 300),
                'y' => $originY + (($index % 2) * 14),
                'pinned' => false,
                'config' => [...AutomationWorkflowNodeCatalog::defaults($type), ...(array) $config],
            ];
        }

        $edges = [];
        foreach ($nodes as $index => $node) {
            if (isset($nodes[$index + 1])) {
                $edges[] = ['from' => $node['id'], 'to' => $nodes[$index + 1]['id'], 'branch' => ''];
            }
        }

        return ['version' => 1, 'nodes' => $nodes, 'edges' => $edges];
    }

    private static function reachableIds(string $start, $edges): array
    {
        $seen = [];
        $queue = [$start];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($edges->where('from', $id) as $edge) {
                $queue[] = (string) $edge['to'];
            }
        }

        return array_keys($seen);
    }

    private static function hasCycle(string $start, $edges): bool
    {
        $adjacency = collect($edges)->groupBy('from');
        $visiting = [];
        $visited = [];

        $visit = function (string $nodeId) use (&$visit, &$visiting, &$visited, $adjacency): bool {
            if (isset($visiting[$nodeId])) {
                return true;
            }
            if (isset($visited[$nodeId])) {
                return false;
            }

            $visiting[$nodeId] = true;
            foreach ($adjacency->get($nodeId, collect()) as $edge) {
                if ($visit((string) $edge['to'])) {
                    return true;
                }
            }
            unset($visiting[$nodeId]);
            $visited[$nodeId] = true;

            return false;
        };

        return $visit($start);
    }

    /**
     * Reject any trigger-to-external-action route that can bypass review.
     * The visited key includes approval state so converging safe/unsafe paths
     * cannot hide one another during traversal.
     */
    private static function approvalDominatesExternalActions(string $start, array $nodesById, array $edges): bool
    {
        $adjacency = collect($edges)->groupBy('from');
        $queue = [[$start, false]];
        $visited = [];

        while ($queue !== []) {
            [$nodeId, $approved] = array_shift($queue);
            $node = $nodesById[$nodeId] ?? null;
            if (! is_array($node)) {
                continue;
            }

            $approved = $approved || (string) ($node['type'] ?? '') === 'approval';
            $stateKey = $nodeId.'|'.($approved ? '1' : '0');
            if (isset($visited[$stateKey])) {
                continue;
            }
            $visited[$stateKey] = true;

            if (in_array((string) ($node['type'] ?? ''), ['schedule_post', 'send_response'], true) && ! $approved) {
                return false;
            }

            foreach ($adjacency->get($nodeId, collect()) as $edge) {
                $queue[] = [(string) $edge['to'], $approved];
            }
        }

        return true;
    }

    private static function appendIssues(array &$issues, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $issues[] = $candidate;
            }
        }
    }
}
