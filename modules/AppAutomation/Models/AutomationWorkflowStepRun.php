<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationWorkflowStepRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_WAITING_DELAY = 'waiting_delay';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'automation_workflow_step_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowRun::class, 'run_id');
    }
}
