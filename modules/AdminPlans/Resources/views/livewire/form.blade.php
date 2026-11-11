<div class="space-y-6">
    

    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Finance')"
        :title="$isEditing ? __('Edit plan') : __('Create new plan')"
        :description="$isEditing ? __('Modify pricing, feature access, and assignment rules for this subscription plan.') : __('Create a new subscription plan with pricing, lifecycle options, and permission bundles.')"
        icon="fa-light fa-layer-group"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-plans.index') }}" variant="outline" wire:navigate>{{ __('Back to plans') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.card class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <x-ui.form-section :title="__('Plan info')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.surface-card padding="sm" accent="none">
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Status') }}</p>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Control whether this plan is available for assignment.') }}</p>
                            </div>
                            <x-ui.field :error="$errors->first('form.status')">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-ui.radio name="plan-status" value="1" wire:model="form.status" :label="__('Enable')" />
                                    <x-ui.radio name="plan-status" value="0" wire:model="form.status" :label="__('Disable')" />
                                </div>
                            </x-ui.field>
                        </div>
                    </x-ui.surface-card>

                    <x-ui.surface-card padding="sm" accent="none">
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Featured') }}</p>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Highlight this plan in plan selectors and pricing pages.') }}</p>
                            </div>
                            <x-ui.field :error="$errors->first('form.featured')">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-ui.radio name="plan-featured" value="1" wire:model="form.featured" :label="__('Yes')" />
                                    <x-ui.radio name="plan-featured" value="0" wire:model="form.featured" :label="__('No')" />
                                </div>
                            </x-ui.field>
                        </div>
                    </x-ui.surface-card>
                </div>

                <div class="grid gap-5 lg:grid-cols-12">
                    <div class="space-y-5 lg:col-span-8">
                        <x-ui.input wire:model.defer="form.name" :label="__('Name')" :error="$errors->first('form.name')" required autofocus />
                        <x-ui.textarea wire:model.defer="form.desc" :label="__('Description')" :error="$errors->first('form.desc')" rows="4" />
                    </div>
                    <div class="space-y-5 lg:col-span-4">
                        <x-ui.input wire:model.defer="form.position" type="number" min="0" :label="__('Position')" :error="$errors->first('form.position')" />
                        <x-ui.input wire:model.defer="form.slug" :label="__('Slug')" :error="$errors->first('form.slug')" :placeholder="__('auto-generated if empty')" />
                        <x-ui.select
                            wire:model.defer="form.currency"
                            :label="__('Currency')"
                            :error="$errors->first('form.currency')"
                            :help="$selectedCurrency ? __('Saved as :code. Pricing cards will use :symbol for :name.', ['code' => $selectedCurrency['code'], 'symbol' => $selectedCurrency['symbol'], 'name' => $selectedCurrency['name']]) : __('Choose the billing currency for this plan.')"
                            required
                        >
                            @foreach ($currencyOptions as $currencyOption)
                                <option value="{{ $currencyOption['code'] }}">{{ $currencyOption['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>
            </x-ui.form-section>

            <x-ui.form-section :title="__('Pricing')">
                <div class="grid gap-4 xl:grid-cols-12">
                    <x-ui.surface-card padding="sm" accent="none" class="space-y-4 xl:col-span-7">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Billing setup') }}</p>
                        <x-ui.input wire:model.defer="form.price" type="number" step="0.01" min="0" :label="__('Price')" :error="$errors->first('form.price')" />
                        <x-ui.select wire:model.defer="form.type" :label="__('Payment Frequency')" :error="$errors->first('form.type')">
                            <option value="1">{{ __('Monthly') }}</option>
                            <option value="2">{{ __('Yearly') }}</option>
                            <option value="3">{{ __('Lifetime') }}</option>
                        </x-ui.select>
                    </x-ui.surface-card>

                    <x-ui.surface-card padding="sm" accent="none" class="space-y-4 xl:col-span-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Access policy') }}</p>
                        <x-ui.select wire:model.defer="form.free_plan" :label="__('Free Plan')" :error="$errors->first('form.free_plan')">
                            <option value="1">{{ __('Yes') }}</option>
                            <option value="0">{{ __('No') }}</option>
                        </x-ui.select>
                        <x-ui.select wire:model.defer="form.default_signup_plan" :label="__('Default signup plan')" :error="$errors->first('form.default_signup_plan')">
                            <option value="0">{{ __('No') }}</option>
                            <option value="1">{{ __('Yes') }}</option>
                        </x-ui.select>
                        <x-ui.input wire:model.defer="form.trial_day" type="number" min="-1" :label="__('Trial day')" :error="$errors->first('form.trial_day')" :help="__('Enter -1 for unlimited.')" />
                    </x-ui.surface-card>
                </div>

                <div class="rounded-[0.95rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 90%, white 10%);">
                    <div class="space-y-1.5 text-sm" style="color: var(--theme-muted-text-color);">
                        <p><strong>{{ __('Free Plan:') }}</strong> {{ __('Select YES to make the plan free with no expiration date.') }}</p>
                        <p><strong>{{ __('Default Signup Plan:') }}</strong> {{ __('When enabled, new registrations and admin-created users without a chosen plan will receive this free plan or zero-price trial plan automatically.') }}</p>
                        <p><strong>{{ __('Trial Plan:') }}</strong> {{ __('Set Price to 0, Free Plan to NO, and enter Trial day to start users with a defined expiration date.') }}</p>
                    </div>
                </div>
            </x-ui.form-section>

            @foreach ($permissionSections as $section)
                @php
                    $sectionKey = $section['key'];
                    $sectionType = $section['type'] ?? 'toggle';
                    $toggleable = $section['toggleable'] ?? true;
                @endphp

                @if ($sectionType === 'toggle')
                    <div class="rounded-[1.25rem] border px-5 py-4 shadow-[0_10px_30px_rgba(15,23,42,0.04)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.95); background: var(--theme-surface-color);">
                        <x-ui.checkbox
                            wire:model.defer="permissionsState.{{ $sectionKey }}"
                            value="1"
                            :label="$section['label']"
                            label-class="text-[1.125rem]"
                            :checked="(bool) ($permissionsState[$sectionKey] ?? false)"
                        />
                    </div>
                @else
                    @if ($sectionKey === 'credits_usage')
                        @php
                            $planCreditsField = collect($section['fields'])->firstWhere('key', 'credits_usage_limit');
                            $creditFields = collect($section['fields'])->reject(fn (array $field) => $field['key'] === 'credits_usage_limit');
                            $aiStudioFields = $creditFields->filter(fn (array $field) => str_starts_with((string) $field['key'], 'credit_cost_ai_studio_'))->values();
                            $aiPublishingFields = $creditFields->filter(fn (array $field) => str_starts_with((string) $field['key'], 'credit_cost_ai_publishing_'))->values();
                            $rssAutomationFields = $creditFields->filter(fn (array $field) => str_starts_with((string) $field['key'], 'credit_cost_rss_'))->values();
                            $otherCreditFields = $creditFields->reject(fn (array $field) => str_starts_with((string) $field['key'], 'credit_cost_ai_studio_') || str_starts_with((string) $field['key'], 'credit_cost_ai_publishing_') || str_starts_with((string) $field['key'], 'credit_cost_rss_'))->values();
                        @endphp

                        <x-ui.form-section>
                            <x-slot:title>
                                <span class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ $section['label'] }}</span>
                            </x-slot:title>

                            @if ($planCreditsField)
                                <div class="rounded-[1.3rem] border p-5 sm:p-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.78); background:
                                    radial-gradient(circle at top right, rgba(var(--theme-accent-rgb), 0.08), transparent 26%),
                                    color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="max-w-2xl">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Plan Credits') }}</p>
                                            <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $planCreditsField['description'] ?? __('Total credits available for this billing period.') }}</p>
                                        </div>
                                        <div class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(var(--theme-accent-rgb), 0.22); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                            {{ __('Shared pool') }}
                                        </div>
                                    </div>

                                    <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,17rem)_1fr]">
                                        <div class="rounded-[1.05rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.54); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Unlimited mode') }}</p>
                                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Set :value to allow unlimited usage.', ['value' => '-1']) }}</p>
                                        </div>
                                        <x-ui.input
                                            wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $planCreditsField['key'] }}"
                                            type="number"
                                            :label="$planCreditsField['label']"
                                            :help="null"
                                        />
                                    </div>
                                </div>
                            @endif

                            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                                <div class="space-y-5">
                                    <div class="rounded-[1.25rem] border p-5 sm:p-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('AI Studio Costs') }}</p>
                                                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Configure the credit cost for each AI Studio tool. These values are consumed from the shared plan credit pool.') }}</p>
                                            </div>
                                            <div class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em]" style="border-color: rgba(var(--theme-accent-rgb), 0.22); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                                {{ $aiStudioFields->count() }} {{ __('tools') }}
                                            </div>
                                        </div>

                                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                                            @foreach ($aiStudioFields as $field)
                                                <div class="rounded-[1.05rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background:
                                                    linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.04), transparent 28%),
                                                    color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $field['label'] }}</p>
                                                            <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $field['description'] ?? __('Credits deducted when this tool runs.') }}</p>
                                                        </div>
                                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ __('Credits') }}</span>
                                                    </div>

                                                    <div class="mt-4">
                                                        <x-ui.input
                                                            wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $field['key'] }}"
                                                            type="number"
                                                            :label="__('Cost per run')"
                                                            :help="null"
                                                        />
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-5">
                                    @if ($aiPublishingFields->isNotEmpty())
                                        <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('AI Publishing Costs') }}</p>
                                            <div class="mt-4 space-y-3">
                                                @foreach ($aiPublishingFields as $field)
                                                    <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background-color: color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $field['label'] }}</p>
                                                        <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $field['description'] ?? __('Credits deducted when this tool runs.') }}</p>
                                                        <div class="mt-3">
                                                            <x-ui.input
                                                                wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $field['key'] }}"
                                                                type="number"
                                                                :label="__('Cost per run')"
                                                                :help="null"
                                                            />
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if ($rssAutomationFields->isNotEmpty())
                                        <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('RSS Automation Costs') }}</p>
                                            <div class="mt-4 space-y-3">
                                                @foreach ($rssAutomationFields as $field)
                                                    <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.48); background-color: color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $field['label'] }}</p>
                                                        <p class="mt-1 text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ $field['description'] ?? __('Credits deducted when this tool runs.') }}</p>
                                                        <div class="mt-3">
                                                            <x-ui.input
                                                                wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $field['key'] }}"
                                                                type="number"
                                                                :label="__('Cost per run')"
                                                                :help="null"
                                                            />
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Credit Notes') }}</p>
                                        <div class="mt-4 space-y-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                            <p>{{ __('All AI costs below deduct from the same plan-wide credit pool.') }}</p>
                                            <p>{{ __('AI Video uses the 4-second value as its base cost. 8-second renders use 2x and 12-second renders use 3x.') }}</p>
                                            <p>{{ __('Keep lower-cost actions at 1 credit when you want AI discovery tools to stay inexpensive.') }}</p>
                                        </div>
                                    </div>

                                    @if ($otherCreditFields->isNotEmpty())
                                        <div class="rounded-[1.25rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 97%, transparent);">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Other Credit Actions') }}</p>
                                            <div class="mt-4 space-y-3">
                                                @foreach ($otherCreditFields as $field)
                                                    <x-ui.input
                                                        wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $field['key'] }}"
                                                        type="number"
                                                        :label="$field['label']"
                                                        :help="$field['description'] ?? null"
                                                    />
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </x-ui.form-section>
                    @elseif ($sectionKey === 'ai_studio')
                        <x-ui.form-section>
                            <x-slot:title>
                                @if ($toggleable)
                                    <x-ui.checkbox
                                        wire:model.defer="permissionsState.{{ $sectionKey }}.enabled"
                                        :label="$section['label']"
                                        label-class="text-[1.125rem]"
                                        :checked="(bool) data_get($permissionsState, $sectionKey.'.enabled', false)"
                                    />
                                @else
                                    <span class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ $section['label'] }}</span>
                                @endif
                            </x-slot:title>

                            @php
                                $toggleFields = collect($section['fields'])->filter(fn (array $field) => ($field['type'] ?? 'boolean') === 'boolean')->values();
                                $numberFields = collect($section['fields'])->filter(fn (array $field) => ($field['type'] ?? 'boolean') === 'number')->values();
                            @endphp

                            @if ($toggleFields->isNotEmpty())
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($toggleFields as $field)
                                        <div class="rounded-[1.05rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.54); background-color: color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                            <x-ui.checkbox
                                                wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $field['key'] }}"
                                                value="1"
                                                :label="$field['label']"
                                                :checked="(bool) data_get($permissionsState, $sectionKey.'.'.$field['key'], false)"
                                            />
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($numberFields->isNotEmpty())
                                <div class="grid gap-4 md:grid-cols-2">
                                    @foreach ($numberFields as $field)
                                        <div class="rounded-[1.05rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.54); background-color: color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                            <x-ui.input
                                                wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $field['key'] }}"
                                                type="number"
                                                :label="$field['label']"
                                                :help="$field['description'] ?? null"
                                            />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-ui.form-section>
                    @else
                        <x-ui.form-section>
                            <x-slot:title>
                                @if ($toggleable)
                                    <x-ui.checkbox
                                        wire:model.defer="permissionsState.{{ $sectionKey }}.enabled"
                                        :label="$section['label']"
                                        label-class="text-[1.125rem]"
                                        :checked="(bool) data_get($permissionsState, $sectionKey.'.enabled', false)"
                                    />
                                @else
                                    <span class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ $section['label'] }}</span>
                                @endif
                            </x-slot:title>

                            @foreach ($section['fields'] as $field)
                                @php
                                    $fieldKey = $field['key'];
                                    $fieldType = $field['type'] ?? 'boolean';
                                @endphp

                                @if ($fieldType === 'number')
                                    <x-ui.input
                                        wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $fieldKey }}"
                                        type="number"
                                        :label="$field['label']"
                                        :help="$field['description'] ?? null"
                                    />
                                @elseif ($fieldType === 'choice')
                                    <x-ui.field :label="$field['label']">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            @foreach ($field['options'] ?? [] as $option)
                                                <x-ui.radio
                                                    :name="'permissions-'.$sectionKey.'-'.$fieldKey"
                                                    :value="$option['value']"
                                                    wire:model="permissionsState.{{ $sectionKey }}.{{ $fieldKey }}"
                                                    :label="$option['label']"
                                                />
                                            @endforeach
                                        </div>
                                    </x-ui.field>
                                @elseif ($fieldType === 'checkbox_list')
                                    <x-ui.field :label="$field['label']" :help="$field['description'] ?? null">
                                        <div class="space-y-4">
                                            <div class="grid gap-x-6 gap-y-4 md:grid-cols-2 xl:grid-cols-3">
                                                @foreach ($field['options'] ?? [] as $option)
                                                    <x-ui.checkbox
                                                        wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $fieldKey }}"
                                                        :value="$option['key']"
                                                        :label="$option['label']"
                                                    />
                                                @endforeach
                                            </div>
                                        </div>
                                    </x-ui.field>
                                @else
                                    <div class="rounded-[1.05rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.54); background:
                                        linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.04), transparent 28%),
                                        color-mix(in srgb, var(--theme-surface-base) 95%, transparent);">
                                        <x-ui.checkbox
                                            wire:model.defer="permissionsState.{{ $sectionKey }}.{{ $fieldKey }}"
                                            value="1"
                                            :label="$field['label']"
                                            :checked="(bool) data_get($permissionsState, $sectionKey.'.'.$fieldKey, false)"
                                        />
                                    </div>
                                @endif
                            @endforeach
                        </x-ui.form-section>
                    @endif
                @endif
            @endforeach

            <x-ui.form-actions :cancel-href="route('admin-plans.index')" :cancel-label="__('Back to plans')" :submit-label="$isEditing ? __('Save changes') : __('Create plan')">
                @if ($isEditing)
                    <x-ui.dialog :title="__('Delete this plan?')" :description="__('This removes the plan and clears it from users currently assigned to it.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger">{{ __('Delete') }}</x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="delete" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                @endif
            </x-ui.form-actions>
        </form>
    </x-ui.card>
</div>
