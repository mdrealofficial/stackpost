<div class="rss-workspace rss-form" x-data="{
    previewCurrent() {
        const inputs = $wire.feedPreview.inputs;
        return inputs &amp;&amp; Object.entries(inputs).every(([key, value]) => $wire[key] === value);
    },
    jump(id) {
        const section = document.getElementById(id);
        section?.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        section?.focus({ preventScroll: true });
    }
}" x-on:rss-preview-ready.window="if (window.matchMedia('(max-width: 1100px)').matches) $nextTick(() => jump('rss-preview'))">
    @include('apprssschedules::partials.workspace-styles')
    <x-ui.sub-header compact :title="$isEditing ? __('Edit RSS schedule') : __('Add a feed')" :description="__('Choose your source, channels and publishing schedule.')"><x-slot:actions><x-ui.button :href="route('portal.rss-schedules.index')" variant="outline">{{ __('Back to schedules') }}</x-ui.button></x-slot:actions></x-ui.sub-header>
    <nav class="rss-steps" aria-label="{{ __('Feed setup sections') }}">
        @foreach(['source' => __('Source'), 'channels' => __('Channels'), 'content' => __('Content'), 'schedule' => __('Schedule')] as $section => $label)
            <button type="button" x-on:click="jump('rss-{{ $section }}')"><span>{{ $loop->iteration }}</span>{{ $label }}<i class="fa-light fa-arrow-down" aria-hidden="true"></i></button>
        @endforeach
    </nav>
    <form wire:submit="save" class="rss-form-layout">
        <div class="min-w-0 space-y-6">
            <x-ui.card id="rss-source" tabindex="-1" padding="none" class="overflow-hidden rss-form-section">
                <div class="border-b px-5 py-4 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent-color);">
                            <i class="fa-light fa-rss"></i>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('1. Feed source') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Use a public RSS or Atom feed and preview its latest articles.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <x-ui.input wire:model.defer="feed_url" :label="__('RSS URL')" :error="$errors->first('feed_url')" type="url" :placeholder="__('https://example.com/feed.xml')" required />
                    <x-ui.button type="button" variant="outline" wire:click="previewFeed" wire:loading.attr="disabled" wire:target="previewFeed,save"><i class="fa-light fa-eye" aria-hidden="true"></i>{{ __('Check feed & preview') }}</x-ui.button>
                    <span class="rss-muted" wire:loading wire:target="previewFeed">{{ __('Reading feed...') }}</span>
                    <p class="rss-muted">{{ __('Checking a feed does not create posts or use AI credits.') }}</p>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.input wire:model.defer="name" :label="__('Schedule name')" :error="$errors->first('name')" :placeholder="__('Optional — uses the feed title')" />
                        <x-ui.input wire:model.defer="description" :label="__('Description')" :error="$errors->first('description')" :placeholder="__('Optional short description')" />
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card id="rss-channels" tabindex="-1" padding="none" class="overflow-hidden rss-form-section">
                <div class="border-b px-5 py-4 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(16,185,129,0.10); color: rgb(5,150,105);">
                            <i class="fa-light fa-share-nodes"></i>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('2. Destination channels') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Choose the active channels that should receive publishing posts created from this feed.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <x-ui.channel-selector
                        name="account_ids"
                        wire-model="account_ids"
                        :options="$channelOptions"
                        :network-options="$channelNetworks"
                        :selected="collect($account_ids)->map(fn ($id) => (string) $id)->all()"
                        :error="$errors->first('account_ids') ?: $errors->first('account_ids.*')"
                        :placeholder="__('Choose one or more accounts')"
                        :empty-label="__('No matching channels found.')"
                        :multiple="true"
                        :show-network-filters="true"
                        :connect-href="route('portal.channels')"
                        :connect-label="__('Connect a channel')"
                        :inline-panel="true"
                    />

                    <details class="rss-advanced"><summary>{{ __('Organization options') }}</summary>
                    <x-ui.select wire:model.defer="campaign_id" :label="__('Campaign')" :error="$errors->first('campaign_id')">
                        <option value="">{{ __('No campaign') }}</option>
                        @foreach ($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}{{ $campaign->status !== 'active' ? ' ('.str($campaign->status)->headline().')' : '' }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.tag-selector
                        name="label_ids"
                        wire-model="label_ids"
                        :live="false"
                        :label="__('Labels')"
                        :options="$labels->map(fn ($label) => ['key' => (string) $label->id, 'label' => $label->name])->all()"
                        :selected="collect($label_ids)->map(fn ($id) => (string) $id)->all()"
                        :description="__('Use labels to organize and filter RSS-generated posts in Publishing.')"
                        :placeholder="__('Search or pick labels...')"
                        :empty-label="__('No matching labels found.')"
                        :error="$errors->first('label_ids') ?: $errors->first('label_ids.*')"
                    />
                    </details>
                </div>
            </x-ui.card>

            <x-ui.card id="rss-content" tabindex="-1" padding="none" class="overflow-hidden rss-form-section">
                <div class="border-b px-5 py-4 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(168,85,247,0.10); color: rgb(126,34,206);">
                            <i class="fa-light fa-sliders"></i>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('3. Content rules') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Control how feed items are transformed before they enter Publishing.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.checkbox wire:model.defer="deny_link" value="1" :label="__('Omit article link')" />
                        <x-ui.checkbox wire:model.defer="accept_caption" value="1" :label="__('Include article summary')" />
                    </div>

                    <div class="rounded-[1.1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.62); background:
                        linear-gradient(180deg, rgba(124, 58, 237, 0.04), rgba(var(--theme-surface-base-rgb,255,255,255),0.94));
                    ">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(124, 58, 237, 0.10); color: #7c3aed;">
                                <i class="fa-light fa-sparkles text-sm"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <x-ui.checkbox wire:model.live="enable_ai_rewrite" value="1" :label="__('Enable AI rewrite')" />
                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('When enabled, RSS items will be rewritten into a fresh caption before entering Publishing. If AI fails, the original RSS caption is used.') }}</p>
                            </div>
                        </div>

                        @if($enable_ai_rewrite)
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <x-ui.select wire:model.defer="ai_rewrite_tone" :label="__('Tone of voice')" :error="$errors->first('ai_rewrite_tone')">
                                @foreach ($toneOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </x-ui.select>

                            <x-ui.language-select
                                wire:model.defer="ai_rewrite_language"
                                :label="__('Language')"
                                :error="$errors->first('ai_rewrite_language')"
                                :preferred="['vi', 'en']"
                            />
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <x-ui.input
                                type="number"
                                min="0"
                                step="1"
                                wire:model.defer="ai_rewrite_max_per_run"
                                :label="__('Max AI rewrites per run')"
                                :error="$errors->first('ai_rewrite_max_per_run')"
                                :placeholder="__('0 = unlimited')"
                                :help="__('How many RSS items can use AI rewrite in one manual/automatic run. Enter 0 for unlimited.')"
                            />

                            <x-ui.input
                                type="number"
                                min="0"
                                step="1"
                                wire:model.defer="ai_rewrite_monthly_credit_limit"
                                :label="__('Monthly AI credit limit')"
                                :error="$errors->first('ai_rewrite_monthly_credit_limit')"
                                :placeholder="__('0 = unlimited')"
                                :help="__('Maximum AI credits this schedule may spend in one month. Enter 0 for unlimited.')"
                            />
                        </div>

                        <div class="mt-4">
                            <x-ui.textarea
                                wire:model.defer="ai_rewrite_prompt"
                                :label="__('Rewrite prompt')"
                                :error="$errors->first('ai_rewrite_prompt')"
                                rows="4"
                                :placeholder="__('Example: Rewrite the caption to be short, persuasive, and suitable for Facebook. Keep key facts accurate.')"
                            />
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs" style="color: var(--theme-muted-text-color);">
                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5" style="border-color: rgba(124, 58, 237, 0.16); background-color: rgba(124, 58, 237, 0.08); color: #6d28d9;">
                                <i class="fa-light fa-coins text-[11px]"></i>
                                {{ __(':credits credit per rewrite', ['credits' => $aiRewriteCreditPreview['unit_cost'] ?? 0]) }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.76);">
                                {{ ($aiRewriteCreditPreview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits credits left', ['credits' => number_format((int) ($aiRewriteCreditPreview['remaining'] ?? 0))]) }}
                            </span>
                        </div>
                        @endif
                    </div>

                    <details class="rss-advanced"><summary>{{ __('Link options') }}</summary>
                    <x-ui.input
                        wire:model.defer="referral_code"
                        :label="__('Link tracking code')"
                        :error="$errors->first('referral_code')"
                        :placeholder="__('Example: your-ref-code')"
                        :help="__('Appends ref= to article links. Your destination website must handle attribution.')"
                    />

                        @if($urlShortenerAvailable)<x-ui.checkbox wire:model.defer="url_shorten" value="1" :label="__('Shorten article links')" />@endif
                    </details>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.textarea wire:model.defer="include_keywords" :label="__('Include keywords')" :error="$errors->first('include_keywords')" rows="2" :help="__('Match any keyword in the title or summary. Separate with commas or new lines.')" />
                        <x-ui.textarea wire:model.defer="ignore_keywords" :label="__('Exclude keywords')" :error="$errors->first('ignore_keywords')" rows="2" :help="__('Skip an article if any of these keywords match.')" />
                    </div>
                </div>
            </x-ui.card>
            <x-ui.card id="rss-schedule" tabindex="-1" padding="none" class="relative z-20 overflow-visible rss-form-section">
                <div class="border-b px-5 py-4 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(245,158,11,0.12); color: rgb(217,119,6);">
                            <i class="fa-light fa-calendar-clock"></i>
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold" style="color: var(--theme-header-text-color);">{{ __('4. Schedule') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('One new article per active channel at each slot. Timezone: :timezone', ['timezone' => $timezone]) }}</p>
                        </div>
                    </div>
                </div>

                <div
                    x-data="{
                        slots: $wire.entangle('time_posts'),
                        selectedDays: $wire.entangle('weekdays'),
                        addSlot() {
                            const current = [...(this.slots || [])];
                            const last = (current.at(-1) || '08:00').split(':');
                            for (let offset = 1; offset &lt;= 24; offset++) {
                                const hour = ((Number(last[0]) || 0) + offset) % 24;
                                const next = String(hour).padStart(2, '0') + ':' + (last[1] || '00');
                                if (!current.includes(next)) { this.slots = [...current, next]; break; }
                            }
                        },
                        removeSlot(index) {
                            const next = [...(this.slots || [])];
                            next.splice(index, 1);
                            this.slots = next.length ? next : ['09:00'];
                        },
                        toggleDay(day) {
                            const current = [...(this.selectedDays || [])];
                            this.selectedDays = current.includes(day)
                                ? current.filter((item) => item !== day)
                                : [...current, day];
                        },
                        hasDay(day) {
                            return (this.selectedDays || []).includes(day);
                        }
                    }"
                    class="space-y-6 px-5 py-5 sm:px-6"
                >
                    <div class="space-y-3">
                        <x-ui.label>{{ __('Import times') }}</x-ui.label>
                        <template x-for="(slot, index) in slots" :key="'slot-'+index">
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <x-ui.time-picker x-model="slots[index]" :placeholder="__('Select time')" class="flex-1" />
                                </div>
                                <button type="button" x-on:click="removeSlot(index)" :disabled="slots.length &lt;= 1" :aria-label="'{{ __("Remove time") }} '+(index+1)" class="inline-flex h-11 w-11 items-center justify-center rounded-[0.85rem] border transition hover:bg-slate-900/[0.03]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-trash"></i>
                                </button>
                            </div>
                        </template>
                        @error('time_posts')<p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>@enderror
                        @error('time_posts.*')<p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>@enderror

                        <button type="button" x-on:click="addSlot()" class="flex h-11 w-full items-center justify-center gap-2 rounded-[0.85rem] border text-sm font-medium transition hover:border-[var(--theme-accent)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.05); color: var(--theme-header-text-color);">
                            <i class="fa-light fa-plus"></i>
                            {{ __('Add time') }}
                        </button>
                    </div>

                    <div class="space-y-3">
                        <x-ui.label>{{ __('Schedule every') }}</x-ui.label>
                        <div class="rss-days">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                                <button
                                    type="button"
                                    x-on:click="toggleDay('{{ $day }}')" x-bind:aria-pressed="hasDay('{{ $day }}')"
                                    class="rounded-[0.95rem] border px-3 py-5 text-sm font-medium transition"
                                    x-bind:style="hasDay('{{ $day }}')
                                        ? 'border-color: transparent; background-color: var(--theme-accent); color: white;'
                                        : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96); color: var(--theme-header-text-color);'"
                                >
                                    {{ __($day) }}
                                </button>
                            @endforeach
                        </div>
                        @error('weekdays')<p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4">
                        <x-ui.date-picker class="relative z-30" name="start_date" wire:model.defer="start_date" :label="__('Start date')" :value="$start_date" :error="$errors->first('start_date')" :placeholder="__('Start immediately')" />
                        <x-ui.date-picker class="relative z-20" name="end_date" wire:model.defer="end_date" :label="__('End date')" :value="$end_date" :error="$errors->first('end_date')" :placeholder="__('No end date')" />
                    </div>
                </div>
            </x-ui.card>

        </div>

        <aside class="rss-form-review" aria-label="{{ __('Preview and delivery') }}">
            <x-ui.card padding="none" class="rss-review-card">
                <div class="rss-review-heading"><span class="rss-badge">{{ __('REVIEW') }}</span><h2>{{ __('Review schedule') }}</h2><p class="rss-muted">{{ __('Check the destination and delivery mode before saving.') }}</p></div>
                <dl class="rss-review-summary">
                    <div><dt>{{ __('Channels') }}</dt><dd x-text="$wire.account_ids.length ? $wire.account_ids.length + ' {{ __('selected') }}' : '{{ __('None selected') }}'"></dd></div>
                    <div><dt>{{ __('Days') }}</dt><dd x-text="$wire.weekdays.length ? $wire.weekdays.join(', ') : '{{ __('Choose days') }}'"></dd></div>
                    <div><dt>{{ __('Times') }}</dt><dd x-text="$wire.time_posts.filter(Boolean).join(', ') || '{{ __('Choose a time') }}'"></dd></div>
                    <div><dt>{{ __('Timezone') }}</dt><dd>{{ $timezone }}</dd></div>
                </dl>
                <div class="flex flex-col gap-4 px-5 py-4 sm:px-6">
                    <x-ui.select wire:model.live="delivery_mode" :label="__('Delivery mode')" :error="$errors->first('delivery_mode')"><option value="draft">{{ __('Create drafts for review') }}</option><option value="automatic">{{ __('Publish automatically') }}</option></x-ui.select>
                    <p class="rss-muted">{{ $delivery_mode === 'draft' ? __('New items stay in Publishing drafts until you schedule or publish them.') : __('New items enter the publishing queue automatically at each scheduled time.') }}</p>
                    <x-ui.checkbox wire:model.defer="status" value="1" :label="__('Active schedule')" />

                    <p class="rss-muted" x-show="!$wire.status" x-cloak>{{ __('Saved as paused. Enable this schedule when you are ready to start importing articles.') }}</p>
                    @if($errors->any())
                        <div class="rss-form-errors" role="alert"><strong>{{ __('Please check your settings') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <x-ui.button type="submit" size="lg" wire:loading.attr="disabled" wire:target="save,previewFeed">
                        <i class="fa-light {{ $isEditing ? 'fa-floppy-disk' : 'fa-plus' }}"></i>
                        <span wire:loading.remove wire:target="save">{{ $isEditing ? __('Save changes') : __('Create RSS Schedule') }}</span><span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                    </x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card id="rss-preview" tabindex="-1" padding="none" class="rss-preview-card rss-form-section">
                <div class="rss-preview-heading"><h2>{{ __('Article preview') }}</h2><span class="rss-badge">{{ __('BEFORE AI') }}</span></div>
                @if($feedPreview)
                    <div class="rss-preview-body">
                        <div x-show="!previewCurrent()" class="rss-preview-stale" role="status">{{ __('Your source or content rules have changed. Refresh to see the updated preview.') }}</div>
                        <div x-show="previewCurrent()">
                            <strong>{{ $feedPreview['title'] }}</strong>
                            <p class="rss-muted">{{ __(':count articles found · Showing up to 3', ['count' => $feedPreview['count']]) }}</p>
                            <div class="rss-preview-articles">
                                @foreach($feedPreview['items'] as $preview)
                                    <details wire:key="rss-preview-{{ md5($feedPreview['url'].$preview['caption'].$loop->index) }}" @if($loop->first) open @endif>
                                        <summary><span>{{ $preview['title'] }}</span><small>{{ $preview['matches'] ? __('Matches rules') : __('Excluded by rules') }}</small></summary>
                                        <div class="rss-preview-caption"><pre>{{ $preview['caption'] }}</pre></div>
                                    </details>
                                @endforeach
                            </div>
                            @if(!$feedPreview['items'])<p class="rss-muted">{{ __('This feed is valid but has no articles yet.') }}</p>@endif
                        </div>
                        <x-ui.button type="button" variant="outline" wire:click="previewFeed" wire:loading.attr="disabled" wire:target="previewFeed,save">{{ __('Refresh preview') }}</x-ui.button>
                        <span class="rss-muted" wire:loading wire:target="previewFeed">{{ __('Reading feed...') }}</span>
                        <button class="rss-preview-edit" type="button" x-on:click="jump('rss-content')">{{ __('Edit content rules') }} <i class="fa-light fa-arrow-up" aria-hidden="true"></i></button>
                    </div>
                @else
                    <div class="rss-preview-empty"><i class="fa-light fa-file-lines" aria-hidden="true"></i><h3>{{ __('See what will be imported') }}</h3><p>{{ __('Enter a feed URL and check it to preview article captions and keyword matches.') }}</p><button type="button" x-on:click="jump('rss-source')">{{ __('Go to feed source') }} <i class="fa-light fa-arrow-up" aria-hidden="true"></i></button></div>
                @endif
                <p class="rss-preview-footnote">{{ __('Preview shows article text before AI rewriting and link shortening. Images and formatting can vary by channel.') }}</p>
            </x-ui.card>
        </aside>
    </form>
</div>
