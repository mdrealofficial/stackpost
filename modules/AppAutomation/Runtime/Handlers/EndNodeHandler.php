<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;

class EndNodeHandler implements AutomationNodeHandler
{
    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        return AutomationNodeResult::success([
            'finished' => true,
            'finished_at' => now()->toIso8601String(),
        ]);
    }
}
