<?php

namespace Modules\AdminUser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'causer_user_id',
        'event',
        'description',
        'subject_type',
        'subject_id',
        'route_name',
        'area',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
