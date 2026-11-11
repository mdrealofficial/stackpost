<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAutomation\Exceptions\AutomationNodeRetryAtException;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Models\AutomationWorkflowStepRun;
use Modules\AppAutomation\Support\AutomationWorkflowNodeCatalog;
use Modules\AppCredits\Models\CreditReservation;
use RuntimeException;
use Throwable;

class AutomationWorkflowCreditRunner
{
    public function preview(User $creditOwner, ?AutomationWorkflow $workflow = null): array
    {
        $action = AutomationWorkflowNodeCatalog::creditAction('ai_write') ?? 'ai_studio_generate_captions';
        $summary = credit_service()->summary($creditOwner);
        $usedByNode = [];

        if ($workflow
            && Schema::hasTable('automation_workflow_runs')
            && Schema::hasTable('automation_workflow_step_runs')) {
            $usedByNode = AutomationWorkflowStepRun::query()
                ->join('automation_workflow_runs', 'automation_workflow_runs.id', '=', 'automation_workflow_step_runs.run_id')
                ->where('automation_workflow_runs.workflow_id', $workflow->id)
                ->where('automation_workflow_step_runs.node_type', 'ai_write')
                ->where('automation_workflow_step_runs.created_at', '>=', now()->startOfMonth())
                ->groupBy('automation_workflow_step_runs.node_id')
                ->selectRaw('automation_workflow_step_runs.node_id, SUM(automation_workflow_step_runs.credits_used) as aggregate')
                ->pluck('aggregate', 'automation_workflow_step_runs.node_id')
                ->map(fn ($value): int => (int) $value)
                ->all();
        }

        return [
            'unit_cost' => credit_service()->costFor($creditOwner, $action, 1),
            'remaining' => $summary['remaining'] ?? null,
            'unlimited' => (bool) ($summary['unlimited'] ?? false),
            'low_balance' => (bool) ($summary['low_balance'] ?? false),
            'used_by_node' => $usedByNode,
            'runner_enabled' => app(AutomationWorkflowHandlerRegistry::class)->supports('ai_write')
                && app(AutomationQueueReadiness::class)->ready(),
        ];
    }

    /**
     * Execute a persisted AI workflow step with success-only, idempotent credit charging.
     *
     * A failed step must be retried as a new AutomationWorkflowStepRun attempt. Replaying
     * the same committed step returns its cached result without calling the provider again.
     */
    public function run(
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        callable $callback,
    ): array {
        if ((int) $step->run_id !== (int) $run->id) {
            throw new RuntimeException(__('The workflow step does not belong to this run.'));
        }

        $action = AutomationWorkflowNodeCatalog::creditAction((string) $step->node_type);
        if (! $action) {
            throw new RuntimeException(__('This workflow step does not define a credit action.'));
        }

        $creditOwner = User::query()->findOrFail((int) $run->owner_user_id);
        $workflow = AutomationWorkflow::query()->findOrFail((int) $run->workflow_id);
        $requestedBy = (int) ($run->requested_by_user_id
            ?: $workflow->activated_by_user_id
            ?: $workflow->created_by_user_id
            ?: $run->owner_user_id);
        $logicalKey = $this->logicalKey($run, $step);
        $prepared = $this->prepareReservation(
            $workflow,
            $run,
            $step,
            $creditOwner,
            $action,
            $requestedBy,
            $logicalKey,
        );
        /** @var CreditReservation $reservation */
        $reservation = $prepared['reservation'];

        if (($prepared['replayed'] ?? false) === true) {
            $result = $this->cachedResult($reservation);
            $this->applyReservationCredits($run, $step, $reservation, true);

            return $this->withCreditResult($result, $reservation->refresh(), true, $creditOwner);
        }

        $requestKey = (string) $reservation->idempotency_key;

        try {
            $result = (array) $callback();
            $charged = $this->hasBillableAiOutput($result);

            if ($charged) {
                credit_service()->commitReservation($creditOwner, $requestKey, [
                    'feature' => 'automation.ai-content-writer',
                    'metadata' => [
                        'result_cache' => $result,
                        'charge_mode' => 'successful_ai_output',
                    ],
                ]);
            } else {
                credit_service()->refundReservation($creditOwner, $requestKey, 'ai_fallback', [
                    'result_source' => (string) ($result['source'] ?? 'fallback'),
                ]);
            }

            $this->applyReservationCredits($run, $step, $reservation->refresh(), false);

            return $this->withCreditResult($result, $reservation->refresh(), false, $creditOwner, $charged);
        } catch (Throwable $exception) {
            $current = credit_service()->reservation($creditOwner, $requestKey);
            if ($current?->status === CreditReservation::STATUS_RESERVED) {
                credit_service()->refundReservation($creditOwner, $requestKey, 'action_failed', [
                    'error' => Str::limit($exception->getMessage(), 1000, ''),
                ]);
            }

            if ($current) {
                $this->applyReservationCredits($run, $step, $current->refresh(), false);
            }

            throw $exception;
        }
    }

