<?php

namespace Modules\AppAutomation\Exceptions;

use Carbon\CarbonInterface;

class AutomationNodeRetryAtException extends AutomationNodeException
{
    public function __construct(string $message, public readonly CarbonInterface $retryAt)
    {
        parent::__construct($message, true);
    }
}
