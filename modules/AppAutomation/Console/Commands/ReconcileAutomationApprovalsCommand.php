<?php

namespace Modules\AppAutomation\Console\Commands;

use Illuminate\Console\Command;
use Modules\AppAutomation\Services\AutomationWorkflowApprovalReconciler;

class ReconcileAutomationApprovalsCommand extends Command
{
    protected $signature = 'automation:reconcile-approvals {--limit=100 : Maximum waiting approval steps to inspect}';

    protected $description = 'Resume workflow runs whose persisted publishing reviews reached a final decision.';

    public function handle(AutomationWorkflowApprovalReconciler $reconciler): int
    {
        $stats = $reconciler->reconcileWaiting((int) $this->option('limit'));

        $this->info(__('Checked :checked approval step(s): :approved approved, :rejected rejected, :waiting waiting, :missing inconsistent, :failed failed.', $stats));

        return ($stats['failed'] + $stats['missing']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
