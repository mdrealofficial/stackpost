<?php

namespace Modules\AppPublishing\Contracts;

use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Models\PublishingPost;

interface PostPublisher
{
    public function publish(PublishingPost $post, SocialAccount $account): array;
}
