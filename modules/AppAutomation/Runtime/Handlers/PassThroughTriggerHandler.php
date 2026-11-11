<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;

class PassThroughTriggerHandler implements AutomationNodeHandler
{
    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        return AutomationNodeResult::success([
            'event' => $context->originalInput(),
            'trigger_key' => (string) $context->run->trigger_key,
            'external_event_id' => $context->run->external_event_id,
        ]);
    }
}
