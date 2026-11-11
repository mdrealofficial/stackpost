<div
    class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]"
    x-data="{ manualInfo: @entangle('state.payment_manual_info') }"
    x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('html-editor:set', { detail: { name: 'payment_manual_info_editor', content: manualInfo || '' } })))"
    x-on:html-editor:change.window="
        if (($event.detail?.name || '') === 'payment_manual_info_editor') {
            manualInfo = $event.detail?.content || '';
        }
    "
>
    <div class="space-y-5">
        <x-ui.radio-group
            name="state.payment_manual_status"
            wire:model.defer="state.payment_manual_status"
            :label="__('Status')"
            :options="$toggleOptions"
            :value="data_get($state, 'payment_manual_status')"
        />

        <x-ui.input wire:model.defer="state.payment_manual_prefix" :label="__('Reference Prefix')" type="text" :error="$errors->first('state.payment_manual_prefix')" />

        <x-ui.html-editor
            name="payment_manual_info_editor"
            :label="__('Customer Instructions')"
            :value="data_get($state, 'payment_manual_info', '')"
            :error="$errors->first('state.payment_manual_info')"
            :help="__('Enter bank account name, account number, branch, SWIFT/BIC, transfer note, and confirmation instructions shown to customers.')"
            rows="10"
        />
        <input type="hidden" x-model="manualInfo" wire:model.defer="state.payment_manual_info">
    </div>

    <div class="space-y-4 rounded-[1rem] border p-5" style="border-color: var(--theme-border-color); background: var(--theme-surface-soft);">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Checkout Preview') }}</p>
            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('This block helps you review how the offline payment method will be presented inside checkout.') }}</p>
        </div>

        <div class="space-y-3">
            <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Reference Example') }}</p>
                <p class="mt-2 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ data_get($state, 'payment_manual_prefix', 'PAY-') }}QB920E0I</p>
            </div>

            <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: var(--theme-surface-color);">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Customer View') }}</p>
                <template x-if="(manualInfo || '').trim() !== ''">
                    <div class="mt-2 text-sm leading-6 break-words [&>*:first-child]:mt-0 [&>*:last-child]:mb-0" style="color: var(--theme-header-text-color);" x-html="manualInfo"></div>
                </template>
                <template x-if="(manualInfo || '').trim() === ''">
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('No manual payment instructions configured yet.') }}</p>
                </template>
            </div>
        </div>
    </div>
</div>