    /**
     * Fence the external AI effect by the logical run + node, not by a queue
     * delivery or step attempt. A recovered lease can therefore replay a
     * committed result, while a genuinely refunded attempt gets a new key.
     *
     * @return array{reservation: CreditReservation, replayed: bool}
     */
    protected function prepareReservation(
        AutomationWorkflow $workflow,
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        User $creditOwner,
        string $action,
        int $requestedBy,
        string $logicalKey,
    ): array {
        return DB::transaction(function () use ($workflow, $run, $step, $creditOwner, $action, $requestedBy, $logicalKey): array {
            AutomationWorkflow::query()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $lockedRun = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $lockedStep = AutomationWorkflowStepRun::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            $latestStep = AutomationWorkflowStepRun::query()
                ->where('run_id', $run->id)
                ->where('node_id', (string) $step->node_id)
                ->orderByDesc('attempt')
                ->lockForUpdate()
                ->first();
            $this->assertCurrentFence($lockedRun, $lockedStep, $latestStep, $run, $step);
            User::query()->whereKey($creditOwner->id)->lockForUpdate()->firstOrFail();

            $reservations = $this->logicalReservations($creditOwner, $run, $step, true);
            $committed = $reservations->firstWhere('status', CreditReservation::STATUS_COMMITTED);
            if ($committed instanceof CreditReservation) {
                return ['reservation' => $committed, 'replayed' => true];
            }

            $active = $reservations->first(function (CreditReservation $candidate): bool {
                return $candidate->status === CreditReservation::STATUS_RESERVED
                    && (! $candidate->expires_at || $candidate->expires_at->isFuture());
            });
            if ($active instanceof CreditReservation) {
                throw new AutomationNodeRetryAtException(
                    __('This workflow node already has an active AI request. It will be checked again without starting a duplicate request.'),
                    ($active->expires_at ?: now()->addMinutes(5))->copy()->addSecond(),
                );
            }

            foreach ($reservations->where('status', CreditReservation::STATUS_RESERVED) as $expired) {
                $expired->forceFill([
                    'status' => CreditReservation::STATUS_REFUNDED,
                    'refunded_at' => now(),
                    'metadata' => [
                        ...(array) $expired->metadata,
                        'refund_reason' => 'expired_before_recovery',
                    ],
                ])->save();
                $this->clearReservationCredits($expired);
            }

            $requestKey = $logicalKey.':try:'.max(1, (int) $lockedStep->attempt);
            $sameAttempt = $reservations->firstWhere('idempotency_key', $requestKey);
            if ($sameAttempt instanceof CreditReservation) {
                throw new RuntimeException(__('This refunded AI attempt cannot be run again. The workflow runner must create a new retry attempt.'));
            }

            $unitCost = credit_service()->costFor($creditOwner, $action, 1);
            $this->assertMonthlyLimit($workflow, $step, $unitCost);
            $reservation = credit_service()->reserve($creditOwner, $action, $requestKey, [
                'unit_cost' => $unitCost,
                'ttl_minutes' => 60,
                'metadata' => [
                    'workflow_id' => (int) $workflow->id,
                    'workflow_revision' => (int) $run->workflow_revision,
                    'run_id' => (int) $run->id,
                    'step_run_id' => (int) $step->id,
                    'node_id' => (string) $step->node_id,
                    'node_type' => (string) $step->node_type,
                    'attempt' => (int) $step->attempt,
                    'team_id' => $run->team_id ? (int) $run->team_id : null,
                    'requested_by_user_id' => $requestedBy,
                    'charge_mode' => 'successful_ai_output',
                    'logical_effect_key' => $logicalKey,
                ],
            ]);

            $lockedStep->forceFill([
                'credits_reserved' => (int) $reservation->amount,
                'credits_used' => 0,
            ])->save();
            $this->syncRunCreditTotals($lockedRun);

            return ['reservation' => $reservation, 'replayed' => false];
        });
    }

