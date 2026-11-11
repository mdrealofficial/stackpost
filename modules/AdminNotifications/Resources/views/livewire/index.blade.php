@php
    $notificationMetricCards = [
        ['label' => __('Total'), 'value' => number_format($summary['total']), 'description' => __('Manual notifications saved in the system.'), 'tone' => 'var(--theme-accent)', 'progress' => 100],
        ['label' => __('Sent today'), 'value' => number_format($summary['sent_today']), 'description' => __('New announcements created today.'), 'tone' => '#10b981', 'progress' => $summary['sent_today'] > 0 ? 100 : 8],
        ['label' => __('Delivered'), 'value' => number_format($summary['recipients']), 'description' => __('Total notification rows generated from manual sends.'), 'tone' => '#64748b', 'progress' => $summary['recipients'] > 0 ? 100 : 8],
    ];
@endphp

<div class="space-y-6">
    @if ($statusMessage)
        <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('User Portal')"
        :title="__('Notifications')"
        :description="__('Create manual notifications for selected users and manage previously sent announcements.')"
        :count="$summary['total']"
        icon="fa-light fa-bell"
    >
        <x-slot:actions>
            <x-ui.button type="button" wire:click="openCreateModal">{{ __('Create notification') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <x-ui.metric-strip :items="$notificationMetricCards" :show-icons="false" columns="md:grid-cols-3" />

    <x-ui.filter-panel fields-class="xl:grid-cols-[minmax(0,1fr)_auto]">
        <x-slot:fields>
            <x-ui.input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Title, message, or link...')" />
        </x-slot:fields>

        <x-slot:actions>
            <x-ui.button type="button" wire:click="$refresh">{{ __('Filter') }}</x-ui.button>
            <x-ui.button type="button" variant="outline" wire:click="resetFilters">{{ __('Reset') }}</x-ui.button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($search !== '')
                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $search }}</span>
            @endif
        </x-slot:chips>
    </x-ui.filter-panel>

    <x-ui.datatable-shell
        :title="__('Notification history')"
        :info="__('Manual notifications that were sent to one or more users.')"
        header-class="py-4"
        eyebrow-class="text-[10px] tracking-[0.2em]"
        title-class="mt-1 text-[1.15rem] tracking-[-0.025em]"
        description-class="mt-1 leading-6"
    >
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-ui.table-head>
                <x-ui.table-cell head>{{ __('Notification') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Recipients') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created By') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Created') }}</x-ui.table-cell>
                <x-ui.table-cell head>{{ __('Actions') }}</x-ui.table-cell>
            </x-ui.table-head>
            <x-ui.table-body>
                @forelse ($notifications as $notification)
                    <x-ui.table-row wire:key="notification-row-{{ $notification->id }}">
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium leading-6" style="color: var(--theme-header-text-color);">{{ $notification->title ?: __('Notification') }}</p>
                                    @if ($notification->is_global)
                                        <x-ui.badge variant="primary">{{ __('Global') }}</x-ui.badge>
                                    @endif
                                </div>
                                <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $notification->excerpt() }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <x-ui.badge variant="primary">{{ number_format($notification->recipientsCount()) }}</x-ui.badge>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <p class="text-sm" style="color: var(--theme-header-text-color);">{{ $notification->creator?->name ?: __('System') }}</p>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="space-y-1">
                                <p class="text-sm" style="color: var(--theme-header-text-color);">{{ optional($notification->created_at)->format('Y-m-d H:i') ?: __('N/A') }}</p>
                                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ optional($notification->created_at)->diffForHumans() }}</p>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center justify-end gap-2 pr-4">
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="resend({{ $notification->id }})">{{ __('Resend') }}</x-ui.button>
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="openEditModal({{ $notification->id }})">{{ __('Edit') }}</x-ui.button>
                                <x-ui.dialog wire:key="notification-delete-dialog-{{ $notification->id }}" :title="__('Delete notification?')" :description="__('This removes the manual notification and all delivered rows linked to it.')" width="sm" dismissible>
                                    <x-slot:trigger><x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button></x-slot:trigger>
                                    <x-slot:footer>
                                        <div class="flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" variant="danger" wire:click="delete({{ $notification->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.dialog>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="5" class="py-10">
                            <x-ui.empty icon="fa-light fa-bell" :title="__('No notifications found.')" :description="__('Create the first manual notification to start your announcement history.')" />
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        @if ($notifications->hasPages())
            <div class="border-t px-6 py-4" style="border-color: var(--theme-border-color);">
                {{ $notifications->links() }}
            </div>
        @endif
    </x-ui.datatable-shell>

    <x-ui.modal
        width="xl"
        open-event="notification-modal-open"
        close-event="notification-modal-close"
        :title="$isEditing ? __('Edit notification') : __('Create notification')"
        :description="$isEditing ? __('Update notification content and destination link for an existing manual announcement.') : __('Compose a manual announcement and choose which users should receive it.')"
        body-class="space-y-6 overflow-visible max-h-none px-6 py-6"
    >
        <form id="notification-modal-form" wire:submit="save" class="space-y-6">
            <section class="space-y-5">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Notification content') }}</h3>
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Write the notification title, body, and optional destination link.') }}</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model="title" :label="__('Title')" :error="$errors->first('title')" :placeholder="__('Payment was successful')" />
                    <x-ui.input wire:model="url" :label="__('Link')" :error="$errors->first('url')" :placeholder="__('https://example.com/upgrade')" />
                </div>

                <x-ui.textarea wire:model="message" :label="__('Message')" :error="$errors->first('message')" rows="8"></x-ui.textarea>
            </section>

            @if (! $isEditing)
                <section class="space-y-5 border-t pt-5" style="border-color: var(--theme-border-color);">
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recipients') }}</h3>
                        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Choose one or more users, or send globally to every user.') }}</p>
                    </div>

                    <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                        <x-ui.checkbox
                            wire:model.live="is_global"
                            value="1"
                            :label="__('Global notification')"
                            :description="__('Enable this to send one notification to every user in the system.')"
                        />
                    </div>

                    <div class="space-y-3 {{ $is_global ? 'opacity-50' : '' }}">
                        <x-ui.input wire:model.live.debounce.300ms="userQuery" :label="__('Search users')" :placeholder="__('Search by name, email, or username...')" />

                        @if ($selectedUsers->isNotEmpty())
                            <div class="flex flex-wrap gap-2 rounded-[0.9rem] border px-3 py-3" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                                @foreach ($selectedUsers as $user)
                                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color)); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);" wire:key="notification-selected-user-{{ $user->id }}">
                                        <span>{{ trim($user->name.' <'.$user->email.'>') }}</span>
                                        <button type="button" class="text-slate-400 transition hover:text-slate-700" wire:click="removeUser('{{ $user->id }}')">
                                            <i class="fa-light fa-xmark"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="rounded-[0.9rem] border" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <div class="max-h-56 overflow-y-auto">
                                @if ($is_global)
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Recipient selection is disabled because global notification is enabled.') }}</div>
                                @else
                                    @forelse ($userResults as $user)
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 border-b px-4 py-3 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                            style="border-color: color-mix(in srgb, var(--theme-border-color) 42%, transparent); color: var(--theme-header-text-color);"
                                            wire:key="notification-user-result-{{ $user->id }}"
                                            wire:click="addUser('{{ $user->id }}')"
                                        >
                                            <span class="truncate">{{ trim($user->name.' <'.$user->email.'>') }}</span>
                                        </button>
                                    @empty
                                        <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No matching users found.') }}</div>
                                    @endforelse
                                @endif
                            </div>
                        </div>

                        @if ($errors->first('user_ids'))
                            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('user_ids') }}</p>
                        @endif
                    </div>
                </section>
            @else
                <section class="space-y-5 border-t pt-5" style="border-color: var(--theme-border-color);">
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Delivery details') }}</h3>
                        <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ __('Recipient assignments are locked after the notification has been created.') }}</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: color-mix(in srgb, var(--theme-surface-base) 88%, white 12%);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Recipients') }}</p>
                            <p class="mt-1 text-xl font-semibold leading-tight" style="color: var(--theme-header-text-color);">{{ number_format($recipientCount) }}</p>
                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Users assigned') }}</p>
                        </div>
                        <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: color-mix(in srgb, var(--theme-surface-base) 88%, white 12%);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Sent at') }}</p>
                            <p class="mt-1 text-sm font-semibold leading-6 break-words" style="color: var(--theme-header-text-color);">{{ $sentAt ?: __('N/A') }}</p>
                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Created timestamp') }}</p>
                        </div>
                        <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background: color-mix(in srgb, var(--theme-surface-base) 88%, white 12%);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Audience') }}</p>
                            <p class="mt-1 text-sm font-semibold leading-6" style="color: var(--theme-header-text-color);">{{ __('Locked after send') }}</p>
                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Recipients cannot be changed') }}</p>
                        </div>
                    </div>
                </section>
            @endif
        </form>

        <x-slot:footer>
            <div class="flex items-center justify-between gap-3">
                <div>
                    @if ($isEditing)
                        <x-ui.dialog :title="__('Delete notification?')" :description="__('This removes the notification record and all user delivery rows tied to it.')" width="sm" dismissible>
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="danger">{{ __('Delete notification') }}</x-ui.button>
                            </x-slot:trigger>
                            <x-slot:footer>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="button" variant="danger" wire:click="delete({{ $notification?->id ?? 0 }})" x-on:click="$dispatch('notification-modal-close'); open = false">{{ __('Delete') }}</x-ui.button>
                                </div>
                            </x-slot:footer>
                        </x-ui.dialog>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="$dispatch('notification-modal-close')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" form="notification-modal-form">{{ $isEditing ? __('Save changes') : __('Create notification') }}</x-ui.button>
                </div>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
