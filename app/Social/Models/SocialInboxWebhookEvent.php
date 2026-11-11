<?php

namespace App\Social\Models;

use Illuminate\Database\Eloquent\Model;

class SocialInboxWebhookEvent extends Model
{
    protected $table = 'social_inbox_webhook_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
