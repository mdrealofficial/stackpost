<?php

namespace Modules\AdminPlans\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

#[Title('Plans')]
class PlanIndex extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'billing', except: 'all')]
    public string $billingFilter = 'all';

    #[Url(as: 'featured', except: 'all')]
    public string $featuredFilter = 'all';

    public function toggleStatus(int $planId): void
    {
        $plan = AdminPlan::query()->find($planId);

        if (! $plan) {
            return;
        }

        $plan->update([
            'status' => ! $plan->status,
        ]);

        log_activity('admin.plans.update-status', 'Changed plan status.', [
            'subject' => $plan,
            'metadata' => [
                'plan' => $plan->name,
                'status' => $plan->status ? 'enabled' : 'disabled',
            ],
        ]);
    }

    public function deletePlan(int $planId): void
    {
        $plan = AdminPlan::query()->find($planId);

        if (! $plan) {
            return;
        }

        User::query()->where('plan_id', $plan->id)->update([
            'plan_id' => null,
            'plan_started_at' => null,
            'plan_expires_at' => null,
        ]);

        $metadata = [
            'plan' => $plan->name,
            'status' => $plan->status ? 'enabled' : 'disabled',
        ];

        $deletedPlanId = $plan->id;
        $plan->delete();

        log_activity('admin.plans.delete', 'Deleted a finance plan.', [
            'subject_type' => AdminPlan::class,
            'subject_id' => $deletedPlanId,
            'metadata' => $metadata,
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'billingFilter', 'featuredFilter']);
    }

    public function render(): View
    {
        $plans = AdminPlan::query()
            ->withCount('users')
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);

                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('desc', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', (int) $this->statusFilter))
            ->when($this->billingFilter !== 'all', fn ($query) => $query->where('type', (int) $this->billingFilter))
            ->when($this->featuredFilter !== 'all', fn ($query) => $query->where('featured', (int) $this->featuredFilter))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('adminplans::livewire.index', [
            'plans' => $plans,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Plans'),
        ]);
    }
}
