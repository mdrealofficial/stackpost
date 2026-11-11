@php
    $metricCards = [
        ['label' => __('Open'), 'value' => $summary['open'], 'description' => __('Tickets waiting for support action.'), 'icon' => 'fa-light fa-inbox', 'tone' => 'rgb(37, 99, 235)', 'iconSurface' => 'rgba(37, 99, 235, 0.10)'],
        ['label' => __('Unread'), 'value' => $summary['unread'], 'description' => __('Replies you have not reviewed yet.'), 'icon' => 'fa-light fa-envelope', 'tone' => 'rgb(217, 119, 6)', 'iconSurface' => 'rgba(217, 119, 6, 0.12)'],
        ['label' => __('Resolved'), 'value' => $summary['resolved'], 'description' => __('Tickets completed by your team.'), 'icon' => 'fa-light fa-circle-check', 'tone' => 'rgb(5, 150, 105)', 'iconSurface' => 'rgba(5, 150, 105, 0.10)'],
        ['label' => __('Total tickets'), 'value' => $summary['total'], 'description' => __('All support requests in this workspace.'), 'icon' => 'fa-light fa-ticket', 'tone' => 'rgb(79, 70, 229)', 'iconSurface' => 'rgba(79, 70, 229, 0.10)'],
    ];
@endphp

<div class="portal-tools space-y-5">@include('partials.portal-tools-style')<x-ui.sub-header compact :title="__('Support')" :description="__('Track your requests and replies from the support team.')"><x-slot:actions><x-ui.modal width="lg" :title="__('New support ticket')" :description="__('Describe your issue clearly so the team can respond with the right context.')">
                    <x-slot:trigger>
                        <x-ui.button type="button">
                            <i class="fa-light fa-plus"></i>
                            {{ __('Create ticket') }}
                        </x-ui.button>
                    </x-slot:trigger>

                    <form id="portal-support-create-form" wire:submit="createTicket" class="space-y-5">
                        <x-ui.select wire:model.defer="cateId" name="cateId" :label="__('Category')" :error="$errors->first('cateId')">
                            <option value="">{{ __('Select category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input wire:model.defer="title" name="title" :label="__('Subject')" :error="$errors->first('title')" :placeholder="__('Brief summary of the issue')" required />
                        <x-ui.textarea wire:model.defer="content" name="content" :label="__('Details')" :error="$errors->first('content')" rows="6" :placeholder="__('Explain what happened, what you expected, and any steps to reproduce the issue.')" />
                    </form>

                    <x-slot:footer>
                        <x-ui.button type="button" variant="outline" x-on:click="open = false">
                            <i class="fa-light fa-xmark"></i>
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" form="portal-support-create-form">
                            <i class="fa-light fa-paper-plane-top"></i>
                            {{ __('Create ticket') }}
                        </x-ui.button>
                    </x-slot:footer>
                </x-ui.modal></x-slot:actions></x-ui.sub-header><x-ui.metric-strip :items="$metricCards" columns="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4" gap="gap-5" variant="portal" :show-progress="false" aria-label="{{ __('Support ticket summary') }}" /><form wire:submit="filterTickets" class="portal-tool-panel"><div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_auto]"><x-ui.input wire:model="search" :label="__('Search')" :placeholder="__('Ticket, subject, or details')" /><x-ui.select wire:model="statusFilter" :label="__('Status')"><option value="all">{{ __('All tickets') }}</option><option value="1">{{ __('Open') }}</option><option value="2">{{ __('Resolved') }}</option><option value="3">{{ __('Closed') }}</option></x-ui.select><div class="portal-tool-actions"><x-ui.button type="submit">{{ __('Filter') }}</x-ui.button><x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button></div></div></form><x-ui.datatable-shell :title="__('Your tickets')"><x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Ticket') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Queue') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Updated') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($tickets as $ticket)
                    <x-ui.table-row>
                        <x-ui.table-cell>
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                                        <i class="fa-light fa-ticket-simple"></i>
                                    </span>
                                    <p class="text-[1rem] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $ticket->title }}</p>
                                    @if ($ticket->user_read)
                                        <x-ui.badge variant="danger" class="px-2 py-0.5 text-[10px] tracking-[0.16em]">{{ __('Unread') }}</x-ui.badge>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--theme-muted-text-color);">
                                    <span class="font-mono">#{{ $ticket->id }}</span>
                                    <span class="inline-flex h-1 w-1 rounded-full" style="background-color: color-mix(in srgb, var(--theme-muted-text-color) 38%, transparent);"></span>
                                    <span>{{ $ticket->comments_count }} {{ __('replies') }}</span>
                                </div>

                                <p class="max-w-[42rem] text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $ticket->excerpt() }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold" style="border-color: color-mix(in srgb, {{ $ticket->category?->color ?: 'var(--theme-accent)' }} 18%, var(--theme-border-color)); color: {{ $ticket->category?->color ?: 'var(--theme-accent)' }}; background-color: color-mix(in srgb, {{ $ticket->category?->color ?: 'var(--theme-accent)' }} 8%, transparent);">
                                    @if ($ticket->category?->icon)
                                        <i class="{{ $ticket->category->icon }}"></i>
                                    @endif
                                    {{ $ticket->category?->name ?: __('Unassigned') }}
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold" style="border-color: color-mix(in srgb, {{ $ticket->type?->color ?: 'var(--theme-border-color)' }} 18%, var(--theme-border-color)); color: {{ $ticket->type?->color ?: 'var(--theme-muted-text-color)' }}; background-color: color-mix(in srgb, {{ $ticket->type?->color ?: 'var(--theme-border-color)' }} 7%, transparent);">
                                    @if ($ticket->type?->icon)
                                        <i class="{{ $ticket->type->icon }}"></i>
                                    @endif
                                    {{ $ticket->type?->name ?: __('No type') }}
                                </span>
                                <x-ui.badge :variant="$ticket->statusVariant()">{{ $ticket->statusLabel() }}</x-ui.badge>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ $ticket->changedAtFormatted() ?: __('N/A') }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $ticket->changedFromHuman() ?: __('No activity') }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex flex-wrap items-center gap-2 justify-end">
                                <x-ui.button href="{{ route('portal.support.show', $ticket) }}" variant="outline" size="sm" wire:navigate>
                                    <i class="fa-light fa-eye"></i>
                                    {{ __('View') }}
                                </x-ui.button>
                                @if ((int) $ticket->status === 1)
                                    <form wire:submit="resolveTicket({{ $ticket->id }})">
                                        <x-ui.button type="submit" size="sm">
                                            <i class="fa-light fa-circle-check"></i>
                                            {{ __('Resolve') }}
                                        </x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="4" class="py-10"><x-ui.empty icon="fa-light fa-headset" :title="__('No support tickets found.')" :description="__('Create your first ticket to start a support thread with the admin team.')" /></x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table><div class="tool-pagination">{{ $tickets->links() }}</div></x-ui.datatable-shell></div>
