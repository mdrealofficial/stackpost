<?php

namespace Modules\AppAutomation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationWorkflowRevision extends Model
{
    protected $table = 'automation_workflow_revisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
