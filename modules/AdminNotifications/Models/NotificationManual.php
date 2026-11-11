<?php

namespace Modules\AdminNotifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminUser\Models\User;

class NotificationManual extends Model
{
    protected $table = 'notification_manual';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'mid');
    }

    public function states(): HasMany
    {
        return $this->hasMany(NotificationManualState::class, 'notification_manual_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipientsCount(): int
    {
        if ($this->is_global) {
            return User::query()->count();
        }

        return (int) ($this->notifications_count ?? $this->notifications()->count());
    }

    public function excerpt(int $limit = 140): string
    {
        $message = trim((string) $this->message);

        if ($message === '') {
            return __('No message');
        }

        return mb_strimwidth($message, 0, $limit, '...');
    }
}
