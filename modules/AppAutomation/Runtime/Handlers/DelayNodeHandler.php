<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;

class DelayNodeHandler implements AutomationNodeHandler
{
    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $amount = max(1, (int) $context->config('amount', 5));
        $unit = (string) $context->config('unit', 'minutes');
        $resumeAt = match ($unit) {
            'days' => now()->addDays($amount),
            'hours' => now()->addHours($amount),
            default => now()->addMinutes($amount),
        };

        return AutomationNodeResult::waitUntil($resumeAt, [
            'amount' => $amount,
            'unit' => $unit,
            'resume_at' => $resumeAt->toIso8601String(),
        ]);
    }
}
