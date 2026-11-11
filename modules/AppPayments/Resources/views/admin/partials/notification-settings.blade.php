@php
    $toggleOptions = [
        ['value' => '1', 'label' => 'Enable'],
        ['value' => '0', 'label' => 'Disable'],
    ];
    $events = [
        ['key' => 'success', 'label' => __('Payment success')],
        ['key' => 'failed', 'label' => __('Payment failed')],
        ['key' => 'review', 'label' => __('Payment review / pending')],
        ['key' => 'cancel', 'label' => __('Payment / recurring cancel')],
    ];
@endphp

<div class="space-y-6">
    <x-ui.alert
        variant="neutral"
        :title="__('Template placeholders')"
        :description="__('Use :name, :email, :plan, :amount, :currency, :gateway, :transaction_id, :subscription_id, :status, :message, :app_name inside subjects and messages.')"
    />

    @foreach ($events as $event)
        @php($base = 'payment_notify_'.$event['key'])
        <x-theme.section-card
            :title="$event['label']"
            :description="__('Configure the outbound payment email sent to the customer for this event.')"
            body-class="space-y-5 p-6"
        >
            <x-ui.radio-group
                :name="'state.'.$base.'_status'"
                wire:model.live="state.{{ $base }}_status"
                :label="__('Email status')"
                :options="$toggleOptions"
                :value="data_get($state, $base.'_status')"
            />

            <x-ui.input
                wire:model="state.{{ $base }}_subject"
                type="text"
                :label="__('Email subject')"
                :error="$errors->first('state.'.$base.'_subject')"
            />

            <x-ui.textarea
                wire:model="state.{{ $base }}_message"
                :label="__('Email message')"
                :error="$errors->first('state.'.$base.'_message')"
                rows="5"
            />
        </x-theme.section-card>
    @endforeach
</div>
