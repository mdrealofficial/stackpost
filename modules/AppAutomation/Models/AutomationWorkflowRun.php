<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationWorkflowRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_WAITING_DELAY = 'waiting_delay';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'automation_workflow_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'trace' => 'array',
            'output' => 'array',
            'next_attempt_at' => 'datetime',
            'claimed_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_SUCCEEDED,
            self::STATUS_FAILED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationWorkflowStepRun::class, 'run_id');
    }
}
