<div class="ai-workspace" x-data="{ previewCurrent(){return Object.entries($wire.preview.inputs || {}).every(([key,value])=>JSON.stringify($wire[key]) === JSON.stringify(value));}, jump(id){ const el=document.getElementById(id);el?.scrollIntoView({block:'start'});el?.focus({preventScroll:true}); } }">
@include('appaipublishing::partials.styles')
<x-ui.sub-header compact :title="$isEditing ? __('Edit AI schedule') : __('Create AI schedule')" :description="__('Turn your topics into recurring posts. Review drafts or choose automatic publishing.')"><x-slot:actions><x-ui.button variant="outline" :href="route('portal.ai-publishing')">{{ __('Back to schedules') }}</x-ui.button></x-slot:actions></x-ui.sub-header>
<nav class="ai-steps" aria-label="{{ __('Schedule setup') }}">@foreach(['prompts'=>__('Prompts'),'channels'=>__('Channels'),'content'=>__('Content'),'schedule'=>__('Schedule')] as $key=>$label)<button type="button" x-on:click="jump('ai-{{ $key }}')"><span>{{ $loop->iteration }}</span>{{ $label }}</button>@endforeach</nav>
<form wire:submit="save" class="ai-form-layout">
<div class="ai-stack">
<section class="ai-card" id="ai-prompts" tabindex="-1"><header><h2>{{ __('1. Prompts') }}</h2><p>{{ __('One prompt per time slot, rotating through your selection.') }}</p></header><div class="ai-card-body">
<x-ui.input wire:model.defer="runName" :label="__('Schedule name')" :error="$errors->first('runName')" :placeholder="__('Weekly product tips')" />
<details class="ai-advanced" @if($prompts->isEmpty()) open @endif><summary>{{ __('Write a new prompt') }}</summary>
<x-ui.textarea wire:model.defer="newPrompt" :label="__('New prompt')" rows="3" :error="$errors->first('newPrompt')" :placeholder="__('Describe your topic, audience, key facts and the result you want.')" />
<x-ui.button type="button" variant="outline" wire:click="addPrompt" wire:loading.attr="disabled">{{ __('Add to library') }}</x-ui.button></details>
<x-ui.input wire:model.live.debounce.300ms="promptSearch" :label="__('Choose from your library')" :placeholder="__('Search prompts...')" />
<div class="ai-prompt-list">@forelse($prompts as $prompt)<div class="ai-prompt" wire:key="prompt-{{ $prompt->id }}"><label><input type="checkbox" wire:model.defer="selectedPromptIds" value="{{ $prompt->id }}"><span>{{ $prompt->prompt_text }}</span></label><button type="button" aria-label="{{ __('Delete prompt :id',['id'=>$prompt->id]) }}" wire:click="deletePrompt({{ $prompt->id }})" wire:confirm="{{ __('Delete this prompt from your library? Prompts used by schedules cannot be deleted.') }}"><i class="fa-light fa-trash" aria-hidden="true"></i></button></div>@empty<p class="ai-muted">{{ __('No matching prompts. Write a new prompt or clear the search.') }}</p>@endforelse</div>
@error('selectedPromptIds')<p class="ai-error">{{ $message }}</p>@enderror
<p class="ai-muted"><span x-text="$wire.selectedPromptIds.length"></span> {{ __('prompts selected') }}</p>
</div></section>
<section class="ai-card" id="ai-channels" tabindex="-1"><header><h2>{{ __('2. Destination channels') }}</h2><p>{{ __('Each selected channel receives one post per time slot.') }}</p></header><div class="ai-card-body">
                    <x-ui.channel-selector
                        name="account_ids"
                        wire-model="account_ids"
                        :options="$bulkChannelOptions"
                        :network-options="$bulkChannelNetworks"
                        :selected="collect($account_ids)->map(fn ($id) => (string) $id)->all()"
                        :label="__('Accounts')"
                        :description="__('Choose one or more connected social accounts for this AI publishing run.')"
                        :error="$errors->first('account_ids') ?: $errors->first('account_ids.*')"
                        :placeholder="__('Choose one or more accounts')"
                        :empty-label="__('No matching channels found.')"
                        :multiple="true"
                        :show-network-filters="true"
                        :connect-href="route('portal.channels')"
                        :connect-label="__('Connect a channel')"
                        :inline-panel="true"
                    />

                    <details class="ai-advanced"><summary>{{ __('Organization options') }}</summary><x-ui.select wire:model.defer="campaign_id" :label="__('Campaign')" :error="$errors->first('campaign_id')">
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
                        :description="__('Use labels to organize and filter AI-generated posts in Publishing.')"
                        :placeholder="__('Search or pick labels...')"
                        :empty-label="__('No matching labels found.')"
                        :error="$errors->first('label_ids') ?: $errors->first('label_ids.*')"
                    />

                    </details>
