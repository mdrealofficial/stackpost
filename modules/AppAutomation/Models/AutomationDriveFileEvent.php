<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationDriveFileEvent extends Model
{
    protected $table = 'automation_drive_file_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_modified_at' => 'datetime',
            'payload' => 'array',
            'dispatched_at' => 'datetime',
        ];
    }

    public function watch(): BelongsTo
    {
        return $this->belongsTo(AutomationDriveFolderWatch::class, 'watch_id');
    }
}
