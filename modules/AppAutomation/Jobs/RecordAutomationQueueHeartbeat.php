<?php

namespace Modules\AppAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AppAutomation\Services\AutomationQueueReadiness;

class RecordAutomationQueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 180;

    public function __construct()
    {
        $this->onConnection(trim((string) config('modules.appautomation.runtime.connection', 'automation')) ?: 'automation');
        $this->onQueue(trim((string) config('modules.appautomation.runtime.queue', 'automation')) ?: 'automation');
    }

    public function handle(AutomationQueueReadiness $queue): void
    {
        $queue->assertWorkerExecution($this->job?->getConnectionName());
        $queue->recordHeartbeat();
    }

    public function uniqueId(): string
    {
        return 'appautomation-worker-heartbeat';
    }
}
