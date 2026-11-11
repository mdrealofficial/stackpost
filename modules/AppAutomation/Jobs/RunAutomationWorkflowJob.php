<?php

namespace Modules\AppAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AppAutomation\Services\AutomationWorkflowRunner;
use Modules\AppAutomation\Services\AutomationQueueReadiness;

class RunAutomationWorkflowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $runId)
    {
        $this->onConnection(trim((string) config('modules.appautomation.runtime.connection', 'automation')) ?: 'automation');
        $this->onQueue(trim((string) config('modules.appautomation.runtime.queue', 'automation')) ?: 'automation');
    }

    public function handle(AutomationWorkflowRunner $runner, AutomationQueueReadiness $queue): void
    {
        $queue->assertWorkerExecution($this->job?->getConnectionName());
        $runner->execute($this->runId);
    }
}
