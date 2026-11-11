<?php

namespace Modules\AdminPlans\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminPlans\Support\CurrencyCatalog;
use Modules\AdminPlans\Support\PlanPermissionSchema;
use Modules\AdminUser\Models\User;

class PlanForm extends Component
{
    public ?AdminPlan $plan = null;

    public bool $isEditing = false;

    public ?string $statusMessage = null;

    public array $form = [
        'name' => '',
        'slug' => '',
        'status' => '1',
        'featured' => '0',
        'currency' => 'USD',
        'price' => 0,
        'type' => '1',
        'free_plan' => '0',
        'default_signup_plan' => '0',
        'trial_day' => 0,
        'position' => 0,
        'desc' => '',
    ];

    public array $permissionsState = [];

    public function mount(?AdminPlan $plan = null): void
    {
        $this->plan = $plan?->exists ? $plan : new AdminPlan();
        $this->isEditing = $this->plan->exists;

        $this->form = [
            'name' => (string) ($this->plan->name ?? ''),
            'slug' => (string) ($this->plan->slug ?? ''),
            'status' => (string) (int) ($this->plan->status ?? true),
            'featured' => (string) (int) ($this->plan->featured ?? false),
            'currency' => CurrencyCatalog::normalizeCode((string) ($this->plan->currency ?: 'USD')),
            'price' => (float) ($this->plan->price ?? 0),
            'type' => (string) (int) ($this->plan->type ?: 1),
            'free_plan' => (string) (int) ($this->plan->free_plan ?? false),
            'default_signup_plan' => (string) (int) ($this->plan->default_signup_plan ?? false),
            'trial_day' => (int) ($this->plan->trial_day ?? 0),
            'position' => (int) ($this->plan->position ?? 0),
            'desc' => (string) ($this->plan->desc ?? ''),
        ];

        $this->permissionsState = $this->permissionSchema()->mapToState($this->plan->permissions ?? []);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $isDefaultSignupPlan = (bool) $validated['form']['default_signup_plan'];
        $isFreePlan = (bool) $validated['form']['free_plan'];
        $isEnabled = (bool) $validated['form']['status'];
        $isZeroPriceTrialPlan = ! $isFreePlan
            && (float) ($validated['form']['price'] ?? 0) <= 0
            && (int) ($validated['form']['trial_day'] ?? 0) > 0;

        if ($isDefaultSignupPlan && ! $isFreePlan && ! $isZeroPriceTrialPlan) {
            $this->addError('form.default_signup_plan', __('The default signup plan must be either a free plan or a zero-price trial plan.'));

            return;
        }

        if ($isDefaultSignupPlan && ! $isEnabled) {
            $this->addError('form.default_signup_plan', __('The default signup plan must be enabled.'));

            return;
        }

        $payload = [
            'name' => trim($validated['form']['name']),
            'slug' => $this->uniqueSlug($validated['form']['slug'] ?: $validated['form']['name'], $this->isEditing ? $this->plan : null),
            'status' => $isEnabled,
            'featured' => (bool) $validated['form']['featured'],
            'currency' => CurrencyCatalog::normalizeCode($validated['form']['currency']),
            'price' => $validated['form']['price'] ?? 0,
            'type' => (int) $validated['form']['type'],
            'free_plan' => $isFreePlan,
            'default_signup_plan' => $isDefaultSignupPlan,
            'trial_day' => $validated['form']['trial_day'] ?? 0,
            'position' => $validated['form']['position'] ?? 0,
            'desc' => $validated['form']['desc'] ?? '',
            'permissions' => $this->permissionSchema()->normalize($this->permissionsState),
        ];

        if ($this->isEditing) {
            DB::transaction(function () use ($payload, $isDefaultSignupPlan): void {
                if ($isDefaultSignupPlan) {
                    AdminPlan::query()
                        ->whereKeyNot($this->plan->id)
                        ->where('default_signup_plan', true)
                        ->update(['default_signup_plan' => false]);
                }

                $this->plan->update($payload);
            });

            log_activity('admin.plans.update', 'Updated a finance plan.', [
                'subject' => $this->plan,
                'metadata' => [
                    'plan' => $this->plan->name,
                    'status' => $this->plan->status ? 'enabled' : 'disabled',
                ],
            ]);

            $this->statusMessage = __('Plan updated successfully.');

            return;
        }

        $this->plan = DB::transaction(function () use ($payload, $isDefaultSignupPlan): AdminPlan {
            if ($isDefaultSignupPlan) {
                AdminPlan::query()
                    ->where('default_signup_plan', true)
                    ->update(['default_signup_plan' => false]);
            }

            return AdminPlan::query()->create($payload);
        });
        $this->isEditing = true;

        log_activity('admin.plans.create', 'Created a finance plan.', [
            'subject' => $this->plan,
            'metadata' => [
                'plan' => $this->plan->name,
                'status' => $this->plan->status ? 'enabled' : 'disabled',
            ],
        ]);

        session()->flash('status', __('Plan created successfully.'));
        $this->redirectRoute('admin-plans.edit', ['plan' => $this->plan->id], navigate: true);
    }

    public function delete(): void
    {
        if (! $this->isEditing || ! $this->plan?->exists) {
            return;
        }

        $metadata = [
            'plan' => $this->plan->name,
            'status' => $this->plan->status ? 'enabled' : 'disabled',
        ];

        User::query()->where('plan_id', $this->plan->id)->update([
            'plan_id' => null,
            'plan_started_at' => null,
            'plan_expires_at' => null,
        ]);

        $deletedPlanId = $this->plan->id;
        $this->plan->delete();

        log_activity('admin.plans.delete', 'Deleted a finance plan.', [
            'subject_type' => AdminPlan::class,
            'subject_id' => $deletedPlanId,
            'metadata' => $metadata,
        ]);

        session()->flash('status', __('Plan deleted successfully.'));
        $this->redirectRoute('admin-plans.index', navigate: true);
    }

    public function render(): View
    {
        return view('adminplans::livewire.form', [
            'plan' => $this->plan,
            'currencyOptions' => CurrencyCatalog::options(),
            'selectedCurrency' => CurrencyCatalog::find($this->form['currency']) ?? CurrencyCatalog::find('USD'),
            'permissionSections' => $this->permissionSchema()->definitions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->isEditing ? __('Edit plan') : __('Create plan'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255', Rule::unique('plans', 'name')->ignore($this->plan?->id)],
            'form.slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($this->plan?->id)],
            'form.status' => ['required', Rule::in(['0', '1'])],
            'form.featured' => ['required', Rule::in(['0', '1'])],
            'form.currency' => ['required', Rule::in(CurrencyCatalog::codes())],
            'form.price' => ['nullable', 'numeric', 'min:0'],
            'form.type' => ['required', Rule::in(['1', '2', '3'])],
            'form.free_plan' => ['required', Rule::in(['0', '1'])],
            'form.default_signup_plan' => ['required', Rule::in(['0', '1'])],
            'form.trial_day' => ['nullable', 'integer', 'min:-1'],
            'form.position' => ['nullable', 'integer', 'min:0'],
            'form.desc' => ['nullable', 'string'],
            'permissionsState' => ['nullable', 'array'],
        ];
    }

    protected function uniqueSlug(string $value, ?AdminPlan $plan = null): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'plan';
        $counter = 2;

        while (
            AdminPlan::query()
                ->where('slug', $slug)
                ->when($plan, fn ($query) => $query->whereKeyNot($plan->id))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function permissionSchema(): PlanPermissionSchema
    {
        return app(PlanPermissionSchema::class);
    }
}
