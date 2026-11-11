@php($unreadNotifications = $notifications->filter(fn ($notification) => is_null($notification->read_at))->values())

<div class="border-b px-4 pt-3">
    <div class="flex items-center gap-5 text-sm font-semibold">
        <button type="button" class="relative pb-3 transition" :class="tab === 'unread' ? 'text-[var(--theme-accent)]' : 'text-slate-500'" x-on:click="tab = 'unread'">
            {{ __('Unread') }}
            @if ($unreadCount > 0)
                <span class="ml-2 inline-flex min-w-6 items-center justify-center rounded-md bg-rose-500 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $unreadCount }}</span>
            @endif
            <span x-show="tab === 'unread'" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-[var(--theme-accent)]"></span>
        </button>
        <button type="button" class="relative pb-3 transition" :class="tab === 'all' ? 'text-[var(--theme-accent)]' : 'text-slate-500'" x-on:click="tab = 'all'">
            {{ __('All') }}
            <span x-show="tab === 'all'" class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-[var(--theme-accent)]"></span>
        </button>
    </div>
</div>

<div class="max-h-[calc(100vh-14rem)] overflow-y-auto sm:max-h-[26rem]" x-show="tab === 'unread'">
    @forelse ($unreadNotifications as $notification)
        <div class="border-b px-4 py-4 last:border-b-0" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md bg-slate-900 px-2 py-0.5 text-[11px] font-semibold text-white dark:bg-slate-100 dark:text-slate-900">{{ __('New') }}</span>
                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $notification->resolvedTitle() }}</p>
                    </div>
                    <div class="text-sm leading-7" style="color: var(--theme-header-text-color);">{!! nl2br(e($notification->resolvedMessage())) !!}</div>
                    @if ($notification->url)
                        <a href="{{ $notification->url }}" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--theme-accent)]" target="_blank">
                            {{ __('Open link') }}
                            <i class="fa-light fa-arrow-up-right-from-square text-xs"></i>
                        </a>
                    @endif
                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ optional($notification->created_at)->diffForHumans() }}</p>
                </div>

                <button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[var(--theme-accent)] transition hover:bg-[color:rgba(var(--theme-accent-rgb),0.08)]" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color));" onclick="return window.adminNotificationsPanel.markRead('{{ $notification->actionKey }}');">
                    <i class="fa-light fa-check text-sm"></i>
                </button>
            </div>
        </div>
    @empty
        <div class="px-4 py-10 text-center text-sm" style="color: var(--theme-muted-text-color);">
            {{ __('No unread notifications.') }}
        </div>
    @endforelse
</div>

<div class="max-h-[calc(100vh-14rem)] overflow-y-auto sm:max-h-[26rem]" x-show="tab === 'all'" x-cloak>
    @forelse ($notifications as $notification)
        <div class="border-b px-4 py-4 last:border-b-0" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-2">
                        @if (is_null($notification->read_at))
                            <span class="inline-flex items-center rounded-md bg-slate-900 px-2 py-0.5 text-[11px] font-semibold text-white dark:bg-slate-100 dark:text-slate-900">{{ __('New') }}</span>
                        @endif
                        <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $notification->resolvedTitle() }}</p>
                    </div>
                    <div class="text-sm leading-7" style="color: var(--theme-header-text-color);">{!! nl2br(e($notification->resolvedMessage())) !!}</div>
                    @if ($notification->url)
                        <a href="{{ $notification->url }}" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--theme-accent)]" target="_blank">
                            {{ __('Open link') }}
                            <i class="fa-light fa-arrow-up-right-from-square text-xs"></i>
                        </a>
                    @endif
                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ optional($notification->created_at)->diffForHumans() }}</p>
                </div>

                @if (is_null($notification->read_at))
                    <button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[var(--theme-accent)] transition hover:bg-[color:rgba(var(--theme-accent-rgb),0.08)]" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color));" onclick="return window.adminNotificationsPanel.markRead('{{ $notification->actionKey }}');">
                        <i class="fa-light fa-check text-sm"></i>
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div class="px-4 py-10 text-center text-sm" style="color: var(--theme-muted-text-color);">
            {{ __('No notifications found.') }}
        </div>
    @endforelse
</div>
