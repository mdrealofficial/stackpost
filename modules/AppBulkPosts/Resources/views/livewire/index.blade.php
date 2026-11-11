<div class="bulk-workspace" x-data x-on:bulk-review-ready.window="$nextTick(()=>{const review=$el.querySelector('#bulk-review');review?.scrollIntoView({behavior:'smooth',block:'start'});review?.focus({preventScroll:true})})">
@include('appbulkposts::partials.styles')
<x-ui.sub-header compact :title="$screen === 'new' ? __('Bulk Import') : __('Import history')" :description="$screen === 'new' ? __('Upload your CSV, choose channels and review every row before importing.') : __('Track your imports, review results and continue unfinished work.')"><x-slot:actions>@if($screen === 'new')<x-ui.button :href="route('portal.bulk-posts.template')" variant="outline"><i class="fa-light fa-download" aria-hidden="true"></i>{{ __('Download CSV template') }}</x-ui.button>@endif</x-slot:actions></x-ui.sub-header>
<nav class="bulk-navigation" aria-label="{{ __('Bulk Import navigation') }}">
    <x-ui.button :href="route('portal.bulk-posts')" variant="ghost" wire:navigate :aria-current="$screen === 'new' ? 'page' : 'false'"><i class="fa-light fa-arrow-up-from-bracket" aria-hidden="true"></i>{{ __('New import') }}</x-ui.button>
    <x-ui.button :href="route('portal.bulk-posts.history', $historyParameters)" variant="ghost" wire:navigate :aria-current="$screen !== 'new' ? 'page' : 'false'"><i class="fa-light fa-clock-rotate-left" aria-hidden="true"></i>{{ __('Import history') }}</x-ui.button>
</nav>
@if($errors->any())<div class="bulk-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($message)<div class="bulk-note" role="status">{{ $message }}</div>@endif
@if($activeBatchId)<div class="bulk-note" wire:poll.2s="continueImport">{{ __('Import in progress. Keep this page open; you can also continue later from Import history.') }}</div>@endif
@if($screen === 'new')
    <div class="bulk-steps" aria-label="{{ __('Import steps') }}"><span><b>1</b>{{ __('Upload') }}</span><span><b>2</b>{{ __('Choose destinations') }}</span><span><b>3</b>{{ __('Review & import') }}</span></div>
    @if($rowLimit===0)<div class="bulk-note">{{ __('Your plan enables Bulk Import but currently allows 0 CSV rows. Importing is unavailable until the plan allowance is updated. Your existing history remains available.') }} <a href="{{ route('portal.packages') }}" class="underline">{{ __('Manage plan') }}</a></div>@endif
    @include('appbulkposts::partials.import-form')
    @include('appbulkposts::partials.import-details')
@elseif($screen === 'history')
    @include('appbulkposts::partials.import-history')
@else
    <div class="bulk-detail-navigation"><a href="{{ route('portal.bulk-posts.history', $historyParameters) }}" wire:navigate><i class="fa-light fa-arrow-left" aria-hidden="true"></i>{{ __('Back to import history') }}</a><span>{{ __('Created on :date', ['date' => $selectedBatch->created_at->copy()->timezone($timezone)->format('d M Y, H:i')]) }} · {{ $timezone }}</span></div>
    @include('appbulkposts::partials.import-details')
@endif
</div>
