@php
    $metricCards = [
        ['label' => __('Packs'), 'value' => number_format($packCount), 'description' => __('Configured top-up offers.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Active'), 'value' => number_format($activeCount), 'description' => __('Currently purchasable packs.'), 'tone' => '#10b981', 'progress' => $packCount > 0 ? max(8, (int) round(($activeCount / max(1, $packCount)) * 100)) : 8],
        ['label' => __('Featured'), 'value' => number_format($featuredCount), 'description' => __('Highlighted packs in portal.'), 'tone' => '#f59e0b', 'progress' => $featuredCount > 0 ? max(8, (int) round(($featuredCount / max(1, $packCount)) * 100)) : 8],
    ];

    $statusLabel = fn ($value) => $value ? __('Enabled') : __('Disabled');
@endphp

<div class="mx-auto max-w-[88rem] space-y-6">
    <x-ui.page-hero
        :eyebrow="__('Credit operations')"
        :title="__('Credit Packs')"
        :description="__('Manage purchasable top-up packs separately from engine settings, with pricing, pack size, and merchandising controls in one place.')"
        :count="$packCount"
        icon="fa-light fa-layer-group"
    >
        <x-slot:actions>
            <x-ui.button type="button" wire:click="openCreateModal">{{ __('Add credit pack') }}</x-ui.button>
            <x-ui.button href="{{ route('settings.credits') }}" variant="outline" wire:navigate>{{ __('Open settings') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$metricCards" :show-icons="false" columns="md:grid-cols-3" />

    @if ($statusMessage)
        <x-ui.alert variant="success">{{ $statusMessage }}</x-ui.alert>
    @endif

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($packs as $pack)
            <x-ui.card class="space-y-4" wire:key="credit-pack-card-{{ $pack->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $pack->name }}</p>
                            @if ($pack->featured)
                                <x-ui.badge variant="primary">{{ __('Featured') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-xs font-mono" style="color: var(--theme-muted-text-color);">/{{ $pack->slug }}</p>
                    </div>
                    <x-ui.badge :variant="$pack->status ? 'success' : 'neutral'">{{ $statusLabel($pack->status) }}</x-ui.badge>
                </div>

                <x-ui.surface-card padding="sm" :accent="$pack->featured ? 'warning' : ($pack->status ? 'success' : 'neutral')" :featured="$pack->featured">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Credits') }}</p>
                            <p class="mt-2 text-[1.75rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($pack->credits) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Price') }}</p>
                            <p class="mt-2 text-[1.35rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ $pack->currency_symbol }}{{ number_format((float) $pack->price, 2) }}</p>
                            <p class="mt-1 text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ $pack->currency }}</p>
                        </div>
                    </div>
                </x-ui.surface-card>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.metric-card
                        :label="__('Sort order')"
                        :value="number_format((int) $pack->sort)"
                        :description="__('Display order in credit pack listings.')"
                        icon="fa-light fa-arrow-down-1-9"
                        accent="neutral"
                        padding="sm"
                        class="min-h-[auto]"
                    />
                    <x-ui.metric-card
                        :label="__('Currency')"
                        :value="$pack->currency_symbol.' '.$pack->currency"
                        :description="__('Checkout currency used for this pack.')"
                        icon="fa-light fa-coins"
                        accent="neutral"
                        padding="sm"
                        class="min-h-[auto]"
                    />
                </div>

                <div class="space-y-3">
                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                        {{ $pack->description ?: __('No description added for this credit pack yet.') }}
                    </p>
                </div>

                <div class="flex items-center justify-between gap-4 border-t pt-4" style="border-color: var(--theme-border-color);">
                    <div class="text-xs" style="color: var(--theme-muted-text-color);">
                        {{ __('Updated') }}: {{ optional($pack->updated_at)->format('M d, Y') ?: __('N/A') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button type="button" variant="outline" size="sm" wire:click="openEditModal({{ $pack->id }})">{{ __('Edit') }}</x-ui.button>
                        <x-ui.dialog :title="__('Delete credit pack?')" :description="__('This permanently removes the selected top-up offer from the checkout catalog.')" width="sm" dismissible>
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                            </x-slot:trigger>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="button" variant="danger" wire:click="deletePack({{ $pack->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card class="md:col-span-2 xl:col-span-3">
                <x-ui.empty
                    class="py-12"
                    icon="fa-light fa-layer-group"
                    :title="__('No credit packs found.')"
                    :description="__('Create your first top-up offer to sell additional credits in the portal.')"
                >
                    <x-ui.button type="button" wire:click="openCreateModal">{{ __('Add credit pack') }}</x-ui.button>
                </x-ui.empty>
            </x-ui.card>
        @endforelse
    </div>

    <x-ui.modal
        width="xl"
        open-event="credit-pack-modal-open"
        close-event="credit-pack-modal-close"
        :title="$isEditingPack ? __('Edit credit pack') : __('Create credit pack')"
        :description="$isEditingPack ? __('Adjust pricing, credits, merchandising, and checkout visibility for this offer.') : __('Create a new purchasable top-up pack with credits, pricing, and merchandising settings.')"
        body-class="space-y-6 overflow-visible max-h-none px-6 py-6"
    >
        <form id="credit-pack-modal-form" wire:submit="savePack" class="space-y-6">
            <section class="space-y-5">
                <div class="space-y-1">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Pack details') }}</h4>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Define the public pack name, pricing, and credit amount shown during checkout.') }}</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="name" :label="__('Pack name')" :error="$errors->first('name')" required autofocus />
                    <x-ui.input wire:model.defer="slug" :label="__('Slug')" :error="$errors->first('slug')" :placeholder="__('Auto-generated if empty')" />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model.defer="credits" type="number" min="1" :label="__('Credits')" :error="$errors->first('credits')" required />
                    <x-ui.input wire:model.defer="price" type="number" step="0.01" min="0" :label="__('Price')" :error="$errors->first('price')" required />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.select
                        wire:model.defer="currency"
                        :label="__('Currency')"
                        :error="$errors->first('currency')"
                        :help="$selectedCurrency ? __('Saved as :code. Checkout will use :symbol for :name.', ['code' => $selectedCurrency['code'], 'symbol' => $selectedCurrency['symbol'], 'name' => $selectedCurrency['name']]) : __('Choose the billing currency for this credit pack.')"
                        required
                    >
                        @foreach ($currencyOptions as $currencyOption)
                            <option value="{{ $currencyOption['code'] }}">{{ $currencyOption['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model.defer="sort" type="number" min="0" :label="__('Sort order')" :error="$errors->first('sort')" />
                </div>

                <x-ui.textarea wire:model.defer="description" rows="4" :label="__('Description')" :error="$errors->first('description')" />
            </section>

            <div class="border-t" style="border-color: var(--theme-border-color);"></div>

            <section class="space-y-5">
                <div class="space-y-1">
                    <h4 class="text-sm font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Visibility') }}</h4>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Control whether this pack is available in checkout and whether it should be highlighted in the portal.') }}</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.radio name="credit-pack-status" value="1" wire:model="status" :label="__('Enable')" />
                            <x-ui.radio name="credit-pack-status" value="0" wire:model="status" :label="__('Disable')" />
                        </div>
                    </x-ui.field>

                    <x-ui.field :label="__('Featured')" :error="$errors->first('featured')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.radio name="credit-pack-featured" value="1" wire:model="featured" :label="__('Enable')" />
                            <x-ui.radio name="credit-pack-featured" value="0" wire:model="featured" :label="__('Disable')" />
                        </div>
                    </x-ui.field>
                </div>
            </section>
        </form>

        <x-slot:footer>
            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="credit-pack-modal-form">{{ $isEditingPack ? __('Save changes') : __('Create pack') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