</div></section>
<section class="ai-card" id="ai-content" tabindex="-1"><header><h2>{{ __('3. Content settings') }}</h2><p>{{ __('Choose the language, voice and media for generated posts.') }}</p></header><div class="ai-card-body">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.select wire:model.live="include_media" :label="__('Include media')" :error="$errors->first('include_media')">
                            @foreach ($mediaSourceGroups as $group)
                                <optgroup label="{{ $group['label'] }}">
                                    @foreach ($group['options'] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select wire:model.defer="tone" :label="__('Tone of voice')">
                            @foreach ($toneOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.language-select
                            wire:model.defer="language"
                            :label="__('Language')"
                            :error="$errors->first('language')"
                            :preferred="['vi', 'en']"
                        />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.checkbox wire:model.defer="include_hashtags" value="1" :label="__('Include hashtags')" />
                        <x-ui.checkbox wire:model.defer="include_cta" value="1" :label="__('Include CTA')" />
                        <x-ui.checkbox wire:model.defer="allow_emoji" value="1" :label="__('Allow emoji')" />
                    </div>
</div></section>
<section class="ai-card" id="ai-schedule" tabindex="-1"><header><h2>{{ __('4. Schedule') }}</h2><p>{{ __('Times use :timezone. Missed days are not generated in bulk.', ['timezone'=>$timezone]) }}</p></header>
                <div
                    x-data="{
                        slots: $wire.entangle('time_slots'),
                        selectedDays: $wire.entangle('weekdays'),
                        addSlot() {
                            const current = [...(this.slots || [])]; for(let i=1;i &lt;=24;i++){const hour=((Number((current.at(-1)||'08:00').split(':')[0])+i)%24);const next=String(hour).padStart(2,'0')+':00';if(!current.includes(next)){this.slots=[...current,next];break;}}
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
                    class="space-y-6 ai-card-body"
                >
                    <div class="space-y-3">
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
                        @error('time_slots')<p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>@enderror
                        @error('time_slots.*')<p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>@enderror

                        <button type="button" x-on:click="addSlot()" class="flex h-11 w-full items-center justify-center gap-2 rounded-[0.85rem] border text-sm font-medium transition hover:border-[var(--theme-accent)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.05); color: var(--theme-header-text-color);">
                            <i class="fa-light fa-plus"></i>
                            {{ __('Add time') }}
                        </button>
                    </div>

                    <div class="space-y-3">
                        <x-ui.label>{{ __('Schedule every') }}</x-ui.label>
                        <div class="ai-days">
                            @foreach ($weekdayOptions as $option)
                                <button
                                    type="button"
                                    x-on:click="toggleDay('{{ $option['key'] }}')" :aria-pressed="hasDay('{{ $option['key'] }}')"
                                    class="rounded-[0.95rem] border px-3 py-5 text-sm font-medium transition"
                                    x-bind:style="hasDay('{{ $option['key'] }}')
                                        ? 'border-color: transparent; background-color: var(--theme-accent); color: white;'
                                        : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96); color: var(--theme-header-text-color);'"
                                >
                                    {{ __($option['label']) }}
                                </button>
                            @endforeach
                        </div>
                        @error('weekdays')<p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4">
                        <x-ui.date-picker
                            wire:model.defer="start_at"
                            name="start_at"
                            :label="__('Start date')"
                            :value="$start_at"
                            :error="$errors->first('start_at')"
                            :placeholder="__('Choose start date')"
                        />
                        <x-ui.date-picker
                            wire:model.defer="end_at"
                            name="end_at"
                            :label="__('End date')"
                            :value="$end_at"
                            :error="$errors->first('end_at')"
                            :placeholder="__('Choose end date')"
                        />
                    </div>
                </div>
</section>
</div>
<aside class="ai-stack" aria-label="{{ __('Review and preview') }}">
<section class="ai-card"><header><h2>{{ __('Review schedule') }}</h2><p>{{ __('Saving sets up the schedule. It does not generate content immediately.') }}</p></header><div class="ai-card-body">
<dl class="ai-review"><div><dt>{{ __('Prompts') }}</dt><dd x-text="$wire.selectedPromptIds.length"></dd></div><div><dt>{{ __('Channels') }}</dt><dd x-text="$wire.account_ids.length"></dd></div><div><dt>{{ __('Days') }}</dt><dd x-text="$wire.weekdays.join(', ')"></dd></div><div><dt>{{ __('Times') }}</dt><dd x-text="$wire.time_slots.join(', ')"></dd></div></dl>
<x-ui.select wire:model.live="delivery_mode" :label="__('Delivery mode')"><option value="draft">{{ __('Create drafts for review') }}</option><option value="automatic">{{ __('Publish automatically') }}</option></x-ui.select>
<p class="ai-note">{{ $delivery_mode === 'draft' ? __('New posts stay in Drafts until you approve their content and schedule them in Publishing.') : __('Generated posts enter the publishing queue at their assigned time without a manual review.') }}</p>
<div class="ai-cost"><strong>{{ __('Estimated credits per generation: :credits', ['credits'=>($textCreditPreview['amount'] + ($include_media === 'ai_image' ? $creditPreview['amount'] : 0))]) }}</strong><p>{{ __('One caption shared across selected channels') }}{{ $include_media === 'ai_image' ? __(' + one AI image.') : '.' }}</p><p>{{ $textCreditPreview['unlimited'] ? __('Unlimited plan credits') : __(':credits credits remaining',['credits'=>number_format($textCreditPreview['remaining'] ?? 0)]) }}</p></div>
<div class="grid gap-4 sm:grid-cols-2"><x-ui.input type="number" min="0" wire:model.defer="run_budget" :label="__('Credits per attempt')" :error="$errors->first('run_budget')" /><x-ui.input type="number" min="0" wire:model.defer="monthly_budget" :label="__('Credits per month')" :error="$errors->first('monthly_budget')" /></div>
<p class="ai-muted">{{ __('Budget limits stop generation before it exceeds the allowance. Enter 0 for no schedule limit.') }}</p>
<x-ui.checkbox wire:model.defer="enabled" :label="__('Enable schedule')" />
@if($isEditing)<p class="ai-muted">{{ __('Changes apply to future attempts. Existing posts and unfinished attempts keep their saved content and settings.') }}</p>@endif
@if($errors->any())<div class="ai-error-summary" role="alert"><strong>{{ __('Please check these settings') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<x-ui.button type="submit" size="lg" wire:loading.attr="disabled"><span wire:loading.remove wire:target="save">{{ $isEditing ? __('Save changes') : __('Create schedule') }}</span><span wire:loading wire:target="save">{{ __('Saving...') }}</span></x-ui.button>
</div></section>
<section class="ai-card"><header><h2>{{ __('Try one caption') }}</h2><p>{{ __('Preview the first selected prompt before enabling your schedule.') }}</p></header><div class="ai-card-body">
<p class="ai-muted">{{ __('Preview cost: :credits credits, separate from schedule budgets. No image or post is created.', ['credits'=>$textCreditPreview['amount']]) }}</p>
<x-ui.button type="button" variant="outline" wire:click="previewContent" wire:confirm="{{ __('Generate a text preview for :credits credits?', ['credits'=>$textCreditPreview['amount']]) }}" wire:loading.attr="disabled">{{ __('Generate preview') }}</x-ui.button><span class="ai-muted" wire:loading wire:target="previewContent">{{ __('Generating caption...') }}</span>
@error('preview')<p class="ai-error" role="alert">{{ $message }}</p>@enderror
@if($preview)<p x-show="!previewCurrent()" class="ai-note" role="status">{{ __('Your settings changed. Generate a new preview to see the updated caption.') }}</p><div x-show="previewCurrent()"><p class="ai-muted">{{ __('Generated from:') }} {{ $preview['prompt'] }}</p><pre class="ai-caption">{{ $preview['caption'] }}</pre><p class="ai-muted">{{ __('This is a sample. Each scheduled generation creates new content. Generate again after changing your settings.') }}</p></div>@else<div class="ai-preview-empty"><i class="fa-light fa-file-lines" aria-hidden="true"></i><p>{{ __('Your sample caption will appear here.') }}</p></div>@endif
</div></section>
</aside></form></div>
