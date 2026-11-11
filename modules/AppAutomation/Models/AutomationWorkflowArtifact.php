<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AppFiles\Models\AppFile;

class AutomationWorkflowArtifact extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowRun::class, 'run_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(AppFile::class, 'app_file_id');
    }
}
