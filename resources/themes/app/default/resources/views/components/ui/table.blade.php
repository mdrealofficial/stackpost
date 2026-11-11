@props(['responsive' => false])
<div {{ $attributes->merge(['style' => 'border-width: var(--theme-card-border-width, 1px); border-color: var(--theme-card-border-color, var(--theme-border-color)); background-color: var(--theme-surface-base);'])->class('app-ui-table overflow-visible rounded-[1rem] border shadow-none'.($responsive ? ' ui-table-responsive' : '')) }}>
    <div class="overflow-x-auto overflow-y-visible">
        <table class="min-w-full border-separate border-spacing-0">
            {{ $slot }}
        </table>
    </div>
</div>

@if($responsive)
@once
<style>
.ui-table-responsive table{width:100%;table-layout:fixed}.ui-table-responsive th,.ui-table-responsive td{padding:12px;overflow-wrap:anywhere;text-align:left;vertical-align:top;border-bottom:1px solid var(--theme-border-color)}.ui-table-responsive th{font-size:12px;color:var(--theme-muted-text-color);font-weight:500}.ui-table-responsive td{font-size:13px}.ui-table-responsive th:first-child,.ui-table-responsive td:first-child{width:44px}.ui-table-responsive th:last-child{width:150px}.ui-table-responsive tbody tr:last-child td{border-bottom:0}
@media(max-width:900px){.ui-table-responsive table,.ui-table-responsive tbody{display:block}.ui-table-responsive thead{display:none}.ui-table-responsive tbody tr{display:grid;grid-template-columns:44px minmax(0,1fr);border-bottom:1px solid var(--theme-border-color);padding:8px}.ui-table-responsive tbody tr:last-child{border-bottom:0}.ui-table-responsive td{display:block;grid-column:2;border:0;padding:8px;min-width:0}.ui-table-responsive td:first-child{grid-column:1;grid-row:1 / span 5;width:auto}.ui-table-responsive td[data-label]::before{content:attr(data-label);display:block;font-size:11px;color:var(--theme-muted-text-color);margin-bottom:4px}}
</style>
@endonce
@endif
