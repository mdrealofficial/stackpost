@component(theme_view('layouts.app', 'app'), ['title' => $isEditing ? __('Edit manual payment') : __('Create manual payment')])
    <div class="space-y-6">
        

        <x-ui.form-page
            :eyebrow="__('Finance')"
            :title="$isEditing ? __('Edit manual payment') : __('Create manual payment')"
            :description="$isEditing ? __('Review or adjust the user, assigned plan, offline payment details, and approval status.') : __('Create a manual payment request for an offline bank transfer or cash payment.')"
            card-class="space-y-6"
        >
            <x-slot:actions>
                <x-ui.button href="{{ route('admin-manual-payments.index') }}" variant="outline" wire:navigate>
                    {{ __('Back to manual payments') }}
                </x-ui.button>
            </x-slot:actions>

            <form method="POST" action="{{ $isEditing ? route('admin-manual-payments.update', $manualPayment) : route('admin-manual-payments.store') }}" class="space-y-6">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif

                @include('adminmanualpayments::partials.fields', [
                    'manualPayment' => $manualPayment,
                    'plainSections' => false,
                    'planOptions' => $planOptions,
                    'selectedUserOption' => $selectedUserOption,
                ])

                <x-ui.form-actions
                    :cancel-href="route('admin-manual-payments.index')"
                    :cancel-label="__('Back to manual payments')"
                    :submit-label="__('Save changes')"
                />
            </form>
        </x-ui.form-page>
    </div>
@endcomponent
