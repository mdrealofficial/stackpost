<?php

namespace Modules\AdminPaymentHistory\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminPaymentHistory\Models\PaymentHistory;

#[Title('Payment History')]
class PaymentHistoryIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'gateway', except: '')]
    public string $gatewayFilter = '';

    public ?string $statusMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGatewayFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'gatewayFilter']);
        $this->resetPage();
    }

    public function delete(int $paymentId): void
    {
        $paymentHistory = PaymentHistory::query()->findOrFail($paymentId);

        $paymentHistory->delete();

        log_activity('admin.payment-history.delete', 'Deleted a payment history record.', [
            'subject_type' => PaymentHistory::class,
            'subject_id' => $paymentHistory->id,
            'metadata' => [
                'invoice' => $paymentHistory->id_secure,
                'transaction_id' => $paymentHistory->transaction_id,
            ],
        ]);

        $this->statusMessage = __('Payment history record deleted successfully.');
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = PaymentHistory::query()
            ->with(['user:id,name,username,email', 'plan:id,name'])
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('id_secure', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%")
                        ->orWhere('from', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('plan', function ($planQuery) use ($search): void {
                            $planQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->when($this->gatewayFilter !== '', fn ($builder) => $builder->where('from', $this->gatewayFilter))
            ->orderByDesc('created')
            ->orderByDesc('id');

        $payments = $query->paginate(15);
        $allPayments = PaymentHistory::query()->get(['status', 'amount', 'from']);

        return view('adminpaymenthistory::livewire.index', [
            'payments' => $payments,
            'summary' => [
                'total' => $allPayments->count(),
                'success' => $allPayments->where('status', 1)->count(),
                'refunded' => $allPayments->where('status', 0)->count(),
                'gross' => (float) $allPayments->where('status', 1)->sum('amount'),
            ],
            'gateways' => PaymentHistory::query()
                ->whereNotNull('from')
                ->where('from', '!=', '')
                ->distinct()
                ->orderBy('from')
                ->pluck('from'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Payment History'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && PaymentHistory::query()
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('id_secure', 'like', "%{$search}%")
                            ->orWhere('transaction_id', 'like', "%{$search}%")
                            ->orWhere('from', 'like', "%{$search}%")
                            ->orWhere('currency', 'like', "%{$search}%");
                    });
                })
                ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
                ->when($this->gatewayFilter !== '', fn ($builder) => $builder->where('from', $this->gatewayFilter))
                ->paginate(15, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }
}
