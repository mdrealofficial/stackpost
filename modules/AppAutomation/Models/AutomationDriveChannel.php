<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationDriveChannel extends Model
{
    protected $table = 'automation_drive_channels';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_notification_at' => 'datetime',
            'last_polled_at' => 'datetime',
            'last_renewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AutomationConnection::class, 'connection_id');
    }
}