    protected function assertMonthlyLimit(AutomationWorkflow $workflow, AutomationWorkflowStepRun $step, int $nextCost): void
    {
        $node = collect((array) data_get($workflow->definition, 'nodes', []))
            ->first(fn ($candidate): bool => is_array($candidate) && (string) ($candidate['id'] ?? '') === (string) $step->node_id);
        $config = AutomationWorkflowNodeCatalog::sanitizeConfig('ai_write', (array) data_get($node, 'config', []));
        $limit = max(1, (int) ($config['monthly_credit_limit'] ?? 100));
        $committedOrReserved = (int) AutomationWorkflowStepRun::query()
            ->join('automation_workflow_runs', 'automation_workflow_runs.id', '=', 'automation_workflow_step_runs.run_id')
            ->where('automation_workflow_runs.workflow_id', $workflow->id)
            ->where('automation_workflow_step_runs.node_id', (string) $step->node_id)
            ->where('automation_workflow_step_runs.created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(automation_workflow_step_runs.credits_used + automation_workflow_step_runs.credits_reserved), 0) as aggregate')
            ->value('aggregate');

        if ($committedOrReserved + $nextCost > $limit) {
            throw new RuntimeException(__('This AI step reached its monthly credit limit of :limit credits.', ['limit' => number_format($limit)]));
        }
    }

    protected function hasBillableAiOutput(array $result): bool
    {
        if (($result['source'] ?? null) !== 'ai') {
            return false;
        }

        if (filled($result['caption'] ?? null)) {
            return true;
        }

        return collect((array) ($result['variants'] ?? $result['items'] ?? []))
            ->contains(fn ($item): bool => is_array($item) && filled($item['caption'] ?? $item['content'] ?? null));
    }

    protected function assertCurrentFence(
        AutomationWorkflowRun $lockedRun,
        AutomationWorkflowStepRun $lockedStep,
        ?AutomationWorkflowStepRun $latestStep,
        AutomationWorkflowRun $claimedRun,
        AutomationWorkflowStepRun $claimedStep,
    ): void {
        if ((string) $lockedRun->status !== AutomationWorkflowRun::STATUS_RUNNING
            || blank($claimedRun->claim_token)
            || (string) $lockedRun->claim_token !== (string) $claimedRun->claim_token
            || (string) $lockedStep->status !== AutomationWorkflowStepRun::STATUS_RUNNING
            || blank($claimedStep->claim_token)
            || (string) $lockedStep->claim_token !== (string) $claimedStep->claim_token
            || (int) $latestStep?->id !== (int) $lockedStep->id) {
            throw new RuntimeException(__('This worker no longer owns the AI workflow node lease.'));
        }
    }

    protected function cachedResult(CreditReservation $reservation): array
    {
        $cached = (array) data_get($reservation->metadata, 'result_cache', []);
        if ($cached !== []) {
            return $cached;
        }

        $originStepId = (int) data_get($reservation->metadata, 'step_run_id', 0);
        $originStep = $originStepId > 0
            ? AutomationWorkflowStepRun::query()->find($originStepId)
            : null;
        $originOutput = (array) $originStep?->output;
        if ($originOutput !== []) {
            unset($originOutput['_runtime']);

            return $originOutput;
        }

        throw new RuntimeException(__('A committed AI charge exists for this workflow node, but its cached output is unavailable. The provider call was not repeated.'));
    }

