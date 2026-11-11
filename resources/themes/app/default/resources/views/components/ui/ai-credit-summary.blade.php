@props(['preview' => []])
<div class="text-xs leading-6" style="color: var(--theme-muted-text-color);">
    <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ trans_choice(':count credit per run|:count credits per run', (int) ($preview['amount'] ?? 0), ['count' => number_format((int) ($preview['amount'] ?? 0))]) }}</span>
    <span class="block">{{ ($preview['unlimited'] ?? false) ? __('Unlimited plan') : __(':credits credits available', ['credits' => $preview['remaining'] ?? 0]) }}</span>
</div>
