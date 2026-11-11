<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\AppAutomation\Jobs\RecordAutomationQueueHeartbeat;
use RuntimeException;
use Throwable;

class AutomationQueueReadiness
{
    public const WORKER_BACKED_DRIVERS = ['database', 'redis', 'sqs', 'beanstalkd'];

    public function connectionName(): string
    {
        return trim((string) config('modules.appautomation.runtime.connection', 'automation')) ?: 'automation';
    }

    public function queueName(): string
    {
        return trim((string) config('modules.appautomation.runtime.queue', 'automation')) ?: 'automation';
    }

    public function driver(): string
    {
        return trim((string) config('queue.connections.'.$this->connectionName().'.driver', ''));
    }

    /**
     * @return array<int, string>
     */
    public function configurationIssues(): array
    {
        $connection = $this->connectionName();
        $queue = $this->queueName();
        $driver = $this->driver();
        $issues = [];

        if (! is_array(config('queue.connections.'.$connection))) {
            return [__('The dedicated Automation queue connection is not configured.')];
        }
        if (! in_array($driver, self::WORKER_BACKED_DRIVERS, true)) {
            $issues[] = __('The Automation queue must use a worker-backed database, Redis, SQS, or Beanstalkd driver; :driver is not safe.', [
                'driver' => $driver !== '' ? $driver : __('no driver'),
            ]);
        }
        if ($queue === '' || $queue === 'default') {
            $issues[] = __('Choose a dedicated queue name for Automation instead of the default queue.');
        }

        if ($driver === 'database') {
            $table = trim((string) config('queue.connections.'.$connection.'.table', 'jobs')) ?: 'jobs';
            $databaseConnection = config('queue.connections.'.$connection.'.connection');
            try {
                $schema = $databaseConnection ? Schema::connection((string) $databaseConnection) : Schema::getFacadeRoot();
                if (! $schema->hasTable($table)) {
                    $issues[] = __('The Automation queue table :table does not exist.', ['table' => $table]);
                }
            } catch (Throwable) {
                $issues[] = __('The Automation database queue cannot be reached.');
            }
        }

        $cacheDriver = trim((string) config('cache.stores.'.config('cache.default').'.driver', ''));
        if (in_array($cacheDriver, ['array', 'null'], true)) {
            $issues[] = __('Use a shared cache store so the Automation worker heartbeat is visible to web requests.');
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<int, string>
     */
    public function issues(bool $requireRecentWorker = true): array
    {
        $issues = $this->configurationIssues();
        if ($issues === [] && $requireRecentWorker && ! $this->workerIsRecent()) {
            $issues[] = __('The dedicated Automation queue worker has not reported a recent heartbeat. Start :command.', [
                'command' => $this->workerCommand(),
            ]);
        }

        return $issues;
    }

    public function ready(): bool
    {
        return $this->issues() === [];
    }

    public function assertConfigured(): void
    {
        $issues = $this->configurationIssues();
        if ($issues !== []) {
            throw new RuntimeException((string) $issues[0]);
        }
    }

    public function assertWorkerExecution(?string $connectionName): void
    {
        $this->assertConfigured();
        if (! is_string($connectionName) || $connectionName !== $this->connectionName()) {
            throw new RuntimeException(__('Automation jobs can only execute on the dedicated :connection queue connection.', [
                'connection' => $this->connectionName(),
            ]));
        }
    }

    public function enqueueHeartbeat(): void
    {
        $this->assertConfigured();
        RecordAutomationQueueHeartbeat::dispatch()
            ->onConnection($this->connectionName())
            ->onQueue($this->queueName());
    }

    public function recordHeartbeat(): void
    {
        Cache::put($this->heartbeatKey(), [
            'processed_at' => now()->toIso8601String(),
            'connection' => $this->connectionName(),
            'queue' => $this->queueName(),
        ], now()->addMinutes(10));
    }

    public function lastHeartbeatAt(): ?Carbon
    {
        $value = data_get(Cache::get($this->heartbeatKey()), 'processed_at');
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function workerIsRecent(): bool
    {
        $last = $this->lastHeartbeatAt();
        $maxAge = max(30, (int) config('modules.appautomation.runtime.heartbeat_max_age_seconds', 180));

        return $last && $last->greaterThanOrEqualTo(now()->subSeconds($maxAge));
    }

    public function status(): array
    {
        $configured = $this->configurationIssues() === [];
        $last = $this->lastHeartbeatAt();

        return [
            'ready' => $configured && $this->workerIsRecent(),
            'configured' => $configured,
            'connection' => $this->connectionName(),
            'queue' => $this->queueName(),
            'driver' => $this->driver(),
            'last_heartbeat_at' => $last?->toIso8601String(),
            'worker_command' => $this->workerCommand(),
            'issues' => $this->issues(),
        ];
    }

    public function workerCommand(): string
    {
        return 'php artisan queue:work '.$this->connectionName().' --queue='.$this->queueName().' --sleep=1 --tries=1 --timeout=300';
    }

    protected function heartbeatKey(): string
    {
        return 'appautomation:queue-heartbeat:'.sha1($this->connectionName().'|'.$this->queueName());
    }
}
