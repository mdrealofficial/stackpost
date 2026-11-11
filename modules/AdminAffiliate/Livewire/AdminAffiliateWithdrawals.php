<?php

namespace Modules\AdminAffiliate\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AppAffiliate\Models\AffiliateWithdrawal;
use Modules\AppAffiliate\Support\AffiliateService;

#[Title('Affiliate Withdrawals')]
class AdminAffiliateWithdrawals extends Component
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

    public function updateStatus(int $withdrawalId, string $status): void
    {
        $withdrawal = AffiliateWithdrawal::query()->findOrFail($withdrawalId);

        if ((int) $withdrawal->status !== AffiliateWithdrawal::STATUS_PENDING) {
            $this->statusMessage = __('This withdrawal has already been processed.');
            return;
        }

        if ($status === 'approve') {
            $this->affiliate->approveWithdrawal($withdrawal, '');
            $this->statusMessage = __('Withdrawal approved successfully.');
            $event = 'admin.affiliate.withdrawal.approve';
        } else {
            $this->affiliate->rejectWithdrawal($withdrawal, '');
            $this->statusMessage = __('Withdrawal rejected successfully.');
            $event = 'admin.affiliate.withdrawal.reject';
        }

        log_activity($event, $this->statusMessage, [
            'subject_type' => AffiliateWithdrawal::class,
            'subject_id' => $withdrawal->id,
            'metadata' => [
                'withdrawal' => $withdrawal->id_secure,
                'status' => $status,
            ],
        ]);

        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = AffiliateWithdrawal::query()
            ->with(['affiliateUser:id,name,username,email'])
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('id_secure', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhereHas('affiliateUser', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->latest('id');

        $withdrawals = $query->paginate(15);
        $allWithdrawals = AffiliateWithdrawal::query()->get(['status', 'amount']);

        return view('adminaffiliate::livewire.withdrawals', [
            'withdrawals' => $withdrawals,
            'filters' => [
                'q' => $this->search,
                'status' => $this->statusFilter,
            ],
            'summary' => [
                'total' => $allWithdrawals->count(),
                'pending' => $allWithdrawals->where('status', AffiliateWithdrawal::STATUS_PENDING)->count(),
                'approved' => (float) $allWithdrawals->where('status', AffiliateWithdrawal::STATUS_APPROVED)->sum('amount'),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Affiliate Withdrawals'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && AffiliateWithdrawal::query()
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('id_secure', 'like', "%{$search}%")
                            ->orWhere('payment_method', 'like', "%{$search}%")
                            ->orWhereHas('affiliateUser', function ($userQuery) use ($search): void {
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
