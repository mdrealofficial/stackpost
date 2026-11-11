@php
    $widthClasses = [
        'compact' => 'lg:col-span-4',
        'half' => 'lg:col-span-6',
        'wide' => 'lg:col-span-8',
        'full' => 'lg:col-span-12',
    ];
@endphp

<div
    class="app-dashboard min-w-0 max-w-full space-y-7 lg:space-y-8"
    x-data="{
        draggingId: null,
        saveTimeout: null,
        saving: false,
        saved: false,
        startDrag(event) {
            const card = event.currentTarget;
            this.draggingId = card.dataset.dashboardId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', this.draggingId);
            card.classList.add('opacity-60');
        },
        dragOver(event) {
            const target = event.currentTarget;
            const sourceId = this.draggingId;

            if (!sourceId || sourceId === target.dataset.dashboardId) {
                return;
            }

            const board = this.$refs.board;
            const source = board.querySelector(`[data-dashboard-id='${sourceId}']`);

            if (!source || !target || source === target) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;

            if (before) {
                board.insertBefore(source, target);
            } else {
                board.insertBefore(source, target.nextSibling);
            }
        },
        endDrag(event) {
            event.currentTarget.classList.remove('opacity-60');
            this.draggingId = null;
            this.persist();
        },
        persist() {
            clearTimeout(this.saveTimeout);

            this.saveTimeout = setTimeout(async () => {
                const itemIds = Array.from(this.$refs.board.querySelectorAll('[data-dashboard-id]'))
                    .map((element) => element.dataset.dashboardId);

                this.saving = true;
                this.saved = false;

                await this.$wire.saveLayout(itemIds);

                this.saving = false;
                this.saved = true;

                setTimeout(() => {
                    this.saved = false;
                }, 1600);
            }, 180);
        },
    }"
>
    @include(theme_view('livewire.portal.partials.dashboard-styles', 'app'))
    <div class="absolute right-8 top-2 flex items-center justify-end">
        <span
            x-cloak
            x-show="saving || saved"
            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
            style="background: rgba(var(--theme-accent-rgb,37,99,235),0.12); color: var(--theme-accent,#2563eb);"
        >
            <span x-show="saving">{{ __('Saving layout...') }}</span>
            <span x-show="saved">{{ __('Layout saved') }}</span>
        </span>
    </div>

    @if (($welcomeItems ?? []) !== [])
        <div class="min-w-0 max-w-full space-y-7 lg:space-y-8">
            @foreach ($welcomeItems as $item)
                <section wire:key="dashboard-welcome-{{ $item['id'] }}" class="min-w-0 max-w-full">
                    {!! $item['content'] !!}
                </section>
            @endforeach
        </div>
    @endif

    @if ($dashboardItems === [])
        <x-ui.empty
            :title="__('Your workspace is ready')"
            :description="__('Your available workspace tools will appear here.')"
        />
    @else
        <div
            x-ref="board"
            class="app-dashboard-board min-w-0 max-w-full grid gap-5 lg:grid-cols-12 lg:gap-6"
        >
            @foreach ($dashboardItems as $item)
                <section
                    draggable="true"
                    wire:key="dashboard-item-{{ $item['id'] }}"
                    data-dashboard-id="{{ $item['id'] }}"
                    class="group relative min-w-0 max-w-full {{ $widthClasses[$item['width'] ?? 'half'] ?? $widthClasses['half'] }}"
                    x-on:dragstart="startDrag($event)"
                    x-on:dragover.prevent="dragOver($event)"
                    x-on:dragend="endDrag($event)"
                >
                    <div class="pointer-events-none absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border bg-white/90 text-slate-400 opacity-0 shadow-sm transition group-hover:opacity-100" style="border-color: var(--theme-border-color);">
                        <i class="fa-light fa-grip-dots text-sm"></i>
                    </div>

                    {!! $item['content'] !!}
                </section>
            @endforeach
        </div>
    @endif
</div>
