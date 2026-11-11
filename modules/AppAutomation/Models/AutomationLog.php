<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    protected $table = 'automation_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(AutomationApiKey::class, 'api_key_id');
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(AutomationWebhook::class, 'webhook_id');
    }
}
