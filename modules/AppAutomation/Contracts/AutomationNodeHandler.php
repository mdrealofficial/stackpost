<?php

namespace Modules\AppAutomation\Contracts;

use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;

interface AutomationNodeHandler
{
    public function handle(AutomationNodeContext $context): AutomationNodeResult;
}
