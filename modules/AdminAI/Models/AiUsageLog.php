<?php

namespace Modules\AdminAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'capability',
        'model',
        'status',
        'feature',
        'route_name',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'latency_ms',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
