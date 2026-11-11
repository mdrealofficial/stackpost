<?php

namespace Modules\AdminManualPayments\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminManualPayments\Models\ManualPayment;
use Modules\AdminManualPayments\Services\ManualPaymentService;
use Modules\AppPayments\Support\PaymentNotificationService;

#[Title('Manual Payments')]
class ManualPaymentIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public ?string $statusMessage = null;

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

    public function approve(int $manualPaymentId, ManualPaymentService $manualPaymentService): void
    {
        $manualPayment = ManualPayment::query()->findOrFail($manualPaymentId);

        $manualPaymentService->approve($manualPayment);

        log_activity('admin.manual-payments.status', 'Changed manual payment status.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPayment->id,
            'metadata' => [
                'payment_id' => $manualPayment->payment_id,
                'status' => 1,
            ],
        ]);

        $this->statusMessage = __('Manual payment approved successfully.');
    }

    public function cancel(int $manualPaymentId, PaymentNotificationService $notifications): void
    {
        $manualPayment = ManualPayment::query()->findOrFail($manualPaymentId);
        $manualPayment->loadMissing(['user', 'plan']);

        $manualPayment->forceFill([
            'status' => 2,
            'changed' => time(),
        ])->save();

        if ($manualPayment->user) {
            $notifications->notify(
                $manualPayment->user,
                'cancel',
                $notifications->buildContext(
                    user: $manualPayment->user,
                    plan: $manualPayment->plan,
                    extra: [
                        'gateway' => 'manual',
                        'status' => 'cancelled',
                        'transaction_id' => (string) $manualPayment->payment_id,
                        'message' => __('Your manual payment request was cancelled.'),
                    ],
                )
            );
        }

        log_activity('admin.manual-payments.status', 'Changed manual payment status.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPayment->id,
            'metadata' => [
                'payment_id' => $manualPayment->payment_id,
                'status' => 2,
            ],
        ]);

        $this->statusMessage = __('Manual payment cancelled successfully.');
    }

    public function delete(int $manualPaymentId): void
    {
        $manualPayment = ManualPayment::query()->findOrFail($manualPaymentId);
        $metadata = [
            'payment_id' => $manualPayment->payment_id,
            'amount' => $manualPayment->amount,
        ];

        $manualPayment->delete();

        log_activity('admin.manual-payments.delete', 'Deleted a manual payment request.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPaymentId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Manual payment deleted successfully.');
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = $this->baseQuery();
        $manualPayments = $query->paginate(15);
        $allPayments = ManualPayment::query()->get(['status', 'amount']);

        return view('adminmanualpayments::livewire.index', [
            'manualPayments' => $manualPayments,
            'summary' => [
                'total' => $allPayments->count(),
                'pending' => $allPayments->where('status', 0)->count(),
                'approved' => $allPayments->where('status', 1)->count(),
                'gross' => (float) $allPayments->where('status', 1)->sum('amount'),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Manual Payments'),
        ]);
    }

    protected function baseQuery()
    {
        return ManualPayment::query()
            ->with(['user:id,name,username,email', 'plan:id,name,type'])
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('payment_id', 'like', "%{$search}%")
                        ->orWhere('payment_info', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
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
            ->orderByDesc('created')
            ->orderByDesc('id');
    }

    protected function resetPageIfNeeded(): void
    {
        if (($this->paginators['page'] ?? 1) > 1 && $this->baseQuery()->paginate(15, ['*'], 'page', $this->getPage())->isEmpty()) {
            $this->previousPage();
        }
    }
}
