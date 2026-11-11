<?php

namespace Modules\AppAutomation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAutomation\Support\AutomationAccess;

#[Title('Workflow templates')]
class AutomationWorkflowTemplates extends Component
{
    protected AutomationAccess $access;

    public function boot(AutomationAccess $access): void
    {
        $this->access = $access;
    }

    public function mount(): void
    {
        abort_unless($this->access->canView(auth()->user()), 404);
    }

    public function render(): View
    {
        return view('appautomation::livewire.workflow-templates', [
            'canManage' => $this->access->canManage(auth()->user()),
            'schemaReady' => Schema::hasTable('automation_workflows') && Schema::hasTable('automation_workflow_revisions'),
            'templates' => $this->templates(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Workflow templates'),
        ]);
    }

    private function templates(): array
    {
        return \Modules\AppAutomation\Support\AutomationWorkflowCapabilities::templates();
    }
}
