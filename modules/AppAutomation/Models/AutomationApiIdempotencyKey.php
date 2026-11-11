<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationApiIdempotencyKey extends Model
{
    protected $table = 'automation_api_idempotency_keys';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(AutomationApiKey::class, 'api_key_id');
    }
}
