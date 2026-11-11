<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationDriveFolderWatch extends Model
{
    protected $table = 'automation_drive_folder_watches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accepted_file_types' => 'array',
            'last_matched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AutomationConnection::class, 'connection_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    public function fileEvents(): HasMany
    {
        return $this->hasMany(AutomationDriveFileEvent::class, 'watch_id');
    }
}
