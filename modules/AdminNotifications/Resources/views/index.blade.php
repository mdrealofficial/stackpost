@component(theme_view('layouts.app', 'app'), ['title' => __('Notifications')])
    @php
        $createNotification = new \Modules\AdminNotifications\Models\NotificationManual();
        $createNotification->message = old('_notification_modal') === 'create' ? old('message') : null;
        $createNotification->title = old('_notification_modal') === 'create' ? old('title') : null;
        $createNotification->url = old('_notification_modal') === 'create' ? old('url') : null;
        $openCreateModal = $errors->any() && old('_notification_modal') === 'create';
    @endphp

    <div class="space-y-6">
        

        <x-ui.sub-header
            :eyebrow="__('User Portal')"
            :title="__('Notifications')"
            :description="__('Create manual notifications for selected users and manage previously sent announcements.')"
            :count="$summary['total']"
        >
            <x-slot:actions>
                <x-ui.modal
                    width="lg"
                    :title="__('Create notification')"
                    :description="__('Compose a manual announcement and choose which users should receive it.')"
                    :initially-open="$openCreateModal"
                >
                    <x-slot:trigger>
                        <x-ui.button type="button">
                            {{ __('Create notification') }}
                        </x-ui.button>
                    </x-slot:trigger>

                    <form id="create-notification-modal-form" method="POST" action="{{ route('admin-notifications.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="_notification_modal" value="create">

                        @include('adminnotifications::partials.fields', [
                            'notification' => $createNotification,
                            'isEditing' => false,
                            'plainSections' => true,
                            'recipientCount' => 0,
                            'sentAt' => null,
                            'selectedUserOptions' => $selectedUserOptions ?? [],
                        ])
                    </form>

                    <x-slot:footer>
                        <x-ui.button type="button" variant="outline" x-on:click="open = false">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" form="create-notification-modal-form">
                            {{ __('Save changes') }}
                        </x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            </x-slot:actions>
        </x-ui.sub-header>

        <div class="grid gap-5 md:grid-cols-3">
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Total') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['total']) }}</p>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Manual notifications saved in the system.') }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Sent Today') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-emerald-600 dark:text-emerald-300">{{ number_format($summary['sent_today']) }}</p>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('New announcements created on :date.', ['date' => now()->format('Y-m-d')]) }}</p>
            </x-ui.card>
            <x-ui.card class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ __('Delivered') }}</p>
                <p class="text-2xl font-semibold tracking-[-0.04em] text-slate-600 dark:text-slate-300">{{ number_format($summary['recipients']) }}</p>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Total user notification rows generated from manual sends.') }}</p>
            </x-ui.card>
        </div>

        <x-ui.card class="space-y-4">
            <form method="GET" action="{{ route('admin-notifications.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
                <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Title, message, or link...')" />

                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">{{ __('Filter') }}</x-ui.button>
                    <x-ui.button href="{{ route('admin-notifications.index') }}" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.datatable-shell :title="__('Notification history')" :info="__('Manual notifications that were sent to one or more users.')">
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
                        @php
                            $editModalOpen = $errors->any()
                                && old('_notification_modal') === 'edit'
                                && (string) old('_notification_id') === (string) $notification->id;
                            $editNotification = clone $notification;
                            if ($editModalOpen) {
                                $editNotification->title = old('title');
                                $editNotification->message = old('message');
                                $editNotification->url = old('url');
                            }
                        @endphp
                        <x-ui.table-row>
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
                                    <x-ui.dropdown-menu align="right" width="auto">
                                        <x-slot:trigger>
                                            <x-ui.button type="button" variant="outline" size="sm">
                                                {{ __('Actions') }}
                                                <i class="fa-light fa-chevron-down text-[10px]"></i>
                                            </x-ui.button>
                                        </x-slot:trigger>

                                        <x-ui.dropdown-menu-item
                                            class="w-full pr-6"
                                            icon="fa-light fa-paper-plane-top"
                                            x-on:click.stop="open = false; document.getElementById('notification-resend-trigger-{{ $notification->id }}')?.click()"
                                        >
                                            {{ __('Resend') }}
                                        </x-ui.dropdown-menu-item>

                                        <x-ui.dropdown-menu-item
                                            class="w-full pr-6"
                                            icon="fa-light fa-pen-to-square"
                                            x-on:click.stop="open = false; document.getElementById('notification-edit-trigger-{{ $notification->id }}')?.click()"
                                        >
                                            {{ __('Edit') }}
                                        </x-ui.dropdown-menu-item>

                                        <div class="my-1 h-px bg-slate-200"></div>

                                        <x-ui.dropdown-menu-item
                                            class="w-full pr-6 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                                            icon="fa-light fa-trash-can"
                                            x-on:click.stop="open = false; document.getElementById('notification-delete-trigger-{{ $notification->id }}')?.click()"
                                        >
                                            {{ __('Delete') }}
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu>

                                    <x-ui.dialog :title="__('Resend notification?')" :description="__('This will create and send a new notification using the same content and audience as the current one.')" width="sm" dismissible>
                                        <x-slot:trigger>
                                            <button id="notification-resend-trigger-{{ $notification->id }}" type="button" class="hidden"></button>
                                        </x-slot:trigger>

                                        <x-slot:footer>
                                            <div class="flex justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                                    {{ __('Cancel') }}
                                                </x-ui.button>
                                                <form method="POST" action="{{ route('admin-notifications.resend', $notification) }}">
                                                    @csrf
                                                    <x-ui.button type="submit">
                                                        {{ __('Resend') }}
                                                    </x-ui.button>
                                                </form>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                    <x-ui.modal
                                        width="lg"
                                        :title="__('Edit notification')"
                                        :description="__('Update notification content and destination link for an existing manual announcement.')"
                                        :initially-open="$editModalOpen"
                                    >
                                        <x-slot:trigger>
                                            <button id="notification-edit-trigger-{{ $notification->id }}" type="button" class="hidden"></button>
                                        </x-slot:trigger>

                                        <form id="edit-notification-modal-form-{{ $notification->id }}" method="POST" action="{{ route('admin-notifications.update', $notification) }}" class="space-y-5">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="_notification_modal" value="edit">
                                            <input type="hidden" name="_notification_id" value="{{ $notification->id }}">

                                            @include('adminnotifications::partials.fields', [
                                                'notification' => $editNotification,
                                                'isEditing' => true,
                                                'plainSections' => true,
                                                'recipientCount' => $notification->recipientsCount(),
                                                'sentAt' => optional($notification->created_at)->format('Y-m-d H:i'),
                                                'selectedUserOptions' => [],
                                            ])
                                        </form>

                                        <x-slot:footer>
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                                {{ __('Cancel') }}
                                            </x-ui.button>
                                            <x-ui.button type="submit" form="edit-notification-modal-form-{{ $notification->id }}">
                                                {{ __('Save changes') }}
                                            </x-ui.button>
                                        </x-slot:footer>
                                    </x-ui.modal>
                                    <x-ui.dialog :title="__('Delete notification?')" :description="__('This removes the manual notification and all delivered rows linked to it.')" width="sm" dismissible>
                                        <x-slot:trigger>
                                            <button id="notification-delete-trigger-{{ $notification->id }}" type="button" class="hidden"></button>
                                        </x-slot:trigger>

                                        <x-slot:footer>
                                            <div class="flex justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                                    {{ __('Cancel') }}
                                                </x-ui.button>
                                                <form method="POST" action="{{ route('admin-notifications.destroy', $notification) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" variant="danger">
                                                        {{ __('Delete') }}
                                                    </x-ui.button>
                                                </form>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                </div>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No notifications found.') }}
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
    </div>
@endcomponent
