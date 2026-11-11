<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationWorkflow extends Model
{
    protected $table = 'automation_workflows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'settings' => 'array',
            'published_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AutomationWorkflowRevision::class, 'workflow_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationWorkflowRun::class, 'workflow_id');
    }
}
