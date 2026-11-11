<?php

namespace Modules\AppPublishing\Console\Commands;

use Illuminate\Console\Command;
use Modules\AppPublishing\Jobs\PublishScheduledPostJob;
use Modules\AppPublishing\Models\PublishingPost;
class DispatchDuePostsCommand extends Command
{
    protected $signature = 'publishing:dispatch-due {--limit=100 : Maximum number of due posts to dispatch per run}';

    protected $description = 'Dispatch scheduled publishing posts that are due for delivery.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $duePosts = PublishingPost::query()
            ->dueToDispatch(now()->timestamp)
            ->orderBy('time_post')
            ->limit($limit)
            ->get(['id']);

        if ($duePosts->isEmpty()) {
            $this->line('No due publishing posts found.');

            return self::SUCCESS;
        }

        foreach ($duePosts as $post) {
            app()->call([
                new PublishScheduledPostJob((int) $post->id),
                'handle',
            ]);
        }

        $this->info(sprintf('Processed %d publishing post(s).', $duePosts->count()));

        return self::SUCCESS;
    }
}
