<?php

namespace Modules\AppPayments\Console\Commands;

use Illuminate\Console\Command;
use Modules\AppPayments\Support\UserPlanTransitionService;

class ActivateScheduledPlansCommand extends Command
{
    protected $signature = 'plans:activate-scheduled';

    protected $description = 'Activate scheduled plan downgrades once the current plan expires.';

    public function handle(UserPlanTransitionService $planTransitions): int
    {
        $count = $planTransitions->activateDuePlans();

        $this->info("Activated {$count} scheduled plan transition(s).");

        return self::SUCCESS;
    }
}
