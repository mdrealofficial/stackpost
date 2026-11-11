<section class="w-full">
    <x-settings.layout :heading="__('System information')" :subheading="__('Review the current runtime, environment, and infrastructure drivers used by the admin platform.')">
        @php
            $summaryItems = [
                ['label' => __('Environment'), 'value' => collect($overviewItems)->firstWhere('label', 'Environment')['value'] ?? 'unknown'],
                ['label' => __('PHP'), 'value' => collect($overviewItems)->firstWhere('label', 'PHP version')['value'] ?? 'unknown'],
                ['label' => __('Laravel'), 'value' => collect($overviewItems)->firstWhere('label', 'Laravel version')['value'] ?? 'unknown'],
                ['label' => __('Database'), 'value' => collect($infrastructureItems)->firstWhere('label', 'Database driver')['value'] ?? 'unknown'],
            ];
        @endphp

        <div class="space-y-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($summaryItems as $item)
                    <x-ui.card class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ $item['label'] }}</p>
                        <div class="flex items-end justify-between gap-3">
                            <p class="text-lg font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ $item['value'] }}</p>
                            <x-ui.badge>{{ __('Live') }}</x-ui.badge>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <x-ui.datatable-shell
                :title="__('Requirements')"
                :info="__('Runtime checks inherited from the legacy installer to confirm the server is ready for the admin platform.')"
            >
                <x-slot:toolbar>
                    <div class="flex items-center gap-2">
                        <x-ui.badge>{{ $requirementSummary['passed'] . '/' . $requirementSummary['total'] . ' ' . __('Passed') }}</x-ui.badge>

                        @if ($requirementSummary['failed'] > 0)
                            <x-ui.badge>{{ $requirementSummary['failed'] . ' ' . __('Failed') }}</x-ui.badge>
                        @endif
                    </div>
                </x-slot:toolbar>

                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('Requirement') }}</x-ui.table-cell>
                        <x-ui.table-cell head>{{ __('Details') }}</x-ui.table-cell>
                        <x-ui.table-cell head class="text-right">{{ __('Status') }}</x-ui.table-cell>
                    </x-ui.table-head>

                    <tbody>
                        @foreach ($requirementItems as $item)
                            <x-ui.table-row>
                                <x-ui.table-cell>
                                    <span class="font-medium text-slate-950 dark:text-white">{{ __($item['label']) }}</span>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __($item['detail']) }}</span>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-right">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em]',
                                        'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300' => $item['passed'],
                                        'border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-300' => ! $item['passed'],
                                    ])>
                                        {{ __($item['status']) }}
                                    </span>
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.datatable-shell>

            <x-ui.datatable-shell
                :title="__('Application overview')"
                :info="__('Core runtime details for the current Laravel installation.')"
            >
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('Property') }}</x-ui.table-cell>
                        <x-ui.table-cell head class="text-right">{{ __('Value') }}</x-ui.table-cell>
                    </x-ui.table-head>

                    <tbody>
                        @foreach ($overviewItems as $item)
                            <x-ui.table-row>
                                <x-ui.table-cell>
                                    <span class="font-medium text-slate-600 dark:text-slate-300">{{ __($item['label']) }}</span>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-right">
                                    <span class="font-semibold text-slate-950 dark:text-white">{{ $item['value'] }}</span>
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.datatable-shell>

            <x-ui.datatable-shell
                :title="__('Infrastructure drivers')"
                :info="__('Current service drivers and connections resolved from application configuration.')"
            >
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-ui.table-head>
                        <x-ui.table-cell head>{{ __('Service') }}</x-ui.table-cell>
                        <x-ui.table-cell head class="text-right">{{ __('Driver') }}</x-ui.table-cell>
                    </x-ui.table-head>

                    <tbody>
                        @foreach ($infrastructureItems as $item)
                            <x-ui.table-row>
                                <x-ui.table-cell>
                                    <span class="font-medium text-slate-600 dark:text-slate-300">{{ __($item['label']) }}</span>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-right">
                                    <span class="font-semibold text-slate-950 dark:text-white">{{ $item['value'] }}</span>
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.datatable-shell>
        </div>
    </x-settings.layout>
</section>
