<div class="mx-auto flex w-full max-w-[1680px] flex-col gap-4 px-4 py-5 sm:px-5 xl:px-6">
    <x-ui.sub-header :compact="true" :title="__('Queue')" :description="__('Track scheduled posts and resolve delivery issues.')"><x-slot:actions>
                <x-ui.button :href="route('portal.publishing.calendar')" variant="outline" wire:navigate>
                    {{ __('Calendar') }}
                </x-ui.button>
                <x-ui.button :href="route('portal.publishing.drafts')" variant="outline" wire:navigate>
                    {{ __('Drafts') }}
                </x-ui.button>
                <x-ui.button :href="route('portal.publishing.calendar', ['compose' => 1])" variant="primary" wire:navigate>
                    {{ __('New Post') }}
                </x-ui.button>
            </x-slot:actions></x-ui.sub-header>

    <x-ui.filter-panel :compact="true" fieldsClass="lg:items-end lg:grid-cols-[minmax(0,1fr)_180px_180px_auto]">
            <div class="min-w-0">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Queue search') }}</p>
                <x-ui.icon-input
                    wire:model.live.debounce.300ms="search"
                    icon="fa-light fa-magnifying-glass"
                    :placeholder="__('Search by title, caption, or queue id...')"
                    wrapperClass="mt-2"
                   
                />
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Network') }}</p>
                <div class="mt-2">
                    <x-ui.select wire:model.live="providerFilter" :aria-label="__('Network')">
                        <option value="all">{{ __('All networks') }}</option>
                        @foreach ($providerFilters as $provider)
                            <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Queue status') }}</p>
                <div class="mt-2">
                    <x-ui.select wire:model.live="statusFilter" :aria-label="__('Status')">
                        <option value="all">{{ __('All statuses') }}</option>
                        <option value="scheduled">{{ __('Queued') }}</option>
                        <option value="processing">{{ __('Processing') }}</option>
                        <option value="failed">{{ __('Failed') }}</option>
                    </x-ui.select>
                </div>
            </div>

            <x-ui.button type="button" variant="outline" wire:click="clearFilters">
                <i class="fa-light fa-rotate-left mr-2"></i>
                {{ __('Reset') }}
            </x-ui.button>
        </x-ui.filter-panel>

    @php
        $queueMetrics = [
            ['label' => __('Queue total'), 'value' => number_format($summary['total']), 'description' => __('Posts in the publishing queue'), 'icon' => 'fa-light fa-layer-group', 'tone' => '#0d9488', 'iconSurface' => 'rgba(13,148,136,.12)', 'spark' => 'M2 24 C10 24 12 14 20 18 S28 29 36 20 S44 10 52 16 S58 18 62 12'],
            ['label' => __('Queued'), 'value' => number_format($summary['scheduled']), 'description' => __('Waiting for their publish time'), 'icon' => 'fa-light fa-clock', 'tone' => '#16856f', 'iconSurface' => 'rgba(22,133,111,.11)', 'spark' => 'M2 20 C10 20 12 26 20 22 S29 10 36 17 S44 25 52 16 S58 17 62 13'],
            ['label' => __('Processing'), 'value' => number_format($summary['processing']), 'description' => __('Being delivered now'), 'icon' => 'fa-light fa-spinner', 'tone' => '#7c3aed', 'iconSurface' => 'rgba(124,58,237,.10)', 'spark' => 'M2 21 C10 21 12 27 20 22 S29 10 36 17 S44 26 52 16 S58 18 62 13'],
            ['label' => __('Failed'), 'value' => number_format($summary['failed']), 'description' => __('Items needing attention'), 'icon' => 'fa-light fa-circle-exclamation', 'tone' => '#b7791f', 'iconSurface' => 'rgba(183,121,31,.12)', 'spark' => 'M2 18 C10 18 13 20 20 20 S29 18 36 20 S45 18 52 20 S58 18 62 18'],
        ];
    @endphp
    <x-ui.metric-strip :items="$queueMetrics" columns="grid-cols-2 lg:grid-cols-4" gap="gap-4" variant="portal" :show-progress="false" aria-label="{{ __('Queue summary') }}" />

    <x-ui.card padding="sm">
        @if ($queueCards->isEmpty())
            <div class="flex min-h-[18rem] items-center justify-center rounded-[1.4rem] border border-dashed px-6 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                <div class="max-w-md">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[1.15rem]" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(59, 130, 246, 0.04)); color: #2563eb;">
                        <i class="fa-light fa-list-check text-xl"></i>
                    </div>
                    <p class="mt-4 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Queue is empty') }}</p>
                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Scheduled and processing posts will appear here once they enter the active publishing queue.') }}</p>
                </div>
            </div>
        @endif

        @if ($queueCards->isNotEmpty())
            @php
                $visibleQueueIds = $queueCards->pluck('id')->values()->all();
                $selectedQueueIds = collect($selectedQueueIds ?? [])->map(fn ($id) => (string) $id)->values();
            @endphp
            <div
                wire:key="queue-selection-{{ md5(json_encode($visibleQueueIds)) }}"
                class="mb-4 flex flex-wrap items-center justify-between gap-3"
                x-data="{
                    visibleQueueIds: @js($visibleQueueIds),
                    selectedQueueIds: @js($selectedQueueIds->all()),
                    selectPage: false,
                    init() {
                        this.syncSelectPage();
                        this.$watch('selectedQueueIds', () => this.syncSelectPage());
                    },
                    allVisibleSelected() {
                        return this.visibleQueueIds.length > 0 && this.visibleQueueIds.every((id) => this.selectedQueueIds.includes(String(id)));
                    },
                    syncSelectPage() {
                        this.selectPage = this.allVisibleSelected();
                    },
                    toggleRow(id) {
                        id = String(id);

                        if (this.selectedQueueIds.includes(id)) {
                            this.selectedQueueIds = this.selectedQueueIds.filter((value) => value !== id);
                        } else {
                            this.selectedQueueIds = [...this.selectedQueueIds, id];
                        }

                        this.syncSelectPage();
                    },
                    toggleVisibleSelection(checked = null) {
                        const shouldSelect = typeof checked === 'boolean' ? checked : !this.allVisibleSelected();

                        if (!shouldSelect) {
                            this.selectedQueueIds = this.selectedQueueIds.filter((id) => !this.visibleQueueIds.includes(String(id)));
                        } else {
                            const merged = new Set([...(this.selectedQueueIds || []), ...this.visibleQueueIds.map(String)]);
                            this.selectedQueueIds = Array.from(merged);
                        }

                        this.syncSelectPage();
                    },
                }"
                x-on:queue-selection-cleared.window="selectedQueueIds = []; selectPage = false"
                x-effect="$wire.set('selectedQueueIds', Array.isArray(selectedQueueIds) ? selectedQueueIds : [], false)"
            >
                <div class="text-sm" style="color: var(--theme-muted-text-color);">
                    <span x-text="selectedQueueIds.length === 1 ? @js(__('1 item selected')) : `${selectedQueueIds.length} {{ __('items selected') }}`"></span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button
                        type="button"
                        x-on:click="toggleVisibleSelection()"
                        data-no-loading
                       
                       
                     variant="outline" size="sm">
                        <i class="fa-light" x-bind:class="allVisibleSelected() ? 'fa-square-minus' : 'fa-square-check'"></i>
                        <span x-text="allVisibleSelected() ? @js(__('Clear page selection')) : @js(__('Select this page'))"></span>
                    </x-ui.button>
                    <x-ui.dialog :title="__('Delete selected queue items?')" :description="__('This will permanently remove all selected queue items.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button
                                type="button"
                                x-bind:disabled="selectedQueueIds.length === 0"
                                data-no-loading
                               
                               
                             variant="outline" size="sm">
                                <i class="fa-light fa-trash-can"></i>
                                <span x-text="selectedQueueIds.length === 1 ? @js(__('Delete selected (1)')) : `{{ __('Delete selected') }} (${selectedQueueIds.length})`"></span>
                            </x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                    {{ __('Cancel') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="danger" x-on:click="$wire.deleteSelectedQueueItems(selectedQueueIds); open = false">
                                    {{ __('Delete selected') }}
                                </x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </div>

                <div class="overflow-hidden rounded-[1.35rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.58);">
                    <div class="overflow-x-auto">
                        <x-ui.table :responsive="true"><thead><tr><th>
                                    <x-ui.checkbox
                                        minimal
                                        :checked="false"
                                        x-model="selectPage"
                                        x-on:change="toggleVisibleSelection($event.target.checked)"
                                        data-no-loading
                                    />
                                </th><th>{{ __('Channel') }}</th><th>{{ __('Post') }}</th><th>{{ __('Scheduled') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>@foreach ($queueCards as $item)
@php($providerChipStyle = publishing_provider_chip_style($item['provider_key']))
<tr><td>
                                        <x-ui.checkbox
                                            minimal
                                            :checked="false"
                                            value="{{ $item['id'] }}"
                                            x-model="selectedQueueIds"
                                            data-no-loading
                                        />
                                    </td><td data-label="{{ __('Channel') }}">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs" style="{{ $providerChipStyle }}">
                                                    <i class="{{ $item['provider_icon'] }}"></i>
                                                </span>
                                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="{{ $providerChipStyle }}">
                                                    {{ $item['provider_label'] }}
                                                </span>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ $item['channel'] }}</p>
                                            @if ($item['handle'])
                                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $item['handle'] }}</p>
                                            @endif
                                            
                                        </div>
                                    </td><td data-label="{{ __('Post') }}">
                                        <div class="flex min-w-0 items-start gap-2">
                                            @if ($item['media_preview'])
                                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-[0.95rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: rgba(var(--theme-border-color-rgb), 0.06);">
                                                    @if (str_starts_with(strtolower($item['media_mime']), 'video/'))
                                                        <video src="{{ $item['media_preview'] }}" muted playsinline preload="metadata" class="h-full w-full object-cover"></video>
                                                    @else
                                                        <img src="{{ $item['media_preview'] }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                                    @endif
                                                </div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[15px] font-semibold leading-6" style="color: var(--theme-header-text-color); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    {{ $item['title'] ?: __('Untitled post') }}
                                                </p>
                                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                                    {{ $item['caption'] !== '' ? $item['caption'] : __('No caption written yet.') }}
                                                </p>
                                            </div>
                                        </div>
                                    <div class="mt-2 text-xs" style="color:var(--theme-muted-text-color)">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $item['media_type'] }}</p>
                                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ trans_choice(':count file|:count files', $item['media_count'], ['count' => $item['media_count']]) }}</p>
                                        </div>
                                    </div></td><td data-label="{{ __('Scheduled') }}">
                                        <div class="min-w-0 text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                            {{ optional($item['scheduled_at'])->format('d/m/Y H:i') ?: '--' }}
                                        </div>
                                    <details class="mt-2 text-xs"><summary class="cursor-pointer">{{ __('Updated') }}</summary>
                                        <div class="min-w-0 text-sm" style="color: var(--theme-header-text-color);">
                                            {{ $item['updated_at'] ? $item['updated_at']->format('d/m/Y H:i') : __('Not available') }}
                                        </div>
                                    </details></td><td data-label="{{ __('Status') }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="{{ $item['status_style'] }}">
                                            {{ $item['status_label'] }}
                                        </span>
                                    @if($item['error'] !== '')<details class="mt-2"><summary class="cursor-pointer text-xs">{{ __('Review issue') }}</summary>
                                        <div class="min-w-0 min-w-0 text-sm leading-6" style="color: {{ $item['error'] !== '' ? '#b91c1c' : 'var(--theme-muted-text-color)' }};">
                                            @if($item['error'] !== '')<strong>{{ __('Delivery needs attention') }}</strong><p>{{ __('Review the post content and channel connection.') }}</p><details class="mt-2"><summary class="cursor-pointer text-xs">{{ __('Technical details') }}</summary><p class="mt-2 break-words text-xs">{{ $item['error'] }}</p></details>@else{{ __('No issues') }}@endif
                                        </div>
                                    </details>@endif</td><td data-label="{{ __('Actions') }}">
                                        <div class="flex flex-wrap min-w-0 justify-end gap-2">
                                            @if ($item['can_edit'])
                                                <x-ui.button :href="route('portal.publishing.calendar', ['edit' => $item['id']])" variant="primary" size="sm" class="min-w-0 justify-center" wire:navigate>
                                                    <i class="fa-light fa-pen-to-square"></i>
                                                    {{ __('Edit') }}
                                                </x-ui.button>
                                            @endif
                                            <x-ui.dialog :title="__('Delete this queue item?')" :description="__('This will permanently remove the selected queue item.')" width="sm" dismissible>
                                                <x-slot:trigger>
                                                    <x-ui.button
                                                        type="button"
                                                       
                                                       
                                                        data-no-loading
                                                     variant="outline" size="sm">
                                                        <i class="fa-light fa-trash-can"></i>
                                                        {{ __('Delete') }}
                                                    </x-ui.button>
                                                </x-slot:trigger>
                                                <x-slot:footer>
                                                    <div class="flex items-center justify-end gap-3">
                                                        <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                                            {{ __('Cancel') }}
                                                        </x-ui.button>
                                                        <x-ui.button type="button" variant="danger" wire:click="deleteQueueItem('{{ $item['id'] }}')" x-on:click="open = false">
                                                            {{ __('Delete') }}
                                                        </x-ui.button>
                                                    </div>
                                                </x-slot:footer>
                                            </x-ui.dialog>
                                        </div>
                                    </td></tr>
@endforeach</tbody></x-ui.table>
                </div>
            </div>

            <div class="mt-5">
                {{ $items->links() }}
            </div>
            </div>
        @endif
    </x-ui.card>
</div>
