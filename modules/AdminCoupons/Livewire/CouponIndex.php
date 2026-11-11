<?php

namespace Modules\AdminCoupons\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminCoupons\Models\Coupon;
use Modules\AdminPlans\Models\AdminPlan;

#[Title('Coupons')]
class CouponIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all';

    public ?string $statusMessage = null;

    public bool $selectPage = false;

    /** @var array<int, string> */
    public array $selectedCouponIds = [];

    public bool $isEditingCoupon = false;

    public ?int $editingCouponId = null;

    public string $name = '';

    public string $code = '';

    public string $type = '1';

    public string $discount = '';

    public string $start_date = '';

    public string $end_date = '';

    /** @var array<int, string> */
    public array $plans = [];

    public string $usage_limit = '0';

    public string $status = '1';

    public function mount(): void
    {
        $this->resetCouponForm();
    }

    public function updatingSearch(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetSelection();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter']);
        $this->resetSelection();
    }

    public function updatedSelectPage(bool $value): void
    {
        if ($value) {
            $this->selectedCouponIds = $this->baseQuery()
                ->paginate(15, ['*'], 'page', $this->getPage())
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            return;
        }

        $this->selectedCouponIds = [];
    }

    public function updatedSelectedCouponIds(): void
    {
        $currentPageIds = $this->baseQuery()
            ->paginate(15, ['*'], 'page', $this->getPage())
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selectPage = ! empty($currentPageIds)
            && count(array_intersect($currentPageIds, $this->selectedCouponIds)) === count($currentPageIds);
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->resetCouponForm();
        $this->dispatch('coupon-modal-open');
    }

    public function openEditModal(int $couponId): void
    {
        $coupon = Coupon::query()->findOrFail($couponId);

        $this->resetValidation();
        $this->isEditingCoupon = true;
        $this->editingCouponId = $coupon->id;
        $this->name = (string) $coupon->name;
        $this->code = (string) $coupon->code;
        $this->type = (string) $coupon->type;
        $this->discount = (string) $coupon->discount;
        $this->start_date = (string) ($coupon->startDateFormatted() ?: '');
        $this->end_date = ($coupon->end_date ?? -1) !== -1 ? (string) $coupon->endDateFormatted() : '';
        $this->plans = collect($coupon->plans ?? [])->map(fn ($value) => (string) $value)->all();
        $this->usage_limit = (string) ($coupon->usage_limit ?? 0);
        $this->status = $coupon->status ? '1' : '0';

        $this->dispatch('coupon-modal-open');
    }

    public function saveCoupon(): void
    {
        $coupon = $this->editingCouponId ? Coupon::query()->findOrFail($this->editingCouponId) : null;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', 'in:1,2'],
            'discount' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*' => ['integer', Rule::exists('plans', 'id')],
            'usage_limit' => ['required', 'integer', 'min:-1'],
            'status' => ['required', 'in:0,1'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'code' => Str::upper(trim($validated['code'])),
            'type' => (int) $validated['type'],
            'discount' => $validated['discount'],
            'start_date' => Carbon::parse($validated['start_date'])->startOfDay()->timestamp,
            'end_date' => empty($validated['end_date']) ? -1 : Carbon::parse($validated['end_date'])->endOfDay()->timestamp,
            'plans' => array_values(array_map('intval', $validated['plans'])),
            'usage_limit' => (int) $validated['usage_limit'],
            'status' => (int) $validated['status'],
            'id_secure' => $coupon?->id_secure ?: Str::random(32),
            'usage_count' => $coupon?->usage_count ?? 0,
            'changed' => time(),
            'created' => $coupon?->created ?: time(),
        ];

        if ($coupon) {
            $coupon->update($payload);

            log_activity('admin.coupons.update', 'Updated a coupon.', [
                'subject_type' => Coupon::class,
                'subject_id' => $coupon->id,
                'metadata' => [
                    'code' => $coupon->code,
                    'type' => $coupon->typeLabel(),
                ],
            ]);

            $this->statusMessage = __('Coupon updated successfully.');
        } else {
            $coupon = Coupon::query()->create($payload);

            log_activity('admin.coupons.create', 'Created a coupon.', [
                'subject_type' => Coupon::class,
                'subject_id' => $coupon->id,
                'metadata' => [
                    'code' => $coupon->code,
                    'type' => $coupon->typeLabel(),
                ],
            ]);

            $this->statusMessage = __('Coupon created successfully.');
        }

        $this->dispatch('coupon-modal-close');
        $this->resetCouponForm();
        $this->resetPage();
    }

    public function selectAllPlans(): void
    {
        $this->plans = $this->paidPlanIds();
    }

    public function clearPlans(): void
    {
        $this->plans = [];
    }

    public function delete(int $couponId): void
    {
        $coupon = Coupon::query()->findOrFail($couponId);

        $metadata = [
            'code' => $coupon->code,
            'discount' => $coupon->discountLabel(),
        ];

        $coupon->delete();

        log_activity('admin.coupons.delete', 'Deleted a coupon.', [
            'subject_type' => Coupon::class,
            'subject_id' => $couponId,
            'metadata' => $metadata,
        ]);

        $this->statusMessage = __('Coupon deleted successfully.');
        $this->selectedCouponIds = array_values(array_filter(
            $this->selectedCouponIds,
            fn ($selectedId) => (int) $selectedId !== $couponId
        ));
        $this->selectPage = false;

        $this->resetPageIfNeeded();
    }

    public function deleteSelected(): void
    {
        $couponIds = collect($this->selectedCouponIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($couponIds->isEmpty()) {
            return;
        }

        $coupons = Coupon::query()
            ->whereIn('id', $couponIds->all())
            ->get(['id', 'code', 'discount', 'type']);

        foreach ($coupons as $coupon) {
            log_activity('admin.coupons.delete', 'Deleted a coupon.', [
                'subject_type' => Coupon::class,
                'subject_id' => $coupon->id,
                'metadata' => [
                    'code' => $coupon->code,
                    'discount' => $coupon->discountLabel(),
                ],
            ]);
        }

        Coupon::query()->whereIn('id', $couponIds->all())->delete();

        $this->statusMessage = __('Selected coupons deleted successfully.');
        $this->resetSelection();
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $coupons = $this->baseQuery()->paginate(15);
        $allCoupons = Coupon::query()->get(['status', 'usage_limit']);
        $planOptions = AdminPlan::query()
            ->where('free_plan', false)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AdminPlan $plan) => ['key' => (string) $plan->id, 'label' => $plan->name])
            ->all();

        return view('admincoupons::livewire.index', [
            'coupons' => $coupons,
            'summary' => [
                'total' => $allCoupons->count(),
                'enabled' => $allCoupons->where('status', true)->count(),
                'unlimited' => $allCoupons->where('usage_limit', -1)->count(),
            ],
            'planOptions' => $planOptions,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Coupons'),
        ]);
    }

    protected function baseQuery()
    {
        return Coupon::query()
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('discount', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn ($builder) => $builder->where('type', (int) $this->typeFilter))
            ->orderByDesc('created')
            ->orderByDesc('id');
    }

    protected function resetCouponForm(): void
    {
        $this->isEditingCoupon = false;
        $this->editingCouponId = null;
        $this->name = '';
        $this->code = '';
        $this->type = '1';
        $this->discount = '';
        $this->start_date = now()->format('Y-m-d');
        $this->end_date = '';
        $this->plans = [];
        $this->usage_limit = '0';
        $this->status = '1';
    }

    protected function resetSelection(): void
    {
        $this->selectPage = false;
        $this->selectedCouponIds = [];
    }

    protected function resetPageIfNeeded(): void
    {
        if (($this->paginators['page'] ?? 1) > 1
            && $this->baseQuery()->paginate(15, ['*'], 'page', $this->getPage())->isEmpty()) {
            $this->previousPage();
        }
    }

    protected function paidPlanIds(): array
    {
        return AdminPlan::query()
            ->where('free_plan', false)
            ->orderBy('position')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
