<?php

namespace Modules\AdminNotifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class NotificationManualState extends Model
{
    protected $table = 'notification_manual_states';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'notification_manual_id' => 'integer',
            'user_id' => 'integer',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(NotificationManual::class, 'notification_manual_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
