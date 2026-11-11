<div class="engagement-workspace" data-module="{{ $moduleKey }}">
    @include('social-automation::styles')
    <x-ui.sub-header :compact="true" :title="__($module['label'])" :description="$module['interaction'] === 'message' ? __('Manage automatic replies to Facebook and Instagram messages.') : __('Manage automatic replies to customer comments on Facebook and Instagram.')"><x-slot:actions>@if($canManage && !$editing)<x-ui.button wire:click="newRule">{{ __('Create rule') }}</x-ui.button>@endif</x-slot:actions></x-ui.sub-header>

    @if(!$editing)
        @if($tab !== 'reports' && !($tab === 'activity' && $activityFromReport))
        @php
            $attentionCount = ($counts['failed'] ?? 0) + ($counts['unknown'] ?? 0) + ($counts['handoff'] ?? 0);
            $workspaceMetrics = [
                [
                    'label' => __('Active rules'),
                    'value' => $activeRules.' / '.$totalRules,
                    'description' => $activeRules > 0 ? __('Automation is listening now') : __('Ready when you activate a rule'),
                    'icon' => 'fa-light fa-bolt',
                    'tone' => 'var(--theme-accent)',
                     'iconSurface' => 'rgba(var(--theme-accent-rgb), .12)',
                     'iconBorder' => 'rgba(var(--theme-accent-rgb), .2)',
                     'spark' => 'M2 28 C10 28 10 18 18 18 S26 28 34 22 S42 10 50 16 S58 10 62 10',
                    'cardStyle' => 'border-color:rgba(var(--theme-accent-rgb),.24);background:linear-gradient(145deg,rgba(var(--theme-accent-rgb),.09),var(--theme-surface-base) 58%);box-shadow:inset 0 3px 0 var(--theme-accent),0 18px 38px -34px rgba(var(--theme-accent-rgb),.75);',
                ],
                [
                    'label' => __('Replies sent'),
                    'value' => number_format($counts['sent'] ?? 0),
                    'description' => __('Delivered in the last 30 days'),
                    'icon' => 'fa-light fa-paper-plane',
                    'tone' => 'var(--theme-success-color, #16856f)',
                     'iconSurface' => 'color-mix(in srgb,var(--theme-success-color,#16856f) 11%,transparent)',
                     'iconBorder' => 'color-mix(in srgb,var(--theme-success-color,#16856f) 20%,transparent)',
                     'spark' => 'M2 22 C10 22 11 30 18 25 S27 8 34 16 S43 26 50 16 S57 20 62 14',
                    'cardStyle' => 'box-shadow:inset 0 3px 0 color-mix(in srgb,var(--theme-success-color,#16856f) 72%,transparent);',
                ],
                [
                    'label' => __('Awaiting review'),
                    'value' => number_format($counts['review'] ?? 0),
                    'description' => __('Waiting for your approval'),
                    'icon' => 'fa-light fa-pen-to-square',
                    'tone' => 'var(--theme-warning-color, #b7791f)',
                     'iconSurface' => 'color-mix(in srgb,var(--theme-warning-color,#b7791f) 11%,transparent)',
                     'iconBorder' => 'color-mix(in srgb,var(--theme-warning-color,#b7791f) 20%,transparent)',
                     'spark' => 'M2 26 C10 26 12 14 20 18 S28 30 36 22 S45 10 52 17 S58 15 62 12',
                    'cardStyle' => 'box-shadow:inset 0 3px 0 color-mix(in srgb,var(--theme-warning-color,#b7791f) 72%,transparent);',
                ],
                [
                    'label' => __('Needs attention'),
                    'value' => number_format($attentionCount),
                    'description' => $attentionCount > 0 ? __('Review delivery issues') : __('Everything looks healthy'),
                    'icon' => 'fa-light fa-triangle-exclamation',
                    'tone' => $attentionCount > 0 ? 'var(--theme-danger-color, #c2415d)' : 'var(--theme-muted-text-color)',
                     'iconSurface' => $attentionCount > 0 ? 'color-mix(in srgb,var(--theme-danger-color,#c2415d) 11%,transparent)' : 'var(--theme-surface-soft)',
                     'iconBorder' => $attentionCount > 0 ? 'color-mix(in srgb,var(--theme-danger-color,#c2415d) 20%,transparent)' : 'var(--theme-border-color)',
                     'spark' => 'M2 18 C10 18 12 20 18 20 S27 18 34 20 S43 18 50 20 S58 18 62 18',
                    'cardStyle' => $attentionCount > 0 ? 'box-shadow:inset 0 3px 0 color-mix(in srgb,var(--theme-danger-color,#c2415d) 72%,transparent);' : 'box-shadow:inset 0 3px 0 color-mix(in srgb,var(--theme-border-color) 72%,transparent);',
                ],
            ];
        @endphp
        <x-ui.metric-strip
            class="my-5"
             :items="$workspaceMetrics"
             columns="grid-cols-2 lg:grid-cols-4"
             gap="gap-3"
             variant="portal"
             :show-progress="false"
        />
        @endif
        <nav class="my-5 inline-flex max-w-full gap-1 overflow-x-auto rounded-lg border p-1" style="border-color:var(--theme-border-color);background:var(--theme-surface-base)" aria-label="{{ __('Automation sections') }}">
            @foreach(['rules'=>__('Rules'),'activity'=>__('Activity & review'),'reports'=>__('Reports')] as $value=>$label)
                <x-ui.button variant="ghost" size="sm" wire:click="$set('tab','{{ $value }}')" :style="$tab === $value ? 'background:var(--theme-surface-soft);color:var(--theme-accent)' : ''" :aria-current="$tab === $value ? 'page' : 'false'">{{ $label }}</x-ui.button>
            @endforeach
        </nav>
        @include('social-automation::connection-status')
        @if($tab === 'rules')

            @if($totalRules > 0 || $search !== '')<div class="eng-tools"><x-ui.icon-input icon="fa-light fa-magnifying-glass" aria-label="{{ __('Search rules') }}" placeholder="{{ __('Search rulesâ€¦') }}" wire:model.live.debounce.300ms="search" /><span>{{ __('Lower priority numbers run first. One matching rule per interaction.') }}</span></div>@endif
            @if($rules->isEmpty() && $search !== '')
                <section class="eng-empty"><h2>{{ __('No matching rules') }}</h2><p>{{ __('Try a different name or clear your search.') }}</p><x-ui.button  wire:click="$set('search','')" variant="outline" size="sm">{{ __('Clear search') }}</x-ui.button></section>
            @elseif($rules->isEmpty())
                @include('social-automation::welcome')
            @else
                <div class="eng-rule-grid">
                    @foreach($rules as $rule)
                        <article class="eng-rule" wire:key="rule-{{ $rule->id }}">
                            <div class="eng-rule-details">
                            <div class="eng-rule-top"><span class="eng-icon"><i class="fa-light {{ ($rule->settings['response_type'] ?? '') === 'ai' ? 'fa-sparkles' : 'fa-message-lines' }}"></i></span><span class="eng-badge {{ $rule->active ? 'is-on' : '' }}">{{ $rule->active ? __('Active') : __('Paused') }}</span></div>
                            <h2>{{ $rule->name }}</h2><p>{{ count($rule->settings['account_ids']) }} {{ __('channels') }} Â· {{ __('Priority') }} {{ $rule->priority }} Â· {{ ($rule->settings['delivery'] ?? '') === 'auto' ? __('Automatic delivery') : __('Review before sending') }}</p>
                            @if($accounts->whereIn('id',$rule->settings['account_ids'])->contains('is_active',false))<p class="eng-readiness">{{ __('A selected channel is inactive. Reconnect it before delivery can continue.') }}</p>@endif
                            </div>
                            <div class="eng-rule-summary"><span>{{ __('WHEN') }}</span><strong>{{ ($rule->settings['match'] ?? '') === 'all' ? __('Any new interaction') : \Illuminate\Support\Str::limit(str_replace("\n",' Â· ',$rule->settings['keywords'] ?? ''),100) }}</strong><span>{{ __('REPLY WITH') }}</span><div>{{ ($rule->settings['response_type'] ?? '') === 'ai' ? __('AI response using your business information') : \Illuminate\Support\Str::limit($rule->settings['response'] ?? '',150) }}</div></div>
                            @if($canManage)<footer><x-ui.button  wire:click="editRule({{ $rule->id }})" variant="outline" size="sm">{{ __('Edit rule') }}</x-ui.button><div><x-ui.button  wire:click="toggleRule({{ $rule->id }})" wire:loading.attr="disabled" variant="outline" size="sm">{{ $rule->active ? __('Pause') : __('Activate') }}</x-ui.button><x-ui.button class="eng-icon-button" aria-label="{{ __('Delete rule') }}" wire:click="deleteRule({{ $rule->id }})" wire:confirm="{{ __('Delete this rule? Its delivery history will remain.') }}" variant="outline" size="sm"><i class="fa-light fa-trash"></i></x-ui.button></div></footer>@endif
                        </article>
                    @endforeach
                </div>
            @endif
        @elseif($tab === 'reports')
            @include('social-automation::reports')
        @else
            <section class="eng-panel eng-activity-controls" aria-label="{{ __('Activity filters') }}">
                <div class="eng-activity-toolbar">
                    <div class="eng-activity-toolbar-title"><h2>{{ __('Activity & review') }}</h2><p>{{ __('Review replies and track delivery outcomes.') }}</p></div>
                    <x-ui.select class="eng-activity-status" wire:model.live="status" :aria-label="__('Filter activity status')">
                        <option value="">{{ __('All activity') }}</option>
                        @foreach(['pending'=>__('Processing'),'review'=>__('Awaiting review'),'sent'=>__('Sent'),'skipped'=>__('Skipped'),'handoff'=>__('Human support'),'failed'=>__('Failed'),'unknown'=>__('Delivery unconfirmed')] as $state=>$label)<option value="{{ $state }}">{{ $label }}</option>@endforeach
                    </x-ui.select>
                </div>
                @if($activityFromReport)<div class="eng-activity-report-scope"><span>{{ __('Showing activity from report filters') }} Â· {{ $reportDays }} {{ __('days') }}</span><div><x-ui.button variant="outline" size="sm" wire:click="$set('tab','reports')">{{ __('Back to report') }}</x-ui.button><x-ui.button variant="outline" size="sm" wire:click="clearActivityScope">{{ __('Show all activity') }}</x-ui.button></div></div>@endif
            </section>
            <div class="eng-activity" wire:poll.20s>
                @forelse($runs as $run)
                    <article class="eng-panel" wire:key="run-{{ $run->id }}"><div class="eng-activity-head"><strong>{{ $run->snapshot['name'] ?? __('Deleted rule') }}</strong><span class="eng-badge {{ $run->status === 'sent' ? 'is-on' : '' }}">{{ ucfirst($run->status) }}</span><time>{{ $run->created_at->format('M j, H:i') }}</time></div>
                        @php($incoming = $inboundMessages->get($run->inbound_message_id))
                        @if($incoming)<p>{{ ucfirst($incoming->provider_key) }} Â· {{ $incoming->sender_name ?: __('Customer') }}</p><div class="eng-rule-summary"><span>{{ __('CUSTOMER INTERACTION') }}</span><div>{{ $incoming->body }}</div></div>@endif
                        @if(isset($reviewEdits[$run->id]))<div class="space-y-1"><x-ui.textarea :label="__('Edit response')" wire:model="reviewEdits.{{ $run->id }}" maxlength="1000" rows="4"></x-ui.textarea></div>@error('reviewEdits.'.$run->id)<p class="eng-errors">{{ $message }}</p>@enderror
                        @elseif($run->reply)<blockquote>{{ $run->reply }}</blockquote>@endif
                        @if($run->reason)<p>{{ __($run->reason) }}</p>@endif
                        @if(data_get($run->snapshot,'original_reply'))<details><summary class="eng-link">{{ __('Original draft before editing') }}</summary><blockquote>{{ data_get($run->snapshot,'original_reply') }}</blockquote></details>@endif
                        @if($canManage && $run->status === 'review')<div class="eng-actions"><x-ui.button  wire:click="approve({{ $run->id }})" wire:confirm="{{ __('Approve this exact response for delivery?') }}" variant="primary" size="sm">{{ __('Approve reply') }}</x-ui.button><x-ui.button  wire:click="discard({{ $run->id }})" variant="outline" size="sm">{{ __('Dismiss') }}</x-ui.button></div>@endif
                        @if($canManage && $run->status === 'review' && data_get($run->snapshot,'settings.editable_review_enabled'))<x-ui.button  wire:click="editReply({{ $run->id }})" variant="outline" size="sm">{{ __('Edit before approving') }}</x-ui.button>@endif
                        @if($run->status === 'handoff')<div class="eng-actions"><span>{{ $incoming?->conversation?->assignee?->name ?: __('Unassigned') }}</span>@if($run->handoff_resolved_at)<span>{{ __('Automation resumed') }}</span>@elseif($canManage && (!$incoming?->conversation?->assigned_user_id || (int)$incoming->conversation->assigned_user_id === (int)auth()->id()))<x-ui.button  wire:click="resumeConversation({{ $run->id }})" wire:confirm="{{ __('Finish human support and resume automation for new interactions? No previous reply will be sent.') }}" variant="outline" size="sm">{{ __('Resume automation') }}</x-ui.button>@endif</div>@endif
                        @if($canManage && $run->status === 'unknown')<div class="eng-actions"><x-ui.button  wire:click="reconcile({{ $run->id }},'sent')" wire:confirm="{{ __('Have you checked the social network and confirmed this reply was sent?') }}" variant="outline" size="sm">{{ __('Confirm sent') }}</x-ui.button><x-ui.button  wire:click="reconcile({{ $run->id }},'not_sent')" wire:confirm="{{ __('Have you checked the social network and confirmed this reply was not sent? This will close the attempt without resending.') }}" variant="outline" size="sm">{{ __('Confirm not sent') }}</x-ui.button></div>@endif
                    </article>
                @empty
                    <section class="eng-empty"><span class="eng-icon"><i class="fa-light fa-clock-rotate-left"></i></span><h2>{{ __('Your activity will appear here') }}</h2><p>{{ __('New matching interactions will show their rule, response and delivery status.') }}</p></section>
                @endforelse
            </div>
            {{ $runs->links() }}
        @endif
    @else
        <div class="eng-editor-heading"><x-ui.button  wire:click="closeEditor" variant="outline" size="sm"><i class="fa-light fa-arrow-left"></i>{{ __('Back to rules') }}</x-ui.button><span>{{ $editingId ? __('Edit rule') : __('New rule') }} Â· {{ __('Changes are saved paused') }}</span></div>
        @include('social-automation::connection-status')
        @if($errors->any())<div class="eng-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form wire:submit="saveRule" class="eng-editor" x-data="{ section: 'audience' }" wire:key="editor-{{ $editingId ?? 'new' }}">
            <nav class="eng-editor-nav" aria-label="{{ __('Rule configuration') }}">
                @foreach(['audience'=>[__('Channels'),'fa-layer-group'],'trigger'=>[__('Trigger'),'fa-bolt'],'response'=>[__('Response'),'fa-message-lines'],'options'=>[__('Options'),'fa-sliders'],'limits'=>[__('Limits'),'fa-shield-check']] as $section=>$item)
                    <button type="button" x-on:click="section = '{{ $section }}'" :class="section === '{{ $section }}' ? 'active' : ''" :aria-pressed="section === '{{ $section }}'"><span>{{ $loop->iteration }}</span><i class="fa-light {{ $item[1] }}"></i>{{ $item[0] }}</button>
                @endforeach
                <button type="button" class="eng-show-all" x-on:click="section = 'all'" :class="section === 'all' ? 'active' : ''" :aria-pressed="section === 'all'">{{ __('Show all') }}</button>
            </nav>
            <div class="eng-editor-fields">
                <section class="eng-panel eng-edit-section" x-show="section === 'audience' || section === 'all'"><div class="eng-section-title"><span>01</span><div><h2>{{ __('Choose your audience') }}</h2><p>{{ __('Decide where this rule should listen.') }}</p></div></div>
                    <div class="space-y-1"><x-ui.input :label="__('Rule name')" wire:model="form.name" maxlength="120" placeholder="{{ __('For example: Product questions') }}" /></div>
                    <div class="eng-field"><span>{{ __('Connected channels') }}</span><div class="eng-account-list">
                        @forelse($accounts as $account)
                            @php($channel = $readiness['channels']->firstWhere('id',$account->id))
                            <div class="eng-account">
                                <x-ui.checkbox wire:model.live="form.account_ids" :value="(string)$account->id" :id="'eng-account-'.$account->id">
                                    <span class="eng-account-choice">@include('social-automation::channel-avatar',['channel'=>$channel])<span class="eng-channel-identity"><strong>{{ $channel['name'] }}</strong><span>{{ $channel['username'] ? '@'.ltrim($channel['username'],'@') : ucfirst($channel['provider']) }}</span></span></span>
                                </x-ui.checkbox>
                                @if($channel['issue'])<div class="eng-account-help"><span>{{ $channel['issue'] }}</span><a class="eng-link" href="{{ $channel['url'] }}" wire:navigate>{{ $channel['action'] }} <i class="fa-light fa-arrow-up-right"></i></a></div>@endif
                            </div>
                        @empty<p>{{ __('No eligible channels. Connect Facebook or Instagram in Channels first.') }}</p>@endforelse
                    </div></div>
                    @if($module['interaction'] === 'comment')<div class="space-y-1"><x-ui.textarea :label="__('Specific post or media IDs (optional)')" wire:model="form.post_ids" rows="2" placeholder="{{ __('One ID per line. Leave empty for all your posts.') }}"></x-ui.textarea></div>@endif
                    @if($module['interaction'] === 'comment')
                        <x-ui.checkbox wire:model.live="showPostPicker" id="eng-post-picker" :label="__('Browse published posts')" />
                        @if($showPostPicker)<div class="eng-post-picker"><p>{{ __('Recent posts published through this workspace, from your selected channels. For other posts, enter the ID above.') }}</p>
                            @forelse($publishedPosts as $post)
                                @php($postSelected = in_array((string)data_get($post->result,'remote_post_id'),app(\App\Social\Automation\Matcher::class)->lines($form['post_ids'] ?? ''),true))
                                @php($thumbnail = (string)data_get($post->data,'medias.0.previewUrl',data_get($post->data,'medias.0.url','')))
                                <button type="button" class="eng-post-option {{ $postSelected ? 'is-selected' : '' }}" wire:click="selectPost({{ $post->id }})" aria-pressed="{{ $postSelected ? 'true' : 'false' }}">
                                    @if(preg_match('~^https?://~i',$thumbnail))<img src="{{ $thumbnail }}" alt="{{ __('Post preview') }}" loading="lazy" referrerpolicy="no-referrer">@else<i class="fa-light fa-file-lines"></i>@endif
                                    <span><strong>{{ \Illuminate\Support\Str::limit((string)data_get($post->data,'caption',__('Published post')),160) }}</strong><small>{{ $post->account?->display_name }} Â· {{ data_get($post->result,'remote_post_id') }}</small></span>@if($postSelected)<i class="fa-light fa-circle-check"></i>@endif</button>
                            @empty<p>{{ __('No published posts with a confirmed network ID for these channels.') }}</p>@endforelse
                        </div>@endif
                    @endif
                </section>
                <section class="eng-panel eng-edit-section" x-show="section === 'trigger' || section === 'all'" x-cloak><div class="eng-section-title"><span>02</span><div><h2>{{ __('Set the trigger') }}</h2><p>{{ __('Match the right interactions and leave the rest to your team.') }}</p></div></div>
                    <div class="space-y-1"><x-ui.select :label="__('Match type')" wire:model.live="form.match"><option value="contains">{{ __('Contains a keyword') }}</option><option value="exact">{{ __('Exact keyword match') }}</option><option value="all">{{ __('All new interactions') }}</option></x-ui.select></div>
                    @if(($form['match'] ?? '') !== 'all')<div class="space-y-1"><x-ui.textarea :label="__('Keywords')" wire:model="form.keywords" rows="3" placeholder="{{ __('One keyword or phrase per line') }}"></x-ui.textarea><small>{{ __('Matches any listed phrase, ignoring uppercase and lowercase.') }}</small></div>@endif
                    <div class="space-y-1"><x-ui.textarea :label="__('Skip these words (optional)')" wire:model="form.exclude" rows="2" placeholder="{{ __('One word or phrase per line') }}"></x-ui.textarea></div>
                </section>
                <section class="eng-panel eng-edit-section" x-show="section === 'response' || section === 'all'" x-cloak><div class="eng-section-title"><span>03</span><div><h2>{{ __('Write a helpful response') }}</h2><p>{{ __('Keep it concise, relevant and true to your brand.') }}</p></div></div>
                    <div class="space-y-1"><x-ui.select :label="__('Response type')" wire:model.live="form.response_type"><option value="template">{{ __('Saved response') }}</option><option value="ai">{{ __('AI using business information') }}</option></x-ui.select></div>
                    @if(($form['response_type'] ?? '') === 'ai')
                        <div class="space-y-1"><x-ui.textarea :label="__('Business information & FAQ')" wire:model="form.knowledge" rows="6" maxlength="8000" placeholder="{{ __('Provide the facts AI is allowed to use: products, prices, hours, approved policies and FAQs.') }}"></x-ui.textarea><small>{{ __('Missing facts or sensitive questions are handed to your team. AI credits apply to successfully generated replies, including review drafts.') }}</small></div>
                        <div class="space-y-1"><x-ui.textarea :label="__('Writing guidance (optional)')" wire:model="form.guidance" rows="2" placeholder="{{ __('For example: Friendly, concise, no emojis.') }}"></x-ui.textarea></div>
                    @else
                        <div class="space-y-1"><x-ui.textarea :label="__('Reply text')" wire:model.live.debounce.400ms="form.response" rows="5" maxlength="1000" placeholder="{{ __('Write the response your customer will seeâ€¦') }}"></x-ui.textarea><small>{{ mb_strlen($form['response'] ?? '') }} / 1000</small></div>
                    @endif
                    <div class="space-y-1"><x-ui.select :label="__('Delivery')" wire:model="form.delivery"><option value="review">{{ __('Create draft for review') }}</option><option value="auto">{{ __('Send automatically') }}</option></x-ui.select></div>
                </section>
                <section class="eng-panel eng-edit-section" x-show="section === 'options' || section === 'all'" x-cloak><div class="eng-section-title"><span>04</span><div><h2>{{ __('Optional features') }}</h2><p>{{ __('Choose the extras your workflow needs.') }}</p></div></div>
                    <div class="eng-options-grid">
                    @foreach([
                        'history_enabled'=>[__('Use conversation history'),__('AI can read up to 8 earlier messages from this conversation. Applies to AI responses only.')],
                        'editable_review_enabled'=>[__('Allow editing before approval'),__('Let reviewers refine a draft before approving it. The original text stays in the activity record.')],
                        'customer_cooldown_enabled'=>[__('Prevent repeated replies to a customer'),__('Apply the cooldown across this customerâ€™s conversations on the same channel, including separate comments.')],
                        'assign_handoff_enabled'=>[__('Assign human support to the rule manager'),__('When AI cannot answer, assign the conversation to the person who last saved this rule.')],
                        'notify_handoff_enabled'=>[__('Notify the rule manager'),__('Show an in-app notification when AI requests human support. No email or direct message is sent.')]
                    ] as $option=>$copy)
                        <div class="eng-option"><span class="eng-option-icon"><i class="fa-light {{ ['history_enabled'=>'fa-clock-rotate-left','editable_review_enabled'=>'fa-pen-line','customer_cooldown_enabled'=>'fa-shield-check','assign_handoff_enabled'=>'fa-user-headset','notify_handoff_enabled'=>'fa-bell'][$option] }}"></i></span><x-ui.checkbox wire:model="form.{{ $option }}" :id="'eng-'.$option" :label="$copy[0]" /><p>{{ $copy[1] }}</p></div>
                    @endforeach
                    </div>
                </section>
                <section class="eng-panel eng-edit-section" x-show="section === 'limits' || section === 'all'" x-cloak><div class="eng-section-title"><span>05</span><div><h2>{{ __('Stay in control') }}</h2><p>{{ __('Choose limits that suit your team and audience.') }}</p></div></div>
                    <div class="eng-field-grid"><div class="space-y-1"><x-ui.input :label="__('Priority (1 runs first)')" type="number" min="1" max="100" wire:model="form.priority" /></div><div class="space-y-1"><x-ui.input :label="__('Maximum replies per day')" type="number" min="1" max="10000" wire:model="form.daily_limit" /></div><div class="space-y-1"><x-ui.input :label="__('Conversation cooldown (seconds)')" type="number" min="0" max="86400" wire:model="form.cooldown" /></div></div>
                    <x-ui.checkbox wire:model.live="form.hours_enabled" id="eng-hours" :label="__('Only run during selected hours')" />
                    @if($form['hours_enabled'] ?? false)<div class="eng-field-grid"><div class="space-y-1"><x-ui.input :label="__('From')" type="time" wire:model="form.start" /></div><div class="space-y-1"><x-ui.input :label="__('Until')" type="time" wire:model="form.end" /></div><div class="space-y-1"><x-ui.select :label="__('Timezone')" wire:model="form.timezone">@foreach(timezone_identifiers_list() as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach</x-ui.select></div></div>@endif
                    <div class="eng-note"><i class="fa-light fa-user-headset"></i><span>{{ __('Assigned conversations stay with your team. A manual Inbox reply pauses automation for 24 hours.') }}</span></div>
                </section>
                <footer class="eng-savebar"><x-ui.button type="button"  wire:click="closeEditor" variant="outline" size="sm">{{ __('Cancel') }}</x-ui.button><div><x-ui.button type="button"  x-show="section !== 'limits' && section !== 'all'" x-on:click="section = ['audience','trigger','response','options','limits'][['audience','trigger','response','options','limits'].indexOf(section) + 1]" variant="outline" size="sm">{{ __('Continue') }}<i class="fa-light fa-arrow-right"></i></x-ui.button><x-ui.button  type="submit" wire:loading.attr="disabled" variant="primary" size="sm">{{ __('Save rule') }}<i class="fa-light fa-check"></i></x-ui.button></div></footer>
            </div>
            <aside class="eng-preview">
                <div class="eng-panel"><div class="eng-eyebrow">{{ __('RULE PREVIEW') }}</div><h2>{{ __('Try the conversation') }}</h2><p>{{ __('Test matching only. Nothing is sent and no AI credits are used.') }}</p>
                    <div class="space-y-1"><x-ui.textarea :label="__('Customer message')" wire:model="sample" rows="3" placeholder="{{ __('What would a customer ask?') }}"></x-ui.textarea></div>
                    @if($module['interaction'] === 'comment')<div class="space-y-1"><x-ui.input :label="__('Post or media ID')" wire:model="samplePostId" placeholder="{{ __('Optional unless the rule targets specific posts') }}" /></div>@endif
                    <x-ui.button type="button"  wire:click="testRule" variant="outline" size="sm">{{ __('Test match') }} <i class="fa-light fa-play"></i></x-ui.button>
                    @if($sampleMatches !== null)<div class="eng-match {{ $sampleMatches ? 'is-match' : '' }}">{{ $sampleMatches ? __('This text matches your conditions.') : __('This text does not match your conditions.') }}</div>@endif
                    <div class="eng-chat-preview"><span class="eng-icon"><i class="fa-light fa-sparkles"></i></span><div>{{ ($form['response_type'] ?? '') === 'ai' ? __('An AI response will be generated from your business information after a real matching interaction.') : ($form['response'] ?: __('Your saved response will appear here.')) }}</div></div>
                    <small>{{ __('This preview checks text and post filters. Live delivery also checks access, hours, limits and human handoff.') }}</small>
                </div>
            </aside>
        </form>
    @endif
</div>
