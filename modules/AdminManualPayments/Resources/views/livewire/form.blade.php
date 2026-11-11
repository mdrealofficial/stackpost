<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" />
    @endif

    <x-ui.form-page
        :eyebrow="__('Finance')"
        :title="$isEditing ? __('Edit manual payment') : __('Create manual payment')"
        :description="$isEditing ? __('Review or adjust the user, assigned plan, offline payment details, and approval status.') : __('Create a manual payment request for an offline bank transfer or cash payment.')"
        card-class="space-y-6"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-manual-payments.index') }}" variant="outline" wire:navigate>{{ __('Back to manual payments') }}</x-ui.button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-6">
            <x-ui.form-section :title="__('Manual payment information')">
                <div class="space-y-5">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('User') }}</p>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search a user by name, email, or username and attach the manual payment request.') }}</p>
                            </div>
                            <span class="text-xs font-medium uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ $uid !== '' ? __('Selected') : __('Required') }}</span>
                        </div>

                        <x-ui.input wire:model.live.debounce.300ms="userQuery" :label="__('Search users')" :placeholder="__('Search users...')" />

                        @if ($selectedUser)
                            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color)); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);">
                                <span>{{ trim($selectedUser->name.' <'.$selectedUser->email.'>') }}</span>
                                <button type="button" class="text-slate-400 transition hover:text-slate-700" wire:click="clearUser"><i class="fa-light fa-xmark"></i></button>
                            </div>
                        @endif

                        <div class="rounded-[0.9rem] border" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <div class="max-h-56 overflow-y-auto">
                                @forelse ($userResults as $user)
                                    <button type="button" class="flex w-full items-center justify-between gap-3 border-b px-4 py-3 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60" style="border-color: color-mix(in srgb, var(--theme-border-color) 42%, transparent); color: var(--theme-header-text-color);" wire:key="manual-payment-user-{{ $user->id }}" wire:click="selectUser('{{ $user->id }}')">
                                        <span class="min-w-0">
                                            <span class="block truncate">{{ trim($user->name.' <'.$user->email.'>') }}</span>
                                            <span class="block truncate text-xs" style="color: var(--theme-muted-text-color);">{{ '@'.$user->username }}</span>
                                        </span>
                                    </button>
                                @empty
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No matching users found.') }}</div>
                                @endforelse
                            </div>
                        </div>

                        @if ($errors->first('uid'))
                            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('uid') }}</p>
                        @endif
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <x-ui.radio name="status" value="0" wire:model.live="status" :checked="$status === '0'" :label="__('Pending')" />
                                <x-ui.radio name="status" value="1" wire:model.live="status" :checked="$status === '1'" :label="__('Approved')" />
                                <x-ui.radio name="status" value="2" wire:model.live="status" :checked="$status === '2'" :label="__('Cancelled')" />
                            </div>
                        </x-ui.field>

                        <x-ui.field :label="__('Plan')" :error="$errors->first('plan_id')">
                            <select wire:model.live="plan_id" class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200" style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);">
                                <option value="">{{ __('Select plan') }}</option>
                                @foreach ($planOptions as $option)
                                    <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.input wire:model="payment_id" :label="__('Payment ID')" :error="$errors->first('payment_id')" required />
                        <x-ui.input wire:model="payment_info" :label="__('Payment info')" :error="$errors->first('payment_info')" />
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.input wire:model="amount" type="number" step="0.01" min="0" :label="__('Amount')" :error="$errors->first('amount')" required />
                        <x-ui.input wire:model="currency" :label="__('Currency')" :error="$errors->first('currency')" required />
                    </div>

                    <x-ui.textarea wire:model="notes" :label="__('Notes')" :error="$errors->first('notes')" rows="4"></x-ui.textarea>
                </div>
            </x-ui.form-section>

            <x-ui.form-actions :cancel-href="route('admin-manual-payments.index')" :cancel-label="__('Back to manual payments')">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.form-page>
</div>
