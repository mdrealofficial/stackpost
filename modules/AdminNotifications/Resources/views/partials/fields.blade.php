@php($plainSections = $plainSections ?? false)

@if ($plainSections)
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.input
                name="title"
                :label="__('Title')"
                :value="old('title', $notification->title)"
                :error="$errors->first('title')"
                :placeholder="__('Payment was successful')"
            />

            <x-ui.input
                name="url"
                :label="__('Link')"
                :value="old('url', $notification->url)"
                :error="$errors->first('url')"
                :placeholder="__('https://example.com/upgrade')"
            />
        </div>

        <x-ui.textarea name="message" :label="__('Message')" :error="$errors->first('message')" rows="10">{{ old('message', $notification->message) }}</x-ui.textarea>

        @if (! $isEditing)
            <div
                x-data="adminNotificationUserSelect({
                    searchUrl: @js(route('admin-notifications.users.search')),
                    initialSelected: @js(collect($selectedUserOptions ?? [])->map(fn ($user) => [
                        'key' => (string) ($user['key'] ?? ''),
                        'label' => (string) ($user['label'] ?? ''),
                    ])->values()->all()),
                    initialGlobal: @js((bool) old('is_global', false)),
                })"
                class="space-y-4 border-t pt-4"
                style="border-color: var(--theme-border-color);"
            >
                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                    <input type="hidden" name="is_global" value="0">
                    <label class="inline-flex items-center gap-3">
                        <input type="checkbox" name="is_global" value="1" class="sr-only" x-model="global">
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            x-bind:class="global ? 'bg-[var(--theme-accent)]' : 'bg-slate-200 dark:bg-slate-700'"
                            x-on:click.prevent="global = !global"
                            role="switch"
                            x-bind:aria-checked="global"
                        >
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition" x-bind:class="global ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                        <span class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Global notification') }}</span>
                    </label>
                    <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Enable this to send one notification to every user in the system.') }}</p>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Send to users') }}</p>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search by name, email, or username and add one or more recipients.') }}</p>
                            </div>
                            <span class="text-xs font-medium uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);" x-text="global ? @js(__('Global')) : (selected.length ? selected.length + ' {{ __('selected') }}' : @js(__('No users selected')))"></span>
                        </div>
                    </div>

                    <div class="space-y-3" x-bind:class="global ? 'opacity-50' : ''">
                        <div class="relative">
                            <input
                                type="text"
                                x-model="query"
                                x-on:input.debounce.250ms="search()"
                                x-bind:disabled="global"
                                class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                                style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                                placeholder="{{ __('Search users...') }}"
                            >
                        </div>

                        <div class="rounded-[0.9rem] border" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <template x-if="selected.length">
                                <div class="flex flex-wrap gap-2 border-b px-3 py-3" style="border-color: var(--theme-border-color);">
                                    <template x-for="user in selected" :key="user.key">
                                        <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color)); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);">
                                            <span x-text="user.label"></span>
                                            <button type="button" class="text-slate-400 transition hover:text-slate-700" x-bind:disabled="global" x-on:click="remove(user.key)">
                                                <i class="fa-light fa-xmark"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div class="max-h-56 overflow-y-auto">
                                <template x-if="!global && loading">
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Searching users...') }}</div>
                                </template>

                                <template x-if="!global && !loading && results.length === 0">
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No matching users found.') }}</div>
                                </template>

                                <template x-if="global">
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Recipient selection is disabled because global notification is enabled.') }}</div>
                                </template>

                                <template x-for="user in results" :key="user.key">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 border-b px-4 py-3 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                        style="border-color: color-mix(in srgb, var(--theme-border-color) 42%, transparent); color: var(--theme-header-text-color);"
                                        x-bind:disabled="global"
                                        x-on:click="toggle(user)"
                                    >
                                        <span class="truncate" x-text="user.label"></span>
                                        <span class="text-[var(--theme-accent)]" x-show="isSelected(user.key)">
                                            <i class="fa-light fa-check"></i>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <template x-for="user in selected" :key="'hidden-' + user.key">
                            <input type="hidden" name="user_ids[]" x-bind:value="user.key">
                        </template>
                    </div>
                </div>

                @error('user_ids')
                    <p class="mt-3 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div class="grid gap-4 border-t pt-4 md:grid-cols-3" style="border-color: var(--theme-border-color);">
                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Recipients') }}</p>
                    <p class="mt-1 text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ number_format($recipientCount) }}</p>
                </div>
                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Sent At') }}</p>
                    <p class="mt-1 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $sentAt ?: __('N/A') }}</p>
                </div>
                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Audience') }}</p>
                    <p class="mt-1 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Recipients are locked after sending.') }}</p>
                </div>
            </div>
        @endif
    </div>
