<?php

namespace Modules\AdminNotifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'mid' => 'integer',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(NotificationManual::class, 'mid');
    }

    public function resolvedTitle(): string
    {
        if ($this->source === 'manual' && filled($this->manual?->title)) {
            return (string) $this->manual->title;
        }

        if (filled($this->title)) {
            return (string) $this->title;
        }

        return __('Notification');
    }

    public function resolvedMessage(): string
    {
        if ($this->source === 'manual' && filled($this->manual?->message)) {
            return (string) $this->manual->message;
        }

        return (string) ($this->message ?? '');
    }
}
