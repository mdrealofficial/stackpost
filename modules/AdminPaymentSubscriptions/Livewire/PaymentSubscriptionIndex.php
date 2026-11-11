<?php

namespace Modules\AdminPaymentSubscriptions\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;

#[Title('Payment Subscriptions')]
class PaymentSubscriptionIndex extends Component
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

    public function delete(int $subscriptionId): void
    {
        $paymentSubscription = PaymentSubscription::query()->findOrFail($subscriptionId);
        $metadata = [
            'subscription_id' => $paymentSubscription->subscription_id,
            'customer_id' => $paymentSubscription->customer_id,
        ];

        $paymentSubscription->delete();

        log_activity('admin.payment-subscriptions.delete', 'Deleted a payment subscription record.', [
            'subject_type' => PaymentSubscription::class,
            'subject_id' => $paymentSubscription->id,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Payment subscription deleted successfully.');
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = PaymentSubscription::query()
            ->with(['user:id,name,username,email', 'plan:id,name'])
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('id_secure', 'like', "%{$search}%")
                        ->orWhere('subscription_id', 'like', "%{$search}%")
                        ->orWhere('customer_id', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhere('service', 'like', "%{$search}%")
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
            ->when($this->gatewayFilter !== '', fn ($builder) => $builder->where('source', $this->gatewayFilter))
            ->orderByDesc('created')
            ->orderByDesc('id');

        $subscriptions = $query->paginate(15);
        $allSubscriptions = PaymentSubscription::query()->get(['status', 'amount', 'source']);

        return view('adminpaymentsubscriptions::livewire.index', [
            'subscriptions' => $subscriptions,
            'summary' => [
                'total' => $allSubscriptions->count(),
                'active' => $allSubscriptions->where('status', 1)->count(),
                'cancelled' => $allSubscriptions->where('status', 2)->count(),
                'monthly_value' => (float) $allSubscriptions->where('status', 1)->sum('amount'),
            ],
            'gateways' => PaymentSubscription::query()
                ->whereNotNull('source')
                ->where('source', '!=', '')
                ->distinct()
                ->orderBy('source')
                ->pluck('source'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Payment Subscriptions'),
        ]);
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && PaymentSubscription::query()
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('id_secure', 'like', "%{$search}%")
                            ->orWhere('subscription_id', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%")
                            ->orWhere('source', 'like', "%{$search}%")
                            ->orWhere('service', 'like', "%{$search}%")
                            ->orWhere('currency', 'like', "%{$search}%");
                    });
                })
                ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
                ->when($this->gatewayFilter !== '', fn ($builder) => $builder->where('source', $this->gatewayFilter))
                ->paginate(15, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }
}
