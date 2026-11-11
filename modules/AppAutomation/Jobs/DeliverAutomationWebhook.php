<?php

namespace Modules\AppAutomation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\AppAutomation\Models\AutomationLog;
use Modules\AppAutomation\Services\AutomationWebhookDispatcher;
use Throwable;

class DeliverAutomationWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 40;
    public bool $failOnTimeout = true;

    public function __construct(public int $logId) {}

    public function backoff(): array { return [30, 120]; }

    public function handle(AutomationWebhookDispatcher $dispatcher): void
    {
        if (!$dispatcher->deliver($this->logId, $this->job?->attempts() ?? 1)) {
            throw new \RuntimeException('Webhook delivery will be retried.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = AutomationLog::find($this->logId);
        if ($log && !in_array($log->status, ['delivered', 'cancelled'], true)) {
            $log->update(['status' => 'failed', 'response' => [...(array) $log->response,
                'error' => $log->response['error'] ?? 'Delivery stopped before completion. You can retry it.', 'next_attempt_at' => null]]);
        }
    }
}
