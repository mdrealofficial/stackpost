@component(theme_view('layouts.app', 'app'), ['title' => __('Automation Hub')])
<div
    class="min-w-0 max-w-full space-y-8 overflow-x-hidden px-4 pb-10 pt-4 sm:px-5 xl:px-6"
    x-data="{
        copy(value, successMessage) {
            if (! value) return;

            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(value).then(() => {
                    window.dispatchEvent(new CustomEvent('app-toast', {
                        detail: {
                            type: 'success',
                            title: @js(__('Copied')),
                            message: successMessage,
                        }
                    }));
                });
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);

            window.dispatchEvent(new CustomEvent('app-toast', {
                detail: {
                    type: 'success',
                    title: @js(__('Copied')),
                    message: successMessage,
                }
            }));
        }
    }"
>
    <section class="min-w-0 max-w-full overflow-hidden rounded-[1.75rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background:
        radial-gradient(circle at 0% 0%, rgba(var(--theme-accent-rgb), 0.20), transparent 34%),
        radial-gradient(circle at 86% 10%, rgba(var(--theme-info-color-rgb), 0.12), transparent 30%),
        linear-gradient(135deg, rgba(var(--theme-surface-base-rgb,255,255,255),0.98), rgba(var(--theme-surface-base-rgb,255,255,255),0.94));
    ">
        <div class="grid min-w-0 max-w-full gap-6 px-5 py-6 sm:px-8 sm:py-7 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.42fr)] lg:items-center">
            <div class="flex min-w-0 gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[1rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.14); color: var(--theme-accent);">
                    <i class="fa-light fa-plug-circle-bolt text-xl"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-accent);">{{ __('Automation') }}</p>
                    <h1 class="mt-2 text-[1.65rem] font-semibold tracking-[-0.05em] sm:text-[1.9rem]" style="color: var(--theme-header-text-color);">{{ __('Automation Hub') }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Connect external tools with API keys, inbound publishing endpoints, and outbound post-status webhooks.') }}</p>
                </div>
            </div>
            <div class="min-w-0 max-w-full rounded-[1.25rem] border p-4 text-sm shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96); color: var(--theme-header-text-color);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('API workspace') }}</p>
                        <p class="mt-2 text-base font-semibold">{{ __('Automation API') }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: rgba(var(--theme-success-color-rgb), 0.12); color: var(--theme-success-color);">
                        <i class="fa-light fa-code-branch"></i>
                    </div>
                </div>
                <div class="mt-4 rounded-[1rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: rgba(var(--theme-surface-muted-rgb,248,250,252),0.76);">
                    <div class="flex min-w-0 flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ __('API Base') }}</p>
                            <p class="mt-1 break-all text-xs" style="color: var(--theme-muted-text-color);">{{ $apiBase }}</p>
                        </div>
                        <x-ui.button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-full sm:w-auto sm:shrink-0"
                            data-copy-value="{{ $apiBase }}"
                            data-copy-message="{{ __('API base copied.') }}"
                            x-on:click="copy($el.dataset.copyValue, $el.dataset.copyMessage)"
                        >
                            <i class="fa-light fa-copy me-2"></i>{{ __('Copy') }}
                        </x-ui.button>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                    <div class="rounded-[0.85rem] border px-3 py-2" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                        <p class="font-semibold">{{ __('Keys') }}</p>
                        <p style="color: var(--theme-muted-text-color);">{{ __('Bearer auth') }}</p>
                    </div>
                    <div class="rounded-[0.85rem] border px-3 py-2" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                        <p class="font-semibold">{{ __('Posts') }}</p>
                        <p style="color: var(--theme-muted-text-color);">{{ __('Inbound') }}</p>
                    </div>
                    <div class="rounded-[0.85rem] border px-3 py-2" style="border-color: rgba(var(--theme-border-color-rgb), 0.55);">
                        <p class="font-semibold">{{ __('Events') }}</p>
                        <p style="color: var(--theme-muted-text-color);">{{ __('Webhooks') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (session('status'))
        <x-ui.alert
            variant="success"
            inline
            :title="__('Saved')"
            :description="session('status')"
        />
    @endif

    @if ($revealedApiKey !== '')
        <x-ui.modal
            width="lg"
            :initially-open="true"
            :title="__('Copy this API key now')"
            :description="__('This raw token is shown only once. Save it in Zapier, n8n, or your app secret store before you leave this page.')"
        >
            <div class="space-y-4">
                <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-warning-color-rgb), 0.28); background-color: rgba(var(--theme-warning-color-rgb), 0.08); color: var(--theme-header-text-color);">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Raw API key') }}</p>
                    <p class="mt-3 font-mono text-sm break-all">{{ $revealedApiKey }}</p>
                </div>
                <p class="text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    {{ __('Use this token with `Authorization: Bearer ...` or `X-Automation-Key`. Once you close this popup, the full key cannot be shown again.') }}
                </p>
            </div>

            <x-slot:footer>
                <x-ui.button type="button" variant="outline" x-on:click="open = false">
                    {{ __('Close') }}
                </x-ui.button>
                <x-ui.button
                    type="button"
                    data-copy-value="{{ $revealedApiKey }}"
                    data-copy-message="{{ __('API key copied. Paste it into n8n now because it will not be shown again.') }}"
                    x-on:click="copy($el.dataset.copyValue, $el.dataset.copyMessage)"
                >
                    <i class="fa-light fa-copy me-2"></i>{{ __('Copy key') }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <div class="grid min-w-0 max-w-full gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card class="min-w-0 max-w-full p-0 overflow-hidden">
                <div class="flex items-start gap-3 border-b px-5 py-5 sm:px-8" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.85rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">
                        <i class="fa-light fa-key"></i>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('API keys') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use these keys with `Authorization: Bearer ...` or `X-Automation-Key` when creating posts from automation tools.') }}</p>
                    </div>
                </div>
                <div class="min-w-0 max-w-full space-y-5 px-5 py-5 sm:px-8 sm:py-6">
                    <form method="POST" action="{{ route('portal.automation.api-keys.store') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                        @csrf
                        <x-ui.input name="name" :label="__('Key name')" :placeholder="__('Zapier production')" :error="$errors->first('name')" />
                        <div class="flex items-end">
                            <x-ui.button type="submit" class="w-full lg:w-auto"><i class="fa-light fa-plus me-2"></i>{{ __('Create key') }}</x-ui.button>
                        </div>
                    </form>

                    <div class="space-y-3">
                        @forelse ($apiKeys as $apiKey)
                            <div class="min-w-0 max-w-full flex flex-col gap-3 rounded-[1rem] border px-4 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.95);">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $apiKey->name }}</p>
                                    <p class="mt-1 text-xs font-mono" style="color: var(--theme-muted-text-color);">{{ $apiKey->key_prefix }}...</p>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Permissions: :permissions', ['permissions' => collect((array) $apiKey->permissions)->implode(', ') ?: 'none']) }}</p>
                                </div>
                                <div class="flex min-w-0 flex-wrap items-center gap-2 sm:justify-end">
                                    <span class="text-xs" style="color: var(--theme-muted-text-color);">{{ $apiKey->last_used_at?->diffForHumans() ?: __('Never used') }}</span>
                                    @if ($apiKey->revoked_at)
                                        <x-ui.badge variant="danger">{{ __('Revoked') }}</x-ui.badge>
                                    @else
                                        <form method="POST" action="{{ route('portal.automation.api-keys.revoke', $apiKey) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="outline" size="sm">{{ __('Revoke') }}</x-ui.button>
                                        </form>
                                    @endif
                                    <x-ui.dialog
                                        width="sm"
                                        dismissible
                                        :title="__('Delete this API key?')"
                                        :description="__('This permanently removes the API key record. Any automation using it will stop working immediately.')"
                                    >
                                        <x-slot:trigger>
                                            <x-ui.button type="button" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                        </x-slot:trigger>

                                        <x-slot:footer>
                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                            <form method="POST" action="{{ route('portal.automation.api-keys.delete', $apiKey) }}" class="inline-flex">
                                                @csrf
                                                <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete key') }}</x-ui.button>
                                            </form>
                                        </x-slot:footer>
                                    </x-ui.dialog>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty icon="fa-light fa-key" :title="__('No API keys yet')" :description="__('Create an API key to let Zapier, n8n, or your backend push content into Publishing.')"/>
                        @endforelse
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="min-w-0 max-w-full p-0 overflow-hidden">
                <div class="border-b px-5 py-5 sm:px-8" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <p class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Outbound webhooks') }}</p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Receive `post.published` and `post.failed` callbacks in n8n, Zapier Catch Hook, or any custom endpoint.') }}</p>
                </div>
                <div class="min-w-0 max-w-full space-y-5 px-5 py-5 sm:px-8 sm:py-6">
                    <form method="POST" action="{{ route('portal.automation.webhooks.store') }}" class="grid gap-4">
                        @csrf
                        <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <x-ui.input name="name" :label="__('Webhook name')" :placeholder="__('n8n production')" :error="$errors->first('name')" />
                            <x-ui.input name="url" :label="__('Destination URL')" :placeholder="__('https://your-n8n.example/webhook/...')" :error="$errors->first('url')" />
                        </div>
                        <x-ui.input name="events" :label="__('Events')" :placeholder="__('post.published,post.failed')" :error="$errors->first('events')" />
                        <div class="flex justify-end">
                            <x-ui.button type="submit" class="w-full sm:w-auto">{{ __('Add webhook') }}</x-ui.button>
                        </div>
                    </form>

                    <div class="space-y-3">
                        @forelse ($webhooks as $webhook)
                            <div class="min-w-0 max-w-full rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.95);">
                                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $webhook->name }}</p>
                                        <p class="mt-1 text-xs break-all" style="color: var(--theme-muted-text-color);">{{ $webhook->url }}</p>
                                        <p class="mt-2 text-xs font-mono break-all" style="color: var(--theme-muted-text-color);">{{ __('Signing secret: :secret', ['secret' => $webhook->signing_secret]) }}</p>
                                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Events: :events', ['events' => collect((array) $webhook->events)->implode(', ') ?: 'post.published, post.failed']) }}</p>
                                    </div>
                                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                                        <x-ui.badge :variant="$webhook->is_active ? 'success' : 'warning'">{{ $webhook->is_active ? __('Active') : __('Paused') }}</x-ui.badge>
                                        <form method="POST" action="{{ route('portal.automation.webhooks.toggle', $webhook) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="outline" size="sm">{{ $webhook->is_active ? __('Pause') : __('Enable') }}</x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('portal.automation.webhooks.delete', $webhook) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty icon="fa-light fa-webhook" :title="__('No webhooks yet')" :description="__('Add a webhook endpoint to receive publishing status events in your external tools.')"/>
                        @endforelse
                </div>
            </x-ui.card>
            <x-ui.card class="min-w-0 max-w-full p-0 overflow-hidden">
                <div class="border-b px-6 py-5 sm:px-8" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <p class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recent automation logs') }}</p>
                </div>
                <div class="space-y-3 px-6 py-6 sm:px-8">
                    @forelse ($logs as $log)
                        <div class="rounded-[1rem] border px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.95);">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $log->event }}</p>
                                    <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ strtoupper($log->direction) }} &middot; {{ $log->created_at?->format('d/m/Y H:i:s') }}</p>
                                </div>
                                <x-ui.badge :variant="$log->status === 'failed' ? 'danger' : 'success'">{{ $log->status }}</x-ui.badge>
                            </div>
                            @if ($log->status_code)
                                <p class="mt-2 text-xs" style="color: var(--theme-muted-text-color);">{{ __('HTTP :code', ['code' => $log->status_code]) }}</p>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty icon="fa-light fa-scroll" :title="__('No automation logs yet')" :description="__('Inbound API requests and outbound webhook deliveries will appear here for debugging.')"/>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card class="min-w-0 max-w-full p-0 overflow-hidden">
                <div class="border-b px-5 py-5 sm:px-8" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-[0.85rem]" style="background-color: rgba(var(--theme-info-color-rgb), 0.12); color: var(--theme-info-color);">
                            <i class="fa-light fa-code"></i>
                        </div>
                        <p class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('Quick start') }}</p>
                    </div>
                </div>
                <div class="min-w-0 max-w-full space-y-4 px-5 py-5 sm:px-8 sm:py-6 text-sm leading-7" style="color: var(--theme-muted-text-color);">
                    <div>
                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('1. List channels') }}</p>
                        <pre class="mt-2 overflow-x-auto rounded-[0.9rem] border p-4 text-xs" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.92); color: var(--theme-header-text-color);">GET {{ $apiBase }}/accounts
