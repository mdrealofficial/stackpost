<?php

namespace Modules\AdminManualPayments\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\AdminManualPayments\Models\ManualPayment;
use Modules\AdminManualPayments\Services\ManualPaymentService;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

class ManualPaymentForm extends Component
{
    public ?ManualPayment $manualPayment = null;

    public string $uid = '';

    public string $plan_id = '';

    public string $payment_id = '';

    public string $payment_info = '';

    public string $amount = '';

    public string $currency = 'USD';

    public string $notes = '';

    public string $status = '0';

    public string $userQuery = '';

    public ?string $statusMessage = null;

    public bool $isEditing = false;

    public function mount(?ManualPayment $manualPayment = null): void
    {
        $this->manualPayment = $manualPayment?->exists ? $manualPayment->load(['user:id,name,username,email', 'plan:id,name,type']) : null;
        $this->isEditing = $this->manualPayment !== null;

        if (! $this->manualPayment) {
            return;
        }

        $this->uid = (string) $this->manualPayment->uid;
        $this->plan_id = (string) $this->manualPayment->plan_id;
        $this->payment_id = (string) $this->manualPayment->payment_id;
        $this->payment_info = (string) ($this->manualPayment->payment_info ?? '');
        $this->amount = (string) $this->manualPayment->amount;
        $this->currency = (string) ($this->manualPayment->currency ?: 'USD');
        $this->notes = (string) ($this->manualPayment->notes ?? '');
        $this->status = (string) $this->manualPayment->status;
    }

    public function selectUser(string $userId): void
    {
        $this->uid = $userId;
        $this->userQuery = '';
    }

    public function clearUser(): void
    {
        $this->uid = '';
    }

    public function save(ManualPaymentService $manualPaymentService): mixed
    {
        $validated = $this->validate([
            'uid' => ['required', 'integer', 'exists:users,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_id' => ['required', 'string', 'max:190'],
            'payment_info' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'integer', 'in:0,1,2'],
        ]);

        $payload = [
            'uid' => (int) $validated['uid'],
            'plan_id' => (int) $validated['plan_id'],
            'payment_id' => trim($validated['payment_id']),
            'payment_info' => $validated['payment_info'],
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'],
            'status' => (int) $validated['status'],
            'id_secure' => $this->manualPayment?->id_secure ?: Str::random(32),
            'changed' => time(),
            'created' => $this->manualPayment?->created ?: time(),
        ];

        if ($this->manualPayment) {
            $previousStatus = (int) $this->manualPayment->status;
            $this->manualPayment->update($payload);

            if ($previousStatus !== 1 && (int) $this->manualPayment->status === 1) {
                $manualPaymentService->approve($this->manualPayment);
            }

            log_activity('admin.manual-payments.update', 'Updated a manual payment request.', [
                'subject_type' => ManualPayment::class,
                'subject_id' => $this->manualPayment->id,
                'metadata' => [
                    'payment_id' => $this->manualPayment->payment_id,
                    'status' => $this->manualPayment->status,
                ],
            ]);

            $this->statusMessage = __('Manual payment updated successfully.');

            return null;
        }

        $this->manualPayment = ManualPayment::query()->create($payload);
        $this->isEditing = true;

        log_activity('admin.manual-payments.create', 'Created a manual payment request.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $this->manualPayment->id,
            'metadata' => [
                'payment_id' => $this->manualPayment->payment_id,
                'amount' => $this->manualPayment->amount,
            ],
        ]);

        if ((int) $this->manualPayment->status === 1) {
            $manualPaymentService->approve($this->manualPayment);
        }

        return $this->redirect(route('admin-manual-payments.edit', $this->manualPayment), navigate: true);
    }

    public function delete(): mixed
    {
        abort_unless($this->manualPayment, 404);

        $manualPaymentId = $this->manualPayment->id;
        $metadata = [
            'payment_id' => $this->manualPayment->payment_id,
            'amount' => $this->manualPayment->amount,
        ];

        $this->manualPayment->delete();

        log_activity('admin.manual-payments.delete', 'Deleted a manual payment request.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPaymentId,
            'metadata' => $metadata,
        ]);

        return $this->redirect(route('admin-manual-payments.index'), navigate: true);
    }

    public function render(): View
    {
        $selectedUser = $this->uid !== ''
            ? User::query()->find((int) $this->uid, ['id', 'name', 'username', 'email'])
            : null;

        $userResults = User::query()
            ->when(trim($this->userQuery) !== '', function ($builder): void {
                $search = trim($this->userQuery);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($this->uid !== '', fn ($builder) => $builder->where('id', '!=', (int) $this->uid))
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'username', 'email']);

        return view('adminmanualpayments::livewire.form', [
            'selectedUser' => $selectedUser,
            'userResults' => $userResults,
            'planOptions' => AdminPlan::query()
                ->where('status', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name', 'currency', 'price', 'type'])
                ->map(fn (AdminPlan $plan) => [
                    'key' => (string) $plan->id,
                    'label' => $plan->name,
                    'description' => trim(strtoupper($plan->currency).' '.number_format((float) $plan->price, 2).' / '.match ((int) $plan->type) {
                        2 => __('Yearly'),
                        3 => __('Lifetime'),
                        default => __('Monthly'),
                    }),
                ])
                ->all(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->isEditing ? __('Edit manual payment') : __('Create manual payment'),
        ]);
    }
}
