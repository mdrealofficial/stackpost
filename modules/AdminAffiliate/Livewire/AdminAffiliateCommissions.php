<?php

namespace Modules\AdminAffiliate\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppAffiliate\Models\AffiliateCommission;
use Modules\AppAffiliate\Support\AffiliateService;

#[Title('Affiliate Commissions')]
class AdminAffiliateCommissions extends Component
{
    use WithPagination;

    protected AffiliateService $affiliate;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public ?string $statusMessage = null;

    public function boot(AffiliateService $affiliate): void
    {
        $this->affiliate = $affiliate;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function updateStatus(int $commissionId, string $status): void
    {
        $commission = AffiliateCommission::query()->findOrFail($commissionId);

        if ((int) $commission->status !== AffiliateCommission::STATUS_PENDING) {
            $this->statusMessage = __('This commission has already been processed.');
            return;
        }

        if ($status === 'approve') {
            $this->affiliate->approveCommission($commission);
            $this->statusMessage = __('Commission approved successfully.');
            $event = 'admin.affiliate.commission.approve';
        } else {
            $this->affiliate->rejectCommission($commission);
            $this->statusMessage = __('Commission rejected successfully.');
            $event = 'admin.affiliate.commission.reject';
        }

        log_activity($event, $this->statusMessage, [
            'subject_type' => AffiliateCommission::class,
            'subject_id' => $commission->id,
            'metadata' => [
                'commission' => $commission->id_secure,
                'status' => $status,
            ],
        ]);

        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = AffiliateCommission::query()
            ->with([
                'affiliateUser:id,name,username,email',
                'referredUser:id,name,username,email',
                'paymentHistory:id,transaction_id,from,currency',
            ])
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('id_secure', 'like', "%{$search}%")
                        ->orWhereHas('affiliateUser', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
                        })
                        ->orWhereHas('referredUser', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->latest('id');

        $commissions = $query->paginate(15);
        $allCommissions = AffiliateCommission::query()->get(['status', 'commission']);

        return view('adminaffiliate::livewire.commissions', [
            'commissions' => $commissions,
            'filters' => [
                'q' => $this->search,
                'status' => $this->statusFilter,
            ],
            'summary' => [
                'total' => $allCommissions->count(),
                'pending' => $allCommissions->where('status', AffiliateCommission::STATUS_PENDING)->count(),
                'approved' => (float) $allCommissions->where('status', AffiliateCommission::STATUS_APPROVED)->sum('commission'),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Affiliate Commissions'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && AffiliateCommission::query()
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('id_secure', 'like', "%{$search}%")
                            ->orWhereHas('affiliateUser', function ($userQuery) use ($search): void {
                                $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
                            })
                            ->orWhereHas('referredUser', function ($userQuery) use ($search): void {
                                $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
                ->latest('id')
                ->paginate(15, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }
}
