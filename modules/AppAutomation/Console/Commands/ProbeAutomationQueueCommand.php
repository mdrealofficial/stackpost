<?php

namespace Modules\AppAutomation\Console\Commands;

use Illuminate\Console\Command;
use Modules\AppAutomation\Services\AutomationQueueReadiness;

class ProbeAutomationQueueCommand extends Command
{
    protected $signature = 'automation:queue-heartbeat';

    protected $description = 'Queue a heartbeat that proves the dedicated Automation worker is processing jobs.';

    public function handle(AutomationQueueReadiness $queue): int
    {
        $issues = $queue->configurationIssues();
        if ($issues !== []) {
            foreach ($issues as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $queue->enqueueHeartbeat();
        $this->info(__('Automation heartbeat queued on :connection/:queue.', [
            'connection' => $queue->connectionName(),
            'queue' => $queue->queueName(),
        ]));
        $this->line(__('Worker command: :command', ['command' => $queue->workerCommand()]));

        return self::SUCCESS;
    }
}