    protected function applyReservationCredits(
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $currentStep,
        CreditReservation $reservation,
        bool $replayed,
    ): void
    {
        DB::transaction(function () use ($run, $currentStep, $reservation, $replayed): void {
            $lockedRun = AutomationWorkflowRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $originStepId = (int) data_get($reservation->metadata, 'step_run_id', 0);
            $charged = $reservation->status === CreditReservation::STATUS_COMMITTED;

            if ($originStepId > 0) {
                $origin = AutomationWorkflowStepRun::query()->whereKey($originStepId)->lockForUpdate()->first();
                $origin?->forceFill([
                    'credits_reserved' => 0,
                    'credits_used' => $charged ? (int) $reservation->amount : 0,
                ])->save();
            }

            if ($replayed && (int) $currentStep->id !== $originStepId) {
                AutomationWorkflowStepRun::query()->whereKey($currentStep->id)->lockForUpdate()->first()?->forceFill([
                    'credits_reserved' => 0,
                    'credits_used' => 0,
                ])->save();
            } elseif ((int) $currentStep->id === $originStepId) {
                AutomationWorkflowStepRun::query()->whereKey($currentStep->id)->lockForUpdate()->first()?->forceFill([
                    'credits_reserved' => 0,
                    'credits_used' => $charged ? (int) $reservation->amount : 0,
                ])->save();
            }

            $this->syncRunCreditTotals($lockedRun);
        });
    }

    protected function clearReservationCredits(CreditReservation $reservation): void
    {
        $stepId = (int) data_get($reservation->metadata, 'step_run_id', 0);
        if ($stepId > 0) {
            AutomationWorkflowStepRun::query()->whereKey($stepId)->update([
                'credits_reserved' => 0,
                'credits_used' => 0,
            ]);
        }
    }

    protected function syncRunCreditTotals(AutomationWorkflowRun $run): void
    {
        $totals = AutomationWorkflowStepRun::query()
            ->where('run_id', $run->id)
            ->selectRaw('COALESCE(SUM(credits_reserved), 0) as reserved, COALESCE(SUM(credits_used), 0) as used')
            ->first();

        $run->forceFill([
            'credits_reserved' => (int) ($totals->reserved ?? 0),
            'credits_used' => (int) ($totals->used ?? 0),
        ])->save();
    }

    protected function logicalKey(AutomationWorkflowRun $run, AutomationWorkflowStepRun $step): string
    {
        return 'automation-node:'.(int) $run->id.':'.sha1((string) $step->node_id);
    }

    /** @return \Illuminate\Support\Collection<int, CreditReservation> */
    protected function logicalReservations(
        User $creditOwner,
        AutomationWorkflowRun $run,
        AutomationWorkflowStepRun $step,
        bool $lock = false,
    ): \Illuminate\Support\Collection {
        $logicalKey = $this->logicalKey($run, $step);
        $legacyKeys = AutomationWorkflowStepRun::query()
            ->where('run_id', $run->id)
            ->where('node_id', (string) $step->node_id)
            ->pluck('id')
            ->map(fn ($id): string => 'automation-step:'.(int) $id)
            ->all();
        $query = CreditReservation::query()
            ->where('user_id', $creditOwner->id)
            ->where(function ($query) use ($logicalKey, $legacyKeys): void {
                $query->where('idempotency_key', 'like', $logicalKey.':try:%');
                if ($legacyKeys !== []) {
                    $query->orWhereIn('idempotency_key', $legacyKeys);
                }
            })
            ->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    protected function withCreditResult(
        array $result,
        CreditReservation $reservation,
        bool $replayed,
        User $creditOwner,
        ?bool $charged = null,
    ): array {
        $charged ??= $reservation->status === CreditReservation::STATUS_COMMITTED;
        $usage = $reservation->usageLog;

        return [
            ...$result,
            '_credit' => [
                'charged' => $charged,
                'cost' => $charged ? (int) $reservation->amount : 0,
                'before' => $usage?->credits_before,
                'after' => $charged ? $usage?->credits_after : (credit_service()->summary($creditOwner)['remaining'] ?? null),
                'replayed' => $replayed,
            ],
        ];
    }
}
