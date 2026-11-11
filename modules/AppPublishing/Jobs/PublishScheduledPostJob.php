<?php

namespace Modules\AppPublishing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AppAutomation\Services\AutomationWebhookDispatcher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Services\Publishing\PostPublishService;
use Modules\AppPublishing\Services\Publishing\RecurringPostScheduler;
use Throwable;

class PublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $postId) {}

    public function handle(
        PostPublishService $publisher,
        AutomationWebhookDispatcher $automationWebhooks,
        RecurringPostScheduler $recurringPosts
    ): void
    {
        $post = PublishingPost::query()->find($this->postId);

        if (! $post || (int) $post->status !== PublishingPost::STATUS_SCHEDULED) {
            return;
        }

        $claimed = PublishingPost::query()
            ->whereKey($post->id)
            ->where('status', PublishingPost::STATUS_SCHEDULED)
            ->update([
                'status' => PublishingPost::STATUS_PROCESSING,
                'changed' => now()->timestamp,
            ]);

        if (! $claimed) {
            return;
        }

        $post->refresh();

        try {
            $result = $publisher->publish($post);

            $state = (string) ($result['state'] ?? '');
            $status = $state === 'published'
                ? PublishingPost::STATUS_PUBLISHED
                : PublishingPost::STATUS_FAILED;

            $post->update([
                'status' => $status,
                'result' => [
                    'state' => $state === 'published' ? 'published' : 'failed',
                    'url' => $result['url'] ?? null,
                    'error' => $result['error'] ?? null,
                    'remote_post_id' => $result['remote_post_id'] ?? null,
                    'published_at' => now()->toIso8601String(),
                    'response' => $result['response'] ?? null,
                ],
                'changed' => now()->timestamp,
            ]);

            $post->refresh();

            if ($state === 'published') {
                try {
                    $recurringPosts->createNextAfterSuccessfulPublish($post);
                } catch (Throwable $recurringException) {
                    logger()->warning('publishing.recurring_next_failed', [
                        'post_id' => $post->id,
                        'error' => $recurringException->getMessage(),
                    ]);
                }
            }

            $automationWebhooks->dispatchForPost(
                $state === 'published' ? 'post.published' : 'post.failed',
                $post
            );
        } catch (Throwable $exception) {
            $post->update([
                'status' => PublishingPost::STATUS_FAILED,
                'result' => [
                    'state' => 'failed',
                    'url' => null,
                    'error' => $exception->getMessage(),
                    'remote_post_id' => null,
                    'published_at' => null,
                    'response' => null,
                ],
                'changed' => now()->timestamp,
            ]);

            $post->refresh();
            $automationWebhooks->dispatchForPost('post.failed', $post);

            throw $exception;
        }
    }
}
