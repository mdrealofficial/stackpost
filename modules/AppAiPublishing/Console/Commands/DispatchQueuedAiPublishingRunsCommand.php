<?php

namespace Modules\AppAiPublishing\Console\Commands;

use Illuminate\Console\Command;
use Modules\AppAiPublishing\Jobs\ProcessAiPublishingRunJob;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppAiPublishing\Support\AiPublishingScheduler;

class DispatchQueuedAiPublishingRunsCommand extends Command
{
    protected $signature = 'ai-publishing:dispatch-queued {--limit=25 : Maximum due schedules to process}';

    protected $description = 'Generate the next due AI content slot for enabled schedules.';

    public function handle(): int
    {
        $processed = 0;
        $failed = 0;
        $limit = max(1, (int) $this->option('limit'));
        foreach (AiPublishingRun::where('status', 'queued')->orderBy('id')->lazyById(100) as $run) {
            try {
                if ($run->isDemo()) {
                    continue;
                }
                if ($run->hasEnded()) {
                    $run->update(['status' => 'completed']);

                    continue;
                }
                $slot = app(AiPublishingScheduler::class)->nextForRun($run);
                if ($slot && $slot->gt(now())) {
                    continue;
                }
                $result = app()->call([new ProcessAiPublishingRunJob($run->id), 'handle']);
                $processed++;
                if (in_array($result['status'], ['error', 'attention'], true)) {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $processed++;
                report($e);
            }
            if ($processed >= $limit) {
                break;
            }
        }
        $this->line("Processed {$processed} schedules; {$failed} need attention.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