@else
    <x-ui.form-section :title="__('Notification content')">
        <div class="grid gap-5 md:grid-cols-2">
            <x-ui.input
                name="title"
                :label="__('Title')"
                :value="old('title', $notification->title)"
                :error="$errors->first('title')"
                :placeholder="__('Payment was successful')"
            />

            <x-ui.input
                name="url"
                :label="__('Link')"
                :value="old('url', $notification->url)"
                :error="$errors->first('url')"
                :placeholder="__('https://example.com/upgrade')"
            />
        </div>

        <x-ui.textarea name="message" :label="__('Message')" :error="$errors->first('message')" rows="10">{{ old('message', $notification->message) }}</x-ui.textarea>
    </x-ui.form-section>

    @if (! $isEditing)
        <x-ui.form-section :title="__('Recipients')">
            <div
                x-data="adminNotificationUserSelect({
                    searchUrl: @js(route('admin-notifications.users.search')),
                    initialSelected: @js(collect($selectedUserOptions ?? [])->map(fn ($user) => [
                        'key' => (string) ($user['key'] ?? ''),
                        'label' => (string) ($user['label'] ?? ''),
                    ])->values()->all()),
                    initialGlobal: @js((bool) old('is_global', false)),
                })"
                class="space-y-4"
            >
                <div class="rounded-[0.85rem] border px-4 py-3" style="border-color: var(--theme-border-color); background-color: rgba(var(--theme-surface-soft-rgb, 248, 250, 252), 0.55);">
                    <input type="hidden" name="is_global" value="0">
                    <label class="inline-flex items-center gap-3">
                        <input type="checkbox" name="is_global" value="1" class="sr-only" x-model="global">
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            x-bind:class="global ? 'bg-[var(--theme-accent)]' : 'bg-slate-200 dark:bg-slate-700'"
                            x-on:click.prevent="global = !global"
                            role="switch"
                            x-bind:aria-checked="global"
                        >
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition" x-bind:class="global ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
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
                        <span class="text-xs font-medium uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);" x-text="global ? @js(__('Global')) : (selected.length ? selected.length + ' {{ __('selected') }}' : @js(__('No users selected')))"></span>
                    </div>

                    <div class="space-y-3" x-bind:class="global ? 'opacity-50' : ''">
                        <input
                            type="text"
                            x-model="query"
                            x-on:input.debounce.250ms="search()"
                            x-bind:disabled="global"
                            class="flex h-11 w-full border px-4 text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)] focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                            style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                            placeholder="{{ __('Search users...') }}"
                        >

                        <div class="rounded-[0.9rem] border" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                            <template x-if="selected.length">
                                <div class="flex flex-wrap gap-2 border-b px-3 py-3" style="border-color: var(--theme-border-color);">
                                    <template x-for="user in selected" :key="user.key">
                                        <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color)); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);">
                                            <span x-text="user.label"></span>
                                            <button type="button" class="text-slate-400 transition hover:text-slate-700" x-bind:disabled="global" x-on:click="remove(user.key)">
                                                <i class="fa-light fa-xmark"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div class="max-h-56 overflow-y-auto">
                                <template x-if="!global && loading">
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Searching users...') }}</div>
                                </template>

                                <template x-if="!global && !loading && results.length === 0">
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No matching users found.') }}</div>
                                </template>

                                <template x-if="global">
                                    <div class="px-4 py-4 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Recipient selection is disabled because global notification is enabled.') }}</div>
                                </template>

                                <template x-for="user in results" :key="user.key">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 border-b px-4 py-3 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                        style="border-color: color-mix(in srgb, var(--theme-border-color) 42%, transparent); color: var(--theme-header-text-color);"
                                        x-bind:disabled="global"
                                        x-on:click="toggle(user)"
                                    >
                                        <span class="truncate" x-text="user.label"></span>
                                        <span class="text-[var(--theme-accent)]" x-show="isSelected(user.key)">
                                            <i class="fa-light fa-check"></i>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <template x-for="user in selected" :key="'hidden-' + user.key">
                            <input type="hidden" name="user_ids[]" x-bind:value="user.key">
                        </template>
                    </div>
                </div>
            </div>

            @error('user_ids')
                <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>
            @enderror
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
@endif

@once
    <script>
        window.adminNotificationUserSelect = function (config) {
            return {
                query: '',
                loading: false,
                results: [],
                selected: config.initialSelected || [],
                global: !!config.initialGlobal,
                async search() {
                    if (this.global) {
                        this.results = [];
                        return;
                    }

                    this.loading = true;

                    try {
                        const response = await fetch(config.searchUrl + '?q=' + encodeURIComponent(this.query), {
                            headers: { Accept: 'application/json' },
                        });
                        const result = await response.json();
                        this.results = result.items || [];
                    } finally {
                        this.loading = false;
                    }
                },
                isSelected(key) {
                    return this.selected.some(user => user.key === String(key));
                },
                toggle(user) {
                    if (this.isSelected(user.key)) {
                        this.remove(user.key);
                        return;
                    }

                    this.selected.push({
                        key: String(user.key),
                        label: user.label,
                    });
                },
                remove(key) {
                    this.selected = this.selected.filter(user => user.key !== String(key));
                },
                init() {
                    this.search();
                    this.$watch('global', value => {
                        if (value) {
                            this.results = [];
                        } else {
                            this.search();
                        }
                    });
                },
            };
        };
    </script>
@endonce
