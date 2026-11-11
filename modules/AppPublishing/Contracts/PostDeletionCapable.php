<?php

namespace Modules\AppPublishing\Contracts;

use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Models\PublishingPost;

interface PostDeletionCapable
{
    public function delete(PublishingPost $post, SocialAccount $account): array;
}
