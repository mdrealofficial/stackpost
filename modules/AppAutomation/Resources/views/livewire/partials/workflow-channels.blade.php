<div class="automation-destinations">
    <section class="automation-channel-picker" aria-label="{{ __('Publishing channels') }}">
        <div class="automation-channel-heading">
            <div><h3>{{ __('Publishing channels') }}</h3><p>{{ __('Choose where each new draft will be created.') }}</p></div>
            <span class="automation-selection-count" x-text="@js(__(':count selected')).replace(':count',nodeIds(selectedNode,'account_ids').length)"></span>
        </div>
        <div class="automation-channel-search"><i class="fa-light fa-magnifying-glass" aria-hidden="true"></i><x-ui.input type="search" id="workflow-channel-search" x-model="channelSearch" :placeholder="__('Find a channel…')" :aria-label="__('Find a channel')" /></div>
        <div class="automation-channel-filters">
            <x-ui.select class="automation-channel-provider" x-model="channelProvider" aria-label="{{ __('Filter by network') }}"><option value="" :selected="channelProvider===''">{{ __('All networks') }}</option><template x-for="provider in channelProviders()" :key="provider"><option :value="provider" :selected="channelProvider===provider" x-text="provider"></option></template></x-ui.select>
            <button type="button" :aria-pressed="selectedChannelsOnly" :class="{'is-active':selectedChannelsOnly}" x-on:click="selectedChannelsOnly=!selectedChannelsOnly"><i class="fa-light fa-check" aria-hidden="true"></i>{{ __('Selected only') }}</button>
        </div>
        <div class="automation-channel-grid" x-show="visibleChannelCount()>0">
            @foreach ($publishingAccounts as $account)
                <div class="automation-channel-item" x-show="channelVisible(@js($account))" x-cloak>
                    <x-ui.checkbox
                        minimal
                        id="workflow-channel-{{ $account['id'] }}"
                        class="automation-channel-card"
                        :value="$account['id']" x-bind:checked="nodeHasId(selectedNode,'account_ids',$el.value)"
                        x-on:change="toggleNodeId(selectedNode,'account_ids',$event.target.value)"
                    >
                        <span class="automation-channel-identity">
                            <span class="automation-channel-mark"><i :class="providerIcon(@js($account['provider']))" aria-hidden="true"></i></span>
                            <span class="automation-channel-name"><strong title="{{ $account['name'] }}">{{ $account['name'] }}</strong><span x-text="providerLabel(@js($account['provider']))"></span><small title="{{ $account['username'] }}">{{ $account['username'] ? '@'.ltrim($account['username'], '@') : __('Connected account') }}</small></span>
                        </span>
                    </x-ui.checkbox>
                </div>
            @endforeach
        </div>
        <div class="automation-channel-empty" x-show="visibleChannelCount()===0" x-cloak>
            <i class="fa-light fa-magnifying-glass" aria-hidden="true"></i><strong>{{ __('No matching channels') }}</strong>
            <p x-text="publishingAccounts.length ? @js(__('Try another search or network, or turn off Selected only.')) : @js(__('Connect an eligible publishing account to continue.'))"></p>
            <button type="button" x-show="publishingAccounts.length" x-on:click="channelSearch='';channelProvider='';selectedChannelsOnly=false">{{ __('Reset filters') }}</button>
            <a x-show="!publishingAccounts.length" href="{{ route('portal.channels') }}" wire:navigate>{{ __('Open Channels') }}</a>
        </div>
        <p class="automation-channel-footnote"><i class="fa-light fa-circle-info" aria-hidden="true"></i>{{ __('Only enabled channels eligible for publishing are listed.') }}</p>
    </section>
    <aside class="automation-channel-options" aria-label="{{ __('Draft organization') }}">
        <div class="automation-organize-heading"><i class="fa-light fa-folder-open" aria-hidden="true"></i><div><h3>{{ __('Keep drafts organized') }}</h3><p>{{ __('Optional') }}</p></div></div>
        <div class="automation-field"><label for="workflow-draft-campaign">{{ __('Campaign') }}</label><x-ui.select id="workflow-draft-campaign" x-model="selectedNode.config.campaign_id" x-on:change="changed()"><option value="">{{ __('No campaign') }}</option><template x-for="campaign in publishingCampaigns" :key="campaign.id"><option :value="campaign.id" :selected="String(selectedNode.config.campaign_id)===String(campaign.id)" x-text="campaign.name"></option></template></x-ui.select></div>
        <div class="automation-field"><label>{{ __('Labels') }}</label><div class="automation-channel-labels" x-show="publishingLabels.length"><template x-for="label in publishingLabels" :key="label.id"><button type="button" class="automation-chip" :class="{'is-active':nodeHasId(selectedNode,'label_ids',label.id)}" :aria-pressed="nodeHasId(selectedNode,'label_ids',label.id)" x-on:click="toggleNodeId(selectedNode,'label_ids',label.id)"><span class="automation-color-dot" :style="`--option-color:${label.color}`"></span><span x-text="label.name"></span><i x-show="nodeHasId(selectedNode,'label_ids',label.id)" class="fa-light fa-check" aria-hidden="true"></i></button></template></div><p class="automation-field-help" x-show="!publishingLabels.length">{{ __('No labels yet.') }}</p></div>
        <div class="automation-channel-summary"><i class="fa-light fa-file-pen" aria-hidden="true"></i><strong x-text="nodeIds(selectedNode,'account_ids').length ? @js(__(':count draft destinations')).replace(':count',nodeIds(selectedNode,'account_ids').length) : @js(__('Choose your first channel'))"></strong><p>{{ __('Campaign and labels apply to every draft created by this step.') }}</p></div>
    </aside>
</div>
