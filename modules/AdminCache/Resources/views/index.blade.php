<section class="w-full">
    <x-settings.layout :heading="__('Cache & session')" :subheading="__('Clear application caches and manage sessions safely.')">
        <div class="space-y-6">
            @if ($statusMessage)
                <x-ui.alert :variant="$statusVariant" :title="$statusVariant === 'success' ? __('Completed') : __('Action failed')" :description="$statusMessage" />
            @endif

            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($cacheActions as $action)
                    <div wire:key="cache-action-card-{{ $action['key'] }}">
                        <x-theme.section-card
                            :title="__($action['title'])"
                            :description="__($action['description'])"
                            body-class="p-6"
                        >
                            <x-ui.dialog wire:key="cache-action-dialog-{{ $action['key'] }}" :title="__($action['confirm_title'])" :description="__($action['confirm'])" width="sm">
                                <x-slot:trigger>
                                    <x-ui.button
                                        type="button"
                                        :variant="$action['variant']"
                                        block
                                        wire:loading.attr="disabled"
                                        wire:target="runAction"
                                    >
                                        <i class="fa-light {{ $action['icon'] }}"></i>
                                        <span>{{ __($action['button']) }}</span>
                                    </x-ui.button>
                                </x-slot:trigger>

                                <x-slot:footer>
                                    <div class="flex justify-end gap-3">
                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                        <x-ui.button
                                            type="button"
                                            variant="secondary"
                                            wire:click="runAction('{{ $action['key'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="runAction"
                                            x-on:click="open = false"
                                        >
                                            {{ __('Continue') }}
                                        </x-ui.button>
                                    </div>
                                </x-slot:footer>
                            </x-ui.dialog>
                        </x-theme.section-card>
                    </div>
                @endforeach
            </div>

            <x-theme.section-card
                :title="__($optimizeAction['title'])"
                :description="__($optimizeAction['description'])"
                body-class="p-6"
            >
                <x-ui.dialog wire:key="cache-action-dialog-{{ $optimizeAction['key'] }}" :title="__($optimizeAction['confirm_title'])" :description="__($optimizeAction['confirm'])" width="sm">
                    <x-slot:trigger>
                        <x-ui.button
                            type="button"
                            :variant="$optimizeAction['variant']"
                            block
                            wire:loading.attr="disabled"
                            wire:target="runAction"
                        >
                            <i class="fa-light {{ $optimizeAction['icon'] }}"></i>
                            <span>{{ __($optimizeAction['button']) }}</span>
                        </x-ui.button>
                    </x-slot:trigger>

                    <x-slot:footer>
                        <div class="flex justify-end gap-3">
                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button
                                type="button"
                                variant="secondary"
                                wire:click="runAction('{{ $optimizeAction['key'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="runAction"
                                x-on:click="open = false"
                            >
                                {{ __('Continue') }}
                            </x-ui.button>
                        </div>
                    </x-slot:footer>
                </x-ui.dialog>
            </x-theme.section-card>

            <x-ui.alert
                variant="danger"
                inline
                :title="__($sessionAction['title'])"
                :description="__($sessionAction['description'])"
            >
                <div class="mt-4">
                    <x-ui.dialog wire:key="cache-action-dialog-{{ $sessionAction['key'] }}" :title="__($sessionAction['confirm_title'])" :description="__($sessionAction['confirm'])" width="sm">
                        <x-slot:trigger>
                            <x-ui.button
                                type="button"
                                :variant="$sessionAction['variant']"
                                block
                                wire:loading.attr="disabled"
                                wire:target="runAction"
                            >
                                <i class="fa-light {{ $sessionAction['icon'] }}"></i>
                                <span>{{ __($sessionAction['button']) }}</span>
                            </x-ui.button>
                        </x-slot:trigger>

                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    wire:click="runAction('{{ $sessionAction['key'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="runAction"
                                    x-on:click="open = false"
                                >
                                    {{ __('Continue') }}
                                </x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </div>
            </x-ui.alert>
        </div>
    </x-settings.layout>
</section>
