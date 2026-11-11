<div class="space-y-6">
    <x-ui.sub-header
        :eyebrow="__('User Portal')"
        :title="$isEditing ? __('Edit notification') : __('Create notification')"
        :description="$isEditing ? __('Update notification content and destination link for an existing manual announcement.') : __('Compose a manual announcement and choose which users should receive it.')"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-notifications.index') }}" variant="outline" wire:navigate>
                {{ __('Back') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.sub-header>

    <div class="mx-auto max-w-[960px] pb-8">
        @if ($statusMessage)
            <x-ui.alert variant="success" :title="__('Saved')" :description="$statusMessage" class="mb-4" />
        @endif

        <form wire:submit="save" class="space-y-4">
            <x-ui.form-section :title="__('Notification content')">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input wire:model="title" :label="__('Title')" :error="$errors->first('title')" :placeholder="__('Payment was successful')" />
                    <x-ui.input wire:model="url" :label="__('Link')" :error="$errors->first('url')" :placeholder="__('https://example.com/upgrade')" />
                </div>

                <x-ui.textarea wire:model="message" :label="__('Message')" :error="$errors->first('message')" rows="10"></x-ui.textarea>
            </x-ui.form-section>

            @if (! $isEditing)
                <x-ui.form-section :title="__('Recipients')">
                    <div class="space-y-4">
                        <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                            <label class="inline-flex items-center gap-3">
                                <input type="checkbox" wire:model.live="is_global" class="h-4 w-4 rounded border-slate-300 text-[var(--theme-accent)] focus:ring-[var(--theme-accent)]">
                                <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Global notification') }}</span>
                            </label>
                            <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Enable this to send one notification to every user in the system.') }}</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Send to users') }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search by name, email, or username and add one or more recipients.') }}</p>
                                </div>
                                <span class="text-xs font-medium uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">
                                    {{ $is_global ? __('Global') : (count($user_ids) ? count($user_ids).' '.__('selected') : __('No users selected')) }}
                                </span>
                            </div>

                            <div class="space-y-3 {{ $is_global ? 'opacity-50' : '' }}">
                                <x-ui.input wire:model.live.debounce.300ms="userQuery" :label="__('Search users')" :placeholder="__('Search users...')" />

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
                            </div>
                        </div>

                        @if ($errors->first('user_ids'))
                            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('user_ids') }}</p>
                        @endif
                    </div>
                </x-ui.form-section>
            @else
                <x-ui.form-section :title="__('Delivery details')">
                    <div class="grid gap-5 md:grid-cols-3">
                        <x-ui.card class="space-y-1 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Recipients') }}</p>
                            <p class="text-xl font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($recipientCount) }}</p>
                        </x-ui.card>
                        <x-ui.card class="space-y-1 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Sent At') }}</p>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $sentAt ?: __('N/A') }}</p>
                        </x-ui.card>
                        <x-ui.card class="space-y-1 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Audience') }}</p>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recipients are locked after sending.') }}</p>
                        </x-ui.card>
                    </div>
                </x-ui.form-section>
            @endif

            <div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-[0.5rem] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background-color: #1f2430;">
                    {{ __('Save changes') }}
                </button>
            </div>
        </form>

        @if ($isEditing)
            <div class="mt-4 overflow-hidden rounded-[0.75rem] border shadow-[0_2px_10px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                <div class="border-b px-6 py-5" style="border-color: var(--theme-border-color);">
                    <h3 class="text-[1.125rem] font-semibold" style="color: var(--theme-header-text-color);">{{ __('Danger zone') }}</h3>
                </div>
                <div class="space-y-4 px-6 py-6">
                    <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Delete this notification if it should no longer exist in the notification history.') }}</p>
                    <x-ui.dialog :title="__('Delete notification?')" :description="__('This removes the notification record and all user delivery rows tied to it.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger">
                                {{ __('Delete notification') }}
                            </x-ui.button>
                        </x-slot:trigger>

                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">
                                    {{ __('Cancel') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="delete" x-on:click="open = false">
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </div>
            </div>
        @endif
    </div>
</div>
