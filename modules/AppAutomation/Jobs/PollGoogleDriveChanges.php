<?php

namespace Modules\AppAutomation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\AppAutomation\Models\AutomationDriveChannel;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveWatchService;
use Modules\AppAutomation\Services\AutomationQueueReadiness;

class PollGoogleDriveChanges implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 240;

    public int $uniqueFor = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $channelId)
    {
        $this->onConnection(trim((string) config('modules.appautomation.runtime.connection', 'automation')) ?: 'automation');
        $this->onQueue(trim((string) config('modules.appautomation.runtime.queue', 'automation')) ?: 'automation');
    }

    public function uniqueId(): string
    {
        return 'automation-google-drive-channel:'.$this->channelId;
    }

    public function backoff(): array
    {
        return [15, 60, 180, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(15)->expireAfter(300)];
    }

    public function handle(GoogleDriveWatchService $watcher, AutomationQueueReadiness $queue): void
    {
        $queue->assertWorkerExecution($this->job?->getConnectionName());
        $channel = AutomationDriveChannel::query()->find($this->channelId);
        if (! $channel) {
            return;
        }

        $result = $watcher->pollChannel($channel);
        if ($result['has_more'] ?? false) {
            $queue->assertConfigured();
            self::dispatch($channel->id)
                ->onConnection($queue->connectionName())
                ->onQueue($queue->queueName())
                ->delay(now()->addSeconds(2));
        }
    }
}
