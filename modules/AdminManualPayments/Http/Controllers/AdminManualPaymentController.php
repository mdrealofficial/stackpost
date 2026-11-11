<?php

namespace Modules\AdminManualPayments\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AdminManualPayments\Models\ManualPayment;
use Modules\AdminManualPayments\Services\ManualPaymentService;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminUser\Models\User;

class AdminManualPaymentController extends Controller
{
    public function __construct(
        protected ManualPaymentService $manualPaymentService
    ) {
    }

    public function index(Request $request): View
    {
        $query = ManualPayment::query()
            ->with(['user:id,name,username,email', 'plan:id,name,type'])
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $search = trim((string) $request->string('q'));

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
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', (int) $request->input('status')))
            ->orderByDesc('created')
            ->orderByDesc('id');

        $manualPayments = $query->paginate(15)->withQueryString();
        $allPayments = ManualPayment::query()->get(['status', 'amount']);

        return view('adminmanualpayments::index', [
            'manualPayments' => $manualPayments,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->input('status', 'all'),
            ],
            'summary' => [
                'total' => $allPayments->count(),
                'pending' => $allPayments->where('status', 0)->count(),
                'approved' => $allPayments->where('status', 1)->count(),
                'gross' => (float) $allPayments->where('status', 1)->sum('amount'),
            ],
            'planOptions' => $this->planOptions(),
            'selectedUserOption' => $this->selectedUserOption($request->session()->getOldInput('uid')),
        ]);
    }

    public function create(): View
    {
        return view('adminmanualpayments::create', $this->formData(new ManualPayment()));
    }

    public function store(Request $request): RedirectResponse
    {
        $manualPayment = ManualPayment::query()->create($this->validateManualPayment($request));

        log_activity('admin.manual-payments.create', 'Created a manual payment request.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPayment->id,
            'metadata' => [
                'payment_id' => $manualPayment->payment_id,
                'amount' => $manualPayment->amount,
            ],
        ]);

        if ((int) $manualPayment->status === 1) {
            $this->manualPaymentService->approve($manualPayment);
        }

        $redirectRoute = $request->input('_manual_payment_modal') === 'create'
            ? route('admin-manual-payments.index')
            : route('admin-manual-payments.edit', $manualPayment);

        return redirect($redirectRoute)->with('status', __('Manual payment created successfully.'));
    }

    public function edit(ManualPayment $manualPayment): View
    {
        $manualPayment->load(['user:id,name,username,email', 'plan:id,name']);

        return view('adminmanualpayments::edit', $this->formData($manualPayment));
    }

    public function update(Request $request, ManualPayment $manualPayment): RedirectResponse
    {
        $previousStatus = (int) $manualPayment->status;
        $manualPayment->update($this->validateManualPayment($request, $manualPayment));

        if ($previousStatus !== 1 && (int) $manualPayment->status === 1) {
            $this->manualPaymentService->approve($manualPayment);
        }

        log_activity('admin.manual-payments.update', 'Updated a manual payment request.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPayment->id,
            'metadata' => [
                'payment_id' => $manualPayment->payment_id,
                'status' => $manualPayment->status,
            ],
        ]);

        $redirectRoute = $request->input('_manual_payment_modal') === 'edit'
            ? route('admin-manual-payments.index')
            : route('admin-manual-payments.edit', $manualPayment);

        return redirect($redirectRoute)->with('status', __('Manual payment updated successfully.'));
    }

    public function updateStatus(Request $request, ManualPayment $manualPayment, string $status): RedirectResponse
    {
        $statusValue = match ($status) {
            'approve' => 1,
            'cancel' => 2,
            default => 0,
        };

        if ($statusValue === 1) {
            $this->manualPaymentService->approve($manualPayment);
        } else {
            $manualPayment->forceFill([
                'status' => $statusValue,
                'changed' => time(),
            ])->save();
        }

        log_activity('admin.manual-payments.status', 'Changed manual payment status.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPayment->id,
            'metadata' => [
                'payment_id' => $manualPayment->payment_id,
                'status' => $statusValue,
            ],
        ]);

        return redirect()
            ->route('admin-manual-payments.index')
            ->with('status', __('Manual payment status updated successfully.'));
    }

    public function destroy(ManualPayment $manualPayment): RedirectResponse
    {
        $metadata = [
            'payment_id' => $manualPayment->payment_id,
            'amount' => $manualPayment->amount,
        ];

        $manualPayment->delete();

        log_activity('admin.manual-payments.delete', 'Deleted a manual payment request.', [
            'subject_type' => ManualPayment::class,
            'subject_id' => $manualPayment->id,
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('admin-manual-payments.index')
            ->with('status', __('Manual payment deleted successfully.'));
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('q'));

        $users = User::query()
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'username', 'email']);

        return response()->json([
            'items' => $users->map(fn (User $user) => [
                'key' => (string) $user->id,
                'label' => trim($user->name.' <'.$user->email.'>'),
                'description' => '@'.$user->username,
            ])->all(),
        ]);
    }

    protected function validateManualPayment(Request $request, ?ManualPayment $manualPayment = null): array
    {
        $validated = $request->validate([
            'uid' => ['required', 'integer', 'exists:users,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_id' => ['required', 'string', 'max:190'],
            'payment_info' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'integer', 'in:0,1,2'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['payment_id'] = trim($validated['payment_id']);
        $validated['id_secure'] = $manualPayment?->id_secure ?: Str::random(32);
        $validated['changed'] = time();
        $validated['created'] = $manualPayment?->created ?: time();

        return $validated;
    }

    protected function formData(ManualPayment $manualPayment): array
    {
        return [
            'manualPayment' => $manualPayment,
            'isEditing' => $manualPayment->exists,
            'planOptions' => $this->planOptions(),
            'selectedUserOption' => $manualPayment->uid ? $this->selectedUserOption($manualPayment->uid) : [],
        ];
    }

    protected function planOptions(): array
    {
        return AdminPlan::query()
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'currency', 'price', 'type'])
            ->map(fn (AdminPlan $plan) => [
                'value' => (string) $plan->id,
                'label' => $plan->name,
                'description' => trim(strtoupper($plan->currency).' '.number_format((float) $plan->price, 2).' · '.match ((int) $plan->type) {
                    2 => __('Yearly'),
                    3 => __('Lifetime'),
                    default => __('Monthly'),
                }),
            ])
            ->all();
    }

    protected function selectedUserOption(mixed $userId): array
    {
        $user = User::query()->find((int) $userId, ['id', 'name', 'username', 'email']);

        if (! $user) {
            return [];
        }

        return [[
            'key' => (string) $user->id,
            'label' => trim($user->name.' <'.$user->email.'>'),
            'description' => '@'.$user->username,
        ]];
    }
}
