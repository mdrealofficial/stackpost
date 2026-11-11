@component(theme_view('layouts.app', 'app'), ['title' => $isEditing ? __('Edit notification') : __('Create notification')])
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
            

            <form method="POST" action="{{ $isEditing ? route('admin-notifications.update', $notification) : route('admin-notifications.store') }}" class="space-y-4">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif

                @include('adminnotifications::partials.fields')

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
                </div>
            @endif
        </div>
    </div>
@endcomponent
