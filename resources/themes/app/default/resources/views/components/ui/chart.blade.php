@props([
    'id' => 'chart-'.\Illuminate\Support\Str::uuid(),
    'title' => null,
    'description' => null,
    'type' => 'line',
    'categories' => [],
    'series' => [],
    'height' => 260,
    'options' => [],
    'legend' => false,
    'donut' => false,
    'class' => '',
    'footerStats' => [],
])

@php
    $resolvedChartType = $type === 'line' ? 'areaspline' : $type;
    $resolvedOptions = $options;

    if ($resolvedOptions === []) {
        $resolvedOptions = [
            'chart' => [
                'type' => $resolvedChartType,
                'spacing' => [10, 8, 10, 8],
                'style' => [
                    'fontFamily' => 'inherit',
                ],
            ],
            'title' => ['text' => null],
            'legend' => ['enabled' => $legend],
            'credits' => ['enabled' => false],
            'xAxis' => [
                'categories' => $categories,
                'lineColor' => '#e2e8f0',
                'lineWidth' => 1,
                'tickWidth' => 0,
                'labels' => [
                    'style' => [
                        'fontSize' => '12px',
                        'color' => '#64748b',
                    ],
                ],
            ],
            'yAxis' => [
                'gridLineWidth' => 1,
                'gridLineDashStyle' => 'Dash',
                'gridLineColor' => '#dbe7ee',
                'title' => ['text' => null],
                'labels' => [
                    'style' => [
                        'fontSize' => '12px',
                        'color' => '#64748b',
                    ],
                ],
            ],
            'tooltip' => [
                'shared' => true,
                'backgroundColor' => '#ffffff',
                'borderColor' => '#dbe7ee',
                'borderRadius' => 10,
                'shadow' => false,
                'style' => [
                    'color' => '#0f172a',
                    'fontSize' => '12px',
                ],
            ],
            'colors' => ['#147d78'],
            'series' => $series,
        ];

        if (in_array($type, ['pie', 'donut'], true)) {
            $resolvedOptions['chart']['type'] = 'pie';
            $resolvedOptions['plotOptions'] = [
                'pie' => [
                    'borderRadius' => 0,
                    'innerSize' => $donut || $type === 'donut' ? '68%' : '0%',
                    'borderWidth' => 0,
                    'size' => '88%',
                    'dataLabels' => [
                        'enabled' => false,
                    ],
                    'showInLegend' => $legend,
                    'states' => [
                        'inactive' => [
                            'opacity' => 1,
                        ],
                    ],
                ],
                'series' => [
                    'states' => [
                        'inactive' => [
                            'opacity' => 1,
                        ],
                    ],
                ],
            ];
            $resolvedOptions['tooltip'] = [
                'pointFormat' => '<b>{point.y}</b>',
            ];
        } else {
            $resolvedOptions['plotOptions'] = [
                'column' => [
                    'borderRadius' => 0,
                    'borderWidth' => 0,
                    'groupPadding' => 0.16,
                    'pointPadding' => 0.08,
                ],
                'bar' => [
                    'borderRadius' => 0,
                    'borderWidth' => 0,
                    'groupPadding' => 0.16,
                    'pointPadding' => 0.08,
                ],
                'area' => [
                    'fillOpacity' => 0.14,
                    'lineWidth' => 3,
                    'marker' => [
                        'enabled' => false,
                    ],
                ],
                'areaspline' => [
                    'fillOpacity' => 0.16,
                    'lineWidth' => 3.5,
                    'marker' => [
                        'enabled' => false,
                    ],
                ],
                'spline' => [
                    'lineWidth' => 3.5,
                    'marker' => [
                        'enabled' => false,
                    ],
                ],
                'line' => [
                    'lineWidth' => 3.5,
                    'marker' => [
                        'enabled' => false,
                    ],
                ],
                'series' => [
                    'borderRadius' => 0,
                    'lineWidth' => in_array($type, ['line', 'area', 'spline', 'areaspline'], true) ? 3.5 : null,
                    'marker' => [
                        'enabled' => false,
                    ],
                    'states' => [
                        'hover' => [
                            'lineWidthPlus' => 0,
                            'halo' => [
                                'size' => 0,
                            ],
                        ],
                        'inactive' => [
                            'opacity' => 1,
                        ],
                    ],
                ],
            ];

            if (in_array($resolvedChartType, ['area', 'areaspline'], true)) {
                $resolvedOptions['plotOptions'][$resolvedChartType]['fillColor'] = [
                    'linearGradient' => [0, 0, 0, 1],
                    'stops' => [
                        [0, 'rgba(20, 125, 120, 0.20)'],
                        [1, 'rgba(20, 125, 120, 0.02)'],
                    ],
                ];
            }
        }
    }
@endphp

<div {{ $attributes->class(['min-w-0 max-w-full overflow-hidden rounded-[1rem] border p-5 shadow-[0_12px_30px_-24px_rgba(15,23,42,0.18)]', $class]) }} style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
    @if (filled($title) || filled($description))
        <div class="mb-4">
            @if (filled($title))
                <p class="text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $title }}</p>
            @endif
            @if (filled($description))
                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $description }}</p>
            @endif
        </div>
    @endif

    <x-shared::highchart
        :id="$id"
        :options="$resolvedOptions"
        :height="$height"
    />

    @if (! empty($footerStats))
        <div class="mt-5 grid overflow-hidden rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.56); grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));">
            @foreach ($footerStats as $stat)
                <div class="border-t px-5 py-4 first:border-t-0 sm:border-l sm:first:border-l-0 sm:border-t-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.56); background: color-mix(in srgb, var(--theme-surface-soft) 88%, transparent);">
                    <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ $stat['label'] ?? '--' }}</p>
                    <p class="mt-2 text-[1.75rem] font-semibold leading-none" style="color: var(--theme-header-text-color);">
                        {{ number_format((float) ($stat['value'] ?? 0), (int) ($stat['decimals'] ?? 0)) }}{{ $stat['suffix'] ?? '' }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>
