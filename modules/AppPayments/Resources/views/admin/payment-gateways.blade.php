<section class="w-full">
    <x-settings.layout :heading="__('Payment Gateways')" :subheading="__('Each payment module can register its own admin settings panel. Gateways without a module panel fall back to a generic summary.')">
        @php
            $initialOpenKey = $sections[0]['key'] ?? $fallbackSections[0]['key'] ?? null;
        @endphp

        <form wire:submit="save" class="my-6 w-full space-y-6" x-data="{ activePaymentGateway: @js($initialOpenKey ? (string) $initialOpenKey : null) }">
            @foreach ($sections as $section)
                @php
                    $statusKey = $section['status_key'] ?? null;
                    $statusValue = $statusKey ? (string) data_get($this->state, $statusKey, '') : null;
                    $enabledValue = (string) ($section['status_enabled_value'] ?? '1');
                    $isEnabled = $statusKey && $statusValue === $enabledValue;
                @endphp

                <x-payments.settings-accordion-card
                    :item-key="$section['key']"
                    :title="__($section['title'])"
                    :description="__($section['description'] ?? '')"
                    :status-label="$statusKey ? ($isEnabled ? __('Enabled') : __('Disabled')) : null"
                    :status-variant="$isEnabled ? 'success' : 'neutral'"
                    :default-open="$loop->first"
                    body-class="p-6"
                >
                    @include($section['view'], ['section' => $section, 'state' => $this->state] + ($section['data'] ?? []))
                </x-payments.settings-accordion-card>
            @endforeach

            @foreach ($fallbackSections as $section)
                <x-payments.fallback-settings-card :section="$section" :default-open="false" />
            @endforeach

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
