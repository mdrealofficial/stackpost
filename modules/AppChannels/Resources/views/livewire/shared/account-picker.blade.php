<div
    class="mx-auto max-w-[70rem] space-y-6"
    x-data="{
        selectedIds: @js($selectedIds),
        visibleIds: @js(collect($items)->pluck('id')->values()->all()),
        isSelected(id) {
            return this.selectedIds.includes(id);
        },
        allVisibleSelected() {
            return this.visibleIds.length > 0 && this.visibleIds.every((id) => this.isSelected(id));
        },
        toggleSelection(id) {
            if (this.isSelected(id)) {
                this.selectedIds = this.selectedIds.filter((value) => value !== id);
                return;
            }

            this.selectedIds = [...this.selectedIds, id];
        },
        selectAllVisible() {
            this.selectedIds = Array.from(new Set([...this.selectedIds, ...this.visibleIds]));
        },
        clearVisible() {
            this.selectedIds = this.selectedIds.filter((value) => !this.visibleIds.includes(value));
        },
        toggleAllVisible() {
            if (this.allVisibleSelected()) {
                this.clearVisible();
                return;
            }

            this.selectAllVisible();
        }
    }"
>
    @php($pickerFlash = session('channels.flash'))
    @php($pickerFlashTone = data_get($pickerFlash, 'tone', 'warning'))
    @php($pickerFlashMessage = data_get($pickerFlash, 'message'))

    <x-ui.card
        class="overflow-hidden border-none shadow-[0_30px_80px_-48px_rgba(var(--theme-accent-rgb),0.45)]"
        style="background:
            radial-gradient(circle at top left, rgba(var(--theme-accent-rgb), 0.22), transparent 38%),
            linear-gradient(135deg, color-mix(in srgb, var(--theme-accent) 10%, var(--theme-surface-base)) 0%, var(--theme-surface-base) 58%, color-mix(in srgb, var(--theme-header-text-color) 6%, var(--theme-surface-base)) 100%);
        "
    >
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-xl shadow-[0_18px_38px_-24px_rgba(var(--theme-accent-rgb),0.55)]" style="background-color: rgba(var(--theme-accent-rgb), 0.14); color: var(--theme-accent);">
                    <i class="{{ $pickerIcon ?? 'fa-brands fa-facebook-f' }}"></i>
                </span>
                <div class="min-w-0">
                    <x-ui.sub-header
                        :eyebrow="$pickerEyebrow ?? __('Channel connection')"
                        :title="$pickerTitle"
                        :description="$pickerDescription"
                        :count="$hasSourceItems ? $sourceItemsCount : count($items)"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button href="{{ route('portal.channels') }}" variant="outline" wire:navigate>
                    {{ __('Back to channels') }}
                </x-ui.button>
                <x-ui.button type="button" variant="outline" wire:click="reconnect">
                    <i class="fa-light fa-rotate-right"></i>
                    {{ $reconnectLabel }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.card>

    @if ($pickerFlashMessage)
        <x-ui.card
            @class([
                'border-amber-200 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-500/10' => $pickerFlashTone === 'warning',
                'border-emerald-200 bg-emerald-50/80 dark:border-emerald-500/20 dark:bg-emerald-500/10' => $pickerFlashTone === 'success',
                'border-rose-200 bg-rose-50/80 dark:border-rose-500/20 dark:bg-rose-500/10' => $pickerFlashTone === 'danger',
            ])
        >
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-header-text-color);">
                    <i class="fa-light {{ $pickerFlashTone === 'success' ? 'fa-circle-check' : ($pickerFlashTone === 'danger' ? 'fa-circle-xmark' : 'fa-circle-exclamation') }}"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">
                        {{ $pickerFlashTone === 'success' ? __('Channel updated') : ($pickerFlashTone === 'danger' ? __('Channel action failed') : __('Channel attention required')) }}
                    </p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $pickerFlashMessage }}</p>
                </div>
            </div>
        </x-ui.card>
    @endif

    @if (($confirmOverLimit ?? false) && ($pendingAcceptedCount ?? 0) > 0)
        <x-ui.alert
            :title="__('Confirm selection')"
            :description="__('You selected more channels than your remaining plan limit. Confirm to add only the first :count selected channel(s).', ['count' => $pendingAcceptedCount])"
            variant="warning"
        >
            <x-slot:actions>
                <div class="flex gap-3">
                    <x-ui.button type="button" variant="outline" wire:click="cancelAddSelected">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="button" wire:click="confirmAddSelected">
                        {{ __('Confirm') }}
                    </x-ui.button>
                </div>
            </x-slot:actions>
        </x-ui.alert>
    @endif

    @if (! $hasSourceItems)
        <x-ui.section-card
            :title="$emptyTitle"
            :description="$emptyDescription"
            body-class="p-6"
        >
            <div class="rounded-[1.35rem] border p-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.07), rgba(var(--theme-border-color-rgb), 0.03));">
                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ $emptyBody }}</p>
                <div class="mt-4">
                    <x-ui.button type="button" wire:click="reconnect">
                        <i class="fa-light fa-rotate-right"></i>
                        {{ $reconnectLabel }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.section-card>
    @else
        <div class="grid gap-6 xl:grid-cols-[19rem_minmax(0,1fr)] xl:items-start">
            <aside class="space-y-4 xl:sticky xl:top-24">
                <x-ui.section-card
                    body-class="space-y-4 p-4"
                >
                    <div class="grid gap-3">
                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Available') }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ count($items) }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Accounts currently returned by the provider.') }}</p>
                        </div>

                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Selected') }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);" x-text="selectedIds.length"></p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Only selected accounts will be added to Channels.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Search helper') }}</p>
                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $searchHelp }}</p>
                    </div>
                </x-ui.section-card>
            </aside>

            <x-ui.section-card body-class="p-0">
                <div class="overflow-hidden rounded-[calc(var(--theme-card-radius,1rem)-1px)]">
                    <div class="border-b px-6 py-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $collectionTitle }}</p>
                                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $collectionDescription }}</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-3 py-1.5 text-sm font-medium" style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);" x-text="`${selectedIds.length} {{ __('selected') }}`"></span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition hover:border-[color:rgba(var(--theme-accent-rgb),0.38)] hover:text-[color:var(--theme-accent)]"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color); background-color: var(--theme-surface-base);"
                                        x-on:click="toggleAllVisible()"
                                    >
                                        <i class="fa-light" x-bind:class="allVisibleSelected() ? 'fa-square-check' : 'fa-check-double'"></i>
                                        <span x-text="allVisibleSelected() ? '{{ __('Clear visible') }}' : '{{ __('Select all visible') }}'"></span>
                                    </button>
                                </div>
                            </div>

                            <x-ui.input
                                wire:model.live.debounce.250ms="search"
                                type="text"
                                :label="$searchLabel"
                                :placeholder="$searchPlaceholder"
                                :help="$searchHelp"
                            />
                        </div>
                    </div>

                    <div class="space-y-3 px-6 py-5">
                        @forelse ($items as $item)
                            @php($usesLocalSocialAvatar = str_contains((string) ($item['avatar_url'] ?? ''), '/media/public/social-accounts/') || str_contains((string) ($item['avatar_url'] ?? ''), '/storage/social-accounts/'))
                            <label
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-[1rem] border px-5 py-4 transition"
                                x-bind:style="isSelected('{{ $item['id'] }}')
                                    ? 'border-color: rgba(var(--theme-accent-rgb), 0.32); background-color: rgba(var(--theme-accent-rgb), 0.04);'
                                    : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);'"
                            >
                                <div class="flex min-w-0 items-center gap-4">
                                    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border text-sm font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: {{ $usesLocalSocialAvatar ? 'rgba(255,255,255,0.96)' : 'rgba(var(--theme-border-color-rgb), 0.04)' }}; color: var(--theme-header-text-color);">
                                        @if (! empty($item['avatar_url']))
                                            @if ($usesLocalSocialAvatar)
                                                <span class="inline-flex h-8 w-8 items-center justify-center overflow-hidden rounded-[0.7rem] bg-white shadow-[0_8px_16px_-12px_rgba(15,23,42,0.22)] ring-1 ring-slate-200/70">
                                                    <img src="{{ $item['avatar_url'] }}" alt="{{ $item['name'] }}" class="h-full w-full object-contain">
                                                </span>
                                            @else
                                                <img src="{{ $item['avatar_url'] }}" alt="{{ $item['name'] }}" class="h-full w-full object-cover">
                                            @endif
                                        @else
                                            {{ str($item['name'])->substr(0, 2)->upper() }}
                                        @endif
                                    </span>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="truncate text-xl font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $item['name'] }}</p>
                                            <span
                                                x-show="isSelected('{{ $item['id'] }}')"
                                                x-cloak
                                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                style="background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);"
                                            >
                                                {{ __('Queued') }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm" style="color: var(--theme-muted-text-color);">
                                            <span>{{ $item['subtitle'] ?: $defaultSubtitle }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span
                                        class="hidden rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] md:inline"
                                        x-bind:style="isSelected('{{ $item['id'] }}')
                                            ? 'background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);'
                                            : 'background-color: rgba(var(--theme-border-color-rgb), 0.06); color: var(--theme-muted-text-color);'"
                                        x-text="isSelected('{{ $item['id'] }}') ? '{{ __('Selected') }}' : '{{ __('Select') }}'"
                                    >
                                    </span>

                                    <label class="group inline-flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="peer sr-only"
                                            :checked="isSelected('{{ $item['id'] }}')"
                                            x-on:change="toggleSelection('{{ $item['id'] }}')"
                                        >
                                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-[0.4rem] border border-slate-300 bg-white text-white shadow-[0_1px_2px_rgba(15,23,42,0.06)] transition-all duration-150 group-hover:border-[color:rgba(var(--theme-accent-rgb),0.45)] peer-checked:border-[color:var(--theme-accent)] peer-checked:bg-[color:var(--theme-accent)] peer-checked:text-white peer-checked:shadow-[0_0_0_3px_rgba(var(--theme-accent-rgb),0.14)] peer-checked:[&_svg]:opacity-100 dark:border-slate-700 dark:bg-slate-950">
                                            <svg viewBox="0 0 16 16" aria-hidden="true" class="h-3.5 w-3.5 opacity-0 transition-opacity duration-150" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2">
                                                <path d="M3.5 8.5 6.5 11.5 12.5 4.5" />
                                            </svg>
                                        </span>
                                    </label>
                                </div>
                            </label>
                        @empty
                            <div class="rounded-[1rem] border border-dashed px-5 py-10" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                                <x-ui.empty
                                    :title="$noMatchTitle"
                                    :description="$noMatchDescription"
                                    icon="fa-light fa-magnifying-glass"
                                />
                            </div>
                        @endforelse
                    </div>

                    <div class="flex items-center justify-between gap-4 border-t px-6 py-5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <div>
                            <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $footerDescription }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);" x-text="selectedIds.length === 1 ? '{{ __('1 account ready to add.') }}' : `${selectedIds.length} {{ __('accounts ready to add.') }}`"></p>
                        </div>

                        <x-ui.button type="button" x-on:click="$wire.addSelected(selectedIds)" x-bind:disabled="selectedIds.length === 0" size="lg">
                            <i class="fa-light fa-plus"></i>
                            {{ $submitLabel }}
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.section-card>
        </div>
    @endif
</div>
