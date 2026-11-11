<form wire:submit="previewImport" class="bulk-layout">
<div class="bulk-stack">
<section class="bulk-card"><header><div><h2>{{ __('1. Upload your CSV') }}</h2><p class="bulk-muted">{{ __('Up to :rows rows, 10 MB. UTF-8 CSV with comma-separated columns.', ['rows'=>number_format($rowLimit)]) }}</p></div></header><div class="bulk-body">
<label class="bulk-upload" for="bulk-posts-csv-file"><strong>{{ __('Choose a CSV file') }}</strong><input id="bulk-posts-csv-file" type="file" wire:model="csv_file" accept=".csv,text/csv,text/plain" aria-describedby="bulk-file-help"/><span class="bulk-muted" wire:loading wire:target="csv_file">{{ __('Uploading file...') }}</span>@if($csv_file)<span>{{ $csv_file->getClientOriginalName() }}</span>@endif</label>
<p class="bulk-muted" id="bulk-file-help">{{ __('Required: caption. Optional: schedule_time, link, media_url, status_mode. Quoted captions can contain commas and line breaks.') }}</p>
<div class="bulk-note">{{ __('Text and links are supported. Rows with media_url are flagged for correction; to use images or videos, import text drafts and attach media in Publishing.') }}</div>
</div></section>
<section class="bulk-card"><header><div><h2>{{ __('2. Choose destinations') }}</h2><p class="bulk-muted">{{ __('Each valid row creates one post for every selected channel.') }}</p></div></header><div class="bulk-body">
<x-ui.channel-selector name="account_ids" wire-model="account_ids" :options="$bulkChannelOptions" :network-options="$bulkChannelNetworks" :selected="collect($account_ids)->map(fn($id)=>(string)$id)->all()" :label="__('Channels')" :description="__('Select active channels for this import.')" :error="$errors->first('account_ids')" :placeholder="__('Choose channels')" :empty-label="__('No active channels available.')" :multiple="true" :show-network-filters="true" :connect-href="route('portal.channels')" :connect-label="__('Manage channels')" :inline-panel="true" />
<details class="bulk-advanced"><summary>{{ __('Campaign and labels') }}</summary><div><x-ui.select wire:model="campaign_id" :label="__('Campaign')"><option value="">{{ __('No campaign') }}</option>@foreach($campaigns as $campaign)<option value="{{ $campaign->id }}">{{ $campaign->name }}</option>@endforeach</x-ui.select>
<x-ui.tag-selector name="label_ids" wire-model="label_ids" :label="__('Labels')" :options="$labels->map(fn($label)=>['key'=>(string)$label->id,'label'=>$label->name])->all()" :selected="collect($label_ids)->map(fn($id)=>(string)$id)->all()" :placeholder="__('Choose labels...')" :empty-label="__('No matching labels.')" />
</div></details></div></section>
</div>
<aside class="bulk-card" aria-label="{{ __('Import settings') }}"><header><div><h2>{{ __('Delivery & timing') }}</h2><p class="bulk-muted">{{ __('Timezone: :timezone', ['timezone'=>$timezone]) }}</p></div></header><div class="bulk-body">
<x-ui.select wire:model.live="delivery_mode" :label="__('Delivery mode')"><option value="draft">{{ __('Create drafts for review') }}</option><option value="schedule">{{ __('Schedule all valid rows') }}</option><option value="csv">{{ __('Use CSV modes (empty = draft)') }}</option></x-ui.select>
@if($delivery_mode==='draft')<p class="bulk-note">{{ __('All imported posts stay in Drafts, even when the CSV says schedule. Nothing is queued until you schedule it in Publishing.') }}</p>@else
<x-ui.input type="datetime-local" wire:model="start_at" :label="__('Start time for rows without a date')" />
<x-ui.input type="number" min="1" max="43200" wire:model="interval_minutes" :label="__('Spacing between undated posts (minutes)')" />
<p class="bulk-muted">{{ __('CSV dates take priority. Empty dates use this start time and spacing. Past or invalid dates are flagged; they never become immediate posts.') }}</p>
@endif
<div class="bulk-note">{{ __('Previewing creates no posts. You will see row errors, resolved times and the total number of posts before confirming.') }}</div>
<x-ui.button type="submit" wire:loading.attr="disabled" wire:target="csv_file,previewImport" :disabled="(bool)$activeBatchId || $rowLimit===0"><span wire:loading.remove wire:target="previewImport">{{ __('Review import') }}</span><span wire:loading wire:target="previewImport">{{ __('Checking CSV...') }}</span></x-ui.button>
</div></aside>
</form>
