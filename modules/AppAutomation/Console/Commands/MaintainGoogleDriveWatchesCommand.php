<?php

namespace Modules\AppAutomation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\AppAutomation\Jobs\PollGoogleDriveChanges;
use Modules\AppAutomation\Models\AutomationConnection;
use Modules\AppAutomation\Services\AutomationQueueReadiness;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveApiService;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveException;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveWatchService;

class MaintainGoogleDriveWatchesCommand extends Command
{
    protected $signature = 'automation:drive-maintain {--connection=} {--renew-all}';

    protected $description = 'Renew Google Drive notification channels and dispatch pending change polls.';

    public function handle(GoogleDriveWatchService $watcher, AutomationQueueReadiness $queue): int
    {
        $queueIssues = $queue->configurationIssues();
        if ($queueIssues !== []) {
            foreach ($queueIssues as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        if (! Schema::hasTable('automation_connections') || ! Schema::hasTable('automation_drive_channels')) {
            $this->warn('Google Drive automation migrations are not ready.');

            return self::FAILURE;
        }

        $query = AutomationConnection::query()
            ->where('provider', GoogleDriveApiService::PROVIDER)
            ->where('status', 'active')
            ->whereNull('disconnected_at');

        if ($connectionId = (int) $this->option('connection')) {
            $query->whereKey($connectionId);
        }

        $connections = $query->with('driveChannel')->get();
        $failed = 0;

        foreach ($connections as $connection) {
            try {
                $channel = $watcher->ensureChannel($connection, (bool) $this->option('renew-all'));
                if ($channel->status === 'active') {
                    PollGoogleDriveChanges::dispatch($channel->id)
                        ->onConnection($queue->connectionName())
                        ->onQueue($queue->queueName());
                    $this->line("Connection {$connection->id}: watcher active, poll queued.");
                } else {
                    $failed++;
                    $this->warn("Connection {$connection->id}: {$channel->status} - {$channel->last_error}");
                }
            } catch (GoogleDriveException $exception) {
                $failed++;
                $this->error("Connection {$connection->id}: {$exception->getMessage()}");
            }
        }

        if ($connections->isEmpty()) {
            $this->line('No active Google Drive workspace connections found.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
