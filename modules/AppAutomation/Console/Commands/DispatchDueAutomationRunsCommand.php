<?php

namespace Modules\AppAutomation\Console\Commands;

use Illuminate\Console\Command;
use Modules\AppAutomation\Models\AutomationWorkflowRun;
use Modules\AppAutomation\Services\AutomationWorkflowDispatcher;

class DispatchDueAutomationRunsCommand extends Command
{
    protected $signature = 'automation:dispatch-due-runs {--limit=100 : Maximum number of due runs to dispatch}';

    protected $description = 'Dispatch queued, retrying, delayed, and stale leased workflow runs.';

    public function handle(AutomationWorkflowDispatcher $dispatcher): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $leaseSeconds = max(30, (int) config('modules.appautomation.runtime.lease_seconds', 300));

        $runIds = AutomationWorkflowRun::query()
            ->where(function ($query) use ($leaseSeconds): void {
                $query->where(function ($due): void {
                    $due->whereIn('status', [
                        AutomationWorkflowRun::STATUS_QUEUED,
                        AutomationWorkflowRun::STATUS_RETRYING,
                        AutomationWorkflowRun::STATUS_WAITING_DELAY,
                    ])->where(function ($time): void {
                        $time->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                    });
                })->orWhere(function ($stale) use ($leaseSeconds): void {
                    $stale->where('status', AutomationWorkflowRun::STATUS_RUNNING)
                        ->where(function ($heartbeat) use ($leaseSeconds): void {
                            $heartbeat->whereNull('heartbeat_at')
                                ->orWhere('heartbeat_at', '<=', now()->subSeconds($leaseSeconds));
                        });
                });
            })
            ->orderByRaw('COALESCE(next_attempt_at, created_at)')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($runIds as $runId) {
            $dispatcher->enqueue((int) $runId);
        }

        $this->info(__('Dispatched :count due workflow run(s).', ['count' => $runIds->count()]));

        return self::SUCCESS;
    }
}