Authorization: Bearer YOUR_API_KEY</pre>
                    </div>
                    <div>
                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('2. Create a post') }}</p>
                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Valid mode values: scheduled, publish_now, now, or draft. scheduled accepts ISO 8601 schedule_at values with any timezone offset.') }}</p>
                        <pre class="mt-2 overflow-x-auto rounded-[0.9rem] border p-4 text-xs" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.92); color: var(--theme-header-text-color);">POST {{ $apiBase }}/posts
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "account_ids": [123],
  "caption": "Post created from n8n",
  "media_urls": ["https://example.com/banner.jpg"],
  "mode": "scheduled",
  "schedule_at": "2026-04-12T09:00:00+07:00",
  "network_options": {
    "facebook": {
      "post_to": "feed"
    },
    "instagram": {
      "post_to": "feed"
    },
    "instagram_unofficial": {
      "post_to": "feed"
    },
    "google_business_profile": {
      "post_to": "feed",
      "gbp_action": "LEARN_MORE",
      "gbp_link": "https://example.com"
    },
    "linkedin_profile": {
      "linkedin_post_type": "auto"
    },
    "linkedin_page": {
      "linkedin_post_type": "auto"
    },
    "x": {
      "x_post_type": "auto"
    },
    "youtube": {
      "youtube_title": "Automation API walkthrough",
      "youtube_category": "22",
      "youtube_privacy": "public",
      "youtube_tags": "automation,api,publishing",
      "youtube_thumbnail": "https://example.com/thumbnail.jpg"
    },
    "pinterest": {
      "pinterest_title": "Automation API launch",
      "pinterest_link": "https://example.com/blog/automation-api"
    },
    "reddit": {
      "reddit_title": "Automation API example"
    },
    "mastodon": {
      "mastodon_visibility": "public",
      "mastodon_spoiler_text": ""
    },
    "discord": {
      "discord_username": "Automation Bot",
      "discord_avatar_url": "https://example.com/avatar.png"
    },
    "tiktok": {
      "tt_privacy": "PUBLIC_TO_EVERYONE",
      "tt_allow_comment": true,
      "tt_allow_duet": false,
      "tt_allow_stitch": false,
      "tt_commercial_content": false,
      "tt_your_brand": false,
      "tt_branded_content": false,
      "tt_consent": true,
      "tt_can_post": true,
      "tt_creator_ready": true
    },
    "threads": {},
    "telegram": {},
    "vk": {
      "post_to": "feed"
    },
    "ok": {}
  }
}</pre>
                    </div>
                    <div>
                        <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ __('3. Verify outbound signature') }}</p>
                        <p>{{ __('Outgoing webhook requests include `X-Automation-Event`, `X-Automation-Timestamp`, and `X-Automation-Signature` where signature = `sha256=` + HMAC-SHA256(timestamp + "." + raw_body, signing_secret).') }}</p>
                    </div>
                </div>
            </x-ui.card>

        </div>

</div>
@endcomponent
