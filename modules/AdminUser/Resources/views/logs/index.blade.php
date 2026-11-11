@component(theme_view('layouts.app', 'app'), ['title' => __('User Logs')])
    <div class="mx-auto max-w-[88rem] space-y-6">
        <x-ui.page-hero
            :eyebrow="__('Audit workspace')"
            :title="__('User Logs')"
            :description="__('Review activity across users with tighter filters and a cleaner event table.')"
            :count="$logs->count()"
            icon="fa-light fa-clipboard-list-check"
        >
            <x-slot:actions>
                <x-ui.button href="{{ route('admin-users.index') }}" variant="outline" wire:navigate>{{ __('Open users') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-hero>

        <x-ui.card class="space-y-4">
            <form method="GET" action="{{ route('admin-user-logs.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px_220px_auto]">
                <x-ui.input name="user_search" :label="__('User search')" :value="$filters['user_search']" :placeholder="__('Search by name, username, or email')" />
                <x-ui.select name="user_id" :label="__('User')">
                    <option value="">{{ __('All users') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) $filters['user_id'] === (string) $user->id)>{{ $user->name }} ({{ '@'.$user->username }})</option>
                    @endforeach
                </x-ui.select>
                <x-ui.date-picker name="date_from" :label="__('Date from')" :value="$filters['date_from']" :placeholder="__('Choose start date')" />
                <x-ui.date-picker name="date_to" :label="__('Date to')" :value="$filters['date_to']" :placeholder="__('Choose end date')" />
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit">{{ __('Apply') }}</x-ui.button>
                    <x-ui.button href="{{ route('admin-user-logs.index') }}" variant="outline" wire:navigate>{{ __('Reset') }}</x-ui.button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                @if ($filters['user_search'])
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">{{ $filters['user_search'] }}</span>
                @endif
                @if ($filters['user_id'])
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                        {{ __('User') }}: {{ $users->firstWhere('id', (int) $filters['user_id'])?->name ?? __('Unknown') }}
                    </span>
                @endif
            </div>
        </x-ui.card>

        <x-ui.datatable-shell :title="__('Audit timeline')" :info="__('Latest 100 log entries after applying filters.')" :footer-text="number_format($logs->count()).' '.__('entries shown')" header-class="py-4" eyebrow-class="text-[10px] tracking-[0.2em]" title-class="mt-1 text-[1.15rem] tracking-[-0.025em]" description-class="mt-1 leading-6">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-ui.table-head>
                    <x-ui.table-cell head>{{ __('User') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Event') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Description') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Area') }}</x-ui.table-cell>
                    <x-ui.table-cell head>{{ __('Date') }}</x-ui.table-cell>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse ($logs as $log)
                        @php
                            $metadataSummary = collect($log->metadata ?? [])
                                ->map(function ($value, $key) {
                                    if (is_array($value) || is_object($value)) {
                                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    } elseif (is_bool($value)) {
                                        $value = $value ? 'true' : 'false';
                                    } elseif ($value === null) {
                                        $value = 'null';
                                    }

                                    return $key.': '.$value;
                                })
                                ->implode(' | ');
                        @endphp
                        <x-ui.table-row>
                            <x-ui.table-cell>
                                <div class="space-y-1">
                                    <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $log->causer?->name ?? __('System') }}</p>
                                    <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $log->causer ? '@'.$log->causer->username.' | '.$log->causer->email : __('No user attached') }}</p>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $log->event }}</p>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="space-y-1">
                                    <p class="font-medium" style="color: var(--theme-header-text-color);">{{ $log->description ?: '-' }}</p>
                                    @if ($metadataSummary !== '')
                                        <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $metadataSummary }}</p>
                                    @endif
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :variant="$log->area === 'user' ? 'primary' : 'neutral'">{{ strtoupper($log->area) }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->created_at?->format('Y-m-d H:i:s') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="py-10">
                                <x-ui.empty icon="fa-light fa-clock-rotate-left" :title="__('No logs found for the selected filters.')" :description="__('Try broadening your filters or wait for new activity entries.')" />
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.datatable-shell>
    </div>
@endcomponent
