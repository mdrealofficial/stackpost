<?php

namespace Modules\AppRssSchedules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Models\PublishingPost;

class RssScheduleHistory extends Model
{
    protected $table = 'rss_schedule_histories';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'queued_at' => 'integer',
            'published_at' => 'integer',
            'created' => 'integer',
            'changed' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RssSchedule::class, 'schedule_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'account_id');
    }

    public function publishingPost(): BelongsTo
    {
        return $this->belongsTo(PublishingPost::class, 'publishing_post_id');
    }
}
