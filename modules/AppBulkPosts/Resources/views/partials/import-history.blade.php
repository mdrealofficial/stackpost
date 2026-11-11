@php($historyStates = ['completed'=>__('Completed'),'partial'=>__('Partially imported'),'processing'=>__('In progress'),'failed'=>__('Needs attention'),'blocked'=>__('Paused'),'ready'=>__('Not imported'),'draft'=>__('Not imported')])
<x-ui.card padding="none" class="bulk-history-panel">
    <header class="bulk-history-heading"><div><span class="bulk-history-eyebrow">{{ __('IMPORT RECORDS') }}</span><h2>{{ __('Import history') }}</h2><p>{{ __('Every file, its results, and your next step.') }}</p></div><span class="bulk-history-count" role="status">{{ trans_choice(':count import|:count imports', $batches->total(), ['count' => $batches->total()]) }}</span></header>
    <div class="bulk-history-filters">
        <x-ui.input wire:model.live.debounce.300ms="historySearch" :label="__('Search files')" :placeholder="__('Search by filename...')" />
        <x-ui.select wire:model.live="historyStatus" :label="__('Status')"><option value="all">{{ __('All statuses') }}</option>@foreach(['completed','partial','processing','failed','blocked','ready'] as $state)<option value="{{ $state }}">{{ $historyStates[$state] }}</option>@endforeach</x-ui.select>
        <x-ui.select wire:model.live="historyPeriod" :label="__('Period')"><option value="all">{{ __('All dates') }}</option><option value="7">{{ __('Last 7 days') }}</option><option value="30">{{ __('Last 30 days') }}</option><option value="90">{{ __('Last 90 days') }}</option></x-ui.select>
        @if($historySearch !== '' || $historyStatus !== 'all' || $historyPeriod !== 'all')<x-ui.button type="button" wire:click="resetHistoryFilters" variant="outline">{{ __('Clear filters') }}</x-ui.button>@endif
    </div>
    <div class="bulk-history-list" aria-label="{{ __('Import records') }}">
    @forelse($batches as $batch)
        <article class="bulk-import-record" wire:key="bulk-batch-{{ $batch->id }}">
            <div class="bulk-import-file"><span class="bulk-file-icon"><i class="fa-light fa-file-csv" aria-hidden="true"></i></span><div><h3><a href="{{ route('portal.bulk-posts.show', ['batch' => $batch->id] + $historyParameters) }}" wire:navigate>{{ $batch->source_filename ?: $batch->name }}</a></h3><p>{{ $batch->created_at->copy()->timezone($timezone)->format('d M Y, H:i') }} <span>· #{{ $batch->id }}</span></p></div></div>
            <dl class="bulk-import-metrics"><div><dt>{{ __('Rows') }}</dt><dd>{{ number_format(data_get($batch->stats, 'total_rows', 0)) }}</dd></div><div><dt>{{ __('Posts created') }}</dt><dd>{{ number_format(data_get($batch->stats, 'created_posts', 0)) }}</dd></div><div><dt>{{ __('Excluded') }}</dt><dd>{{ number_format(data_get($batch->stats, 'invalid_rows', 0)) }}</dd></div></dl>
            <div class="bulk-import-status"><span class="bulk-badge" data-state="{{ $batch->displayStatus() }}">{{ $historyStates[$batch->displayStatus()] ?? $batch->displayStatus() }}</span></div>
            <x-ui.button :href="route('portal.bulk-posts.show', ['batch' => $batch->id] + $historyParameters)" variant="outline" wire:navigate :aria-label="__('View import #:id', ['id' => $batch->id])">{{ __('View details') }}<i class="fa-light fa-arrow-right" aria-hidden="true"></i></x-ui.button>
        </article>
    @empty
        <div class="bulk-history-empty"><span class="bulk-file-icon"><i class="fa-light fa-clock-rotate-left" aria-hidden="true"></i></span><h3>{{ $historySearch !== '' || $historyStatus !== 'all' || $historyPeriod !== 'all' ? __('No matching imports') : __('Your imports will appear here') }}</h3><p>{{ $historySearch !== '' || $historyStatus !== 'all' || $historyPeriod !== 'all' ? __('Try another filename, status, or date range.') : __('Import a CSV to see its results and review any rows that need attention.') }}</p>@if($historySearch !== '' || $historyStatus !== 'all' || $historyPeriod !== 'all')<x-ui.button type="button" wire:click="resetHistoryFilters" variant="outline">{{ __('Clear filters') }}</x-ui.button>@else<x-ui.button :href="route('portal.bulk-posts')" wire:navigate>{{ __('New import') }}</x-ui.button>@endif</div>
    @endforelse
    </div>
    <footer class="bulk-history-footer"><span>{{ __('Dates shown in :timezone', ['timezone' => $timezone]) }}</span>{{ $batches->links() }}</footer>
</x-ui.card>
