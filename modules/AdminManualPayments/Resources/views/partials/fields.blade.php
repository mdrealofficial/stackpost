@php($plainSections = $plainSections ?? false)

@if ($plainSections)
    <div class="space-y-4">
        <div
            x-data="adminManualPaymentUserSelect({
                searchUrl: @js(route('admin-manual-payments.users.search')),
                initialSelected: @js(collect($selectedUserOption ?? [])->map(fn ($user) => [
                    'key' => (string) ($user['key'] ?? ''),
                    'label' => (string) ($user['label'] ?? ''),
                    'description' => (string) ($user['description'] ?? ''),
                ])->values()->all()),
            })"
            class="space-y-4"
        >
            <div>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('User') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search a user by name, email, or username and attach the manual payment request.') }}</p>
                    </div>
                    <span class="text-xs font-medium uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);" x-text="selected.length ? @js(__('Selected')) : @js(__('Required'))"></span>
                </div>
            </div>

            <div class="space-y-3">
                <input
                    type="text"
                    x-model="query"
                    x-on:input.debounce.250ms="search()"
                    class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                    style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                    placeholder="{{ __('Search users...') }}"
                >

                <template x-for="user in selected" :key="user.key">
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color)); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);">
                        <span x-text="user.label"></span>
                        <button type="button" class="text-slate-400 transition hover:text-slate-700" x-on:click="remove(user.key)">
                            <i class="fa-light fa-xmark"></i>
                        </button>
                    </div>
                </template>

                <div class="rounded-[0.9rem] border" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                    <div class="max-h-56 overflow-y-auto">
                        <template x-if="loading">
                            <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Searching users...') }}</div>
                        </template>

                        <template x-if="!loading && results.length === 0">
                            <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No matching users found.') }}</div>
                        </template>

                        <template x-for="user in results" :key="user.key">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 border-b px-4 py-3 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                style="border-color: color-mix(in srgb, var(--theme-border-color) 42%, transparent); color: var(--theme-header-text-color);"
                                x-on:click="select(user)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate" x-text="user.label"></span>
                                    <span class="block truncate text-xs" style="color: var(--theme-muted-text-color);" x-text="user.description"></span>
                                </span>
                                <span class="text-[var(--theme-accent)]" x-show="isSelected(user.key)">
                                    <i class="fa-light fa-check"></i>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <template x-for="user in selected" :key="'hidden-' + user.key">
                    <input type="hidden" name="uid" x-bind:value="user.key">
                </template>
            </div>
        </div>

        @if ($errors->first('uid'))
            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('uid') }}</p>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.choice-group
                name="status"
                :label="__('Status')"
                :value="(int) old('status', (int) ($manualPayment->status ?? 0))"
                :error="$errors->first('status')"
                :options="[
                    ['value' => 0, 'label' => __('Pending')],
                    ['value' => 1, 'label' => __('Approved')],
                    ['value' => 2, 'label' => __('Cancelled')],
                ]"
            />

            <x-ui.select name="plan_id" :label="__('Plan')" :error="$errors->first('plan_id')">
                <option value="">{{ __('Select plan') }}</option>
                @foreach ($planOptions as $option)
                    <option value="{{ $option['value'] }}" @selected((string) old('plan_id', $manualPayment->plan_id) === (string) $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.input name="payment_id" :label="__('Payment ID')" :value="old('payment_id', $manualPayment->payment_id)" :error="$errors->first('payment_id')" required />
            <x-ui.input name="payment_info" :label="__('Payment info')" :value="old('payment_info', $manualPayment->payment_info)" :error="$errors->first('payment_info')" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.input name="amount" type="number" step="0.01" min="0" :label="__('Amount')" :value="old('amount', $manualPayment->amount)" :error="$errors->first('amount')" required />
            <x-ui.input name="currency" :label="__('Currency')" :value="old('currency', $manualPayment->currency ?: 'USD')" :error="$errors->first('currency')" required />
        </div>

        <x-ui.textarea name="notes" :label="__('Notes')" :error="$errors->first('notes')" rows="4">{{ old('notes', $manualPayment->notes) }}</x-ui.textarea>
    </div>
@else
    <x-ui.form-section :title="__('Manual payment information')">
        @include('adminmanualpayments::partials.fields', [
            'manualPayment' => $manualPayment,
            'plainSections' => true,
            'planOptions' => $planOptions,
            'selectedUserOption' => $selectedUserOption,
        ])
    </x-ui.form-section>
@endif

@once
    <script>
        window.adminManualPaymentUserSelect = function (config) {
            return {
                query: '',
                loading: false,
                results: [],
                selected: config.initialSelected || [],
                async search() {
                    this.loading = true;

                    try {
                        const response = await fetch(config.searchUrl + '?q=' + encodeURIComponent(this.query), {
                            headers: { Accept: 'application/json' },
                        });
                        const result = await response.json();
                        this.results = result.items || [];
                    } finally {
                        this.loading = false;
                    }
                },
                isSelected(key) {
                    return this.selected.some(user => user.key === String(key));
                },
                select(user) {
                    this.selected = [{
                        key: String(user.key),
                        label: user.label,
                        description: user.description || '',
                    }];
                },
                remove(key) {
                    this.selected = this.selected.filter(user => user.key !== String(key));
                },
                init() {
                    this.search();
                },
            };
        };
    </script>
@endonce
