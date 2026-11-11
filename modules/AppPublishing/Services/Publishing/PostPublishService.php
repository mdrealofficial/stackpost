<?php

namespace Modules\AppPublishing\Services\Publishing;

use Modules\AppPublishing\Contracts\PostDeletionCapable;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppWatermark\Services\PublishingWatermarkProcessor;
use RuntimeException;

class PostPublishService
{
    public function __construct(
        protected PublishingChannelRegistry $publishers,
        protected PublishingWatermarkProcessor $watermarks,
    ) {}

    public function publish(PublishingPost $post): array
    {
        $account = $post->account;

        if (! $account) {
            throw new RuntimeException('The connected channel is no longer available.');
        }

        $publisher = $this->publishers->get((string) $account->provider_key);

        if (! $publisher) {
            return [
                'state' => 'failed',
                'error' => 'Publishing is not supported for this social network yet.',
                'url' => null,
                'remote_post_id' => null,
                'response' => null,
            ];
        }

        $preparedData = $this->watermarks->applyToPostData($post);
        $dataChanged = $preparedData !== (is_array($post->data) ? $post->data : []);

        if ($dataChanged) {
            $post->forceFill(['data' => $preparedData])->save();
            $post->setAttribute('data', $preparedData);
        }

        try {
            return $publisher->publish($post, $account);
        } finally {
            if ($dataChanged) {
                $this->watermarks->cleanupGeneratedMedia($post);
            }
        }
    }

    public function canDeleteRemote(PublishingPost $post): bool
    {
        $account = $post->account;

        if (! $account) {
            return false;
        }

        return $this->publishers->get((string) $account->provider_key) instanceof PostDeletionCapable;
    }

    public function deleteRemote(PublishingPost $post): array
    {
        $account = $post->account;

        if (! $account) {
            throw new RuntimeException('The connected channel is no longer available.');
        }

        $publisher = $this->publishers->get((string) $account->provider_key);

        if (! $publisher instanceof PostDeletionCapable) {
            return [
                'state' => 'failed',
                'error' => 'Remote deletion is not supported for this social network yet.',
            ];
        }

        return $publisher->delete($post, $account);
    }
}
