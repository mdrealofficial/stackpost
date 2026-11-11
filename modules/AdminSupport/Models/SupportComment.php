<?php

namespace Modules\AdminSupport\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminUser\Models\User;

class SupportComment extends Model
{
    protected $table = 'support_comments';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->created) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->created)->format($format);
    }
}
