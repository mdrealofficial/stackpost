@props([
    'id' => 'highchart-'.\Illuminate\Support\Str::uuid(),
    'options' => [],
    'height' => '320px',
    'class' => '',
])

@php
    $resolvedHeight = is_numeric($height) ? $height.'px' : $height;
    $fallbackScriptUrl = theme_shared_asset('plugins/highcharts/highcharts.js');
@endphp

<div class="{{ $class }}">
    <div
        id="{{ $id }}"
        wire:ignore
        x-data="{
            options: @js($options),
            error: '',
            resizeObserver: null,
            resizeFrame: null,
            layoutInterval: null,
            layoutStableCount: 0,
            layoutTickCount: 0,
            lastLayoutWidth: 0,
            registerBridge() {
                window.StackpostsHighcharts = {
                    lib: window.Highcharts,
                    render: (elOrId, options = {}) => {
                        const element = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;

                        if (!element) {
                            return null;
                        }

                        return window.Highcharts.chart(element, this.withTheme(options));
                    },
                };

                return window.StackpostsHighcharts;
            },
            mixColor(hex, ratio, target = '#ffffff') {
                const normalize = (value) => {
                    const sanitized = (value || '').replace('#', '').trim();
                    if (sanitized.length === 3) {
                        return sanitized.split('').map((char) => char + char).join('');
                    }

                    return sanitized.padStart(6, '0').slice(0, 6);
                };

                const source = normalize(hex);
                const to = normalize(target);
                const clamp = (value) => Math.max(0, Math.min(1, value));
                const amount = clamp(ratio);
                const fromRgb = [0, 2, 4].map((index) => parseInt(source.slice(index, index + 2), 16));
                const toRgb = [0, 2, 4].map((index) => parseInt(to.slice(index, index + 2), 16));
                const mixed = fromRgb.map((channel, index) => Math.round(channel + ((toRgb[index] - channel) * amount)));

                return `#${mixed.map((channel) => channel.toString(16).padStart(2, '0')).join('')}`;
            },
            normalizeSeries(options = {}, theme = null) {
                const resolvedTheme = theme || this.themePalette();
                const normalized = window.Highcharts.merge({}, options);
                const chartType = normalized.chart?.type || 'line';
                const series = Array.isArray(normalized.series) ? normalized.series : [];
                const linePalette = [
                    resolvedTheme.accent,
                    this.mixColor(resolvedTheme.accent, 0.14, '#ffffff'),
                    this.mixColor(resolvedTheme.accent, 0.28, '#ffffff'),
                    this.mixColor(resolvedTheme.accent, 0.42, '#ffffff'),
                    this.mixColor(resolvedTheme.accent, 0.18, '#0f172a'),
                    this.mixColor(resolvedTheme.accent, 0.56, '#ffffff'),
                ];
                const piePalette = [
                    resolvedTheme.accent,
                    this.mixColor(resolvedTheme.accent, 0.12, '#0f172a'),
                    this.mixColor(resolvedTheme.accent, 0.18, '#ffffff'),
                    this.mixColor(resolvedTheme.accent, 0.32, '#ffffff'),
                    this.mixColor(resolvedTheme.accent, 0.46, '#ffffff'),
                    this.mixColor(resolvedTheme.accent, 0.58, '#ffffff'),
                ];

                normalized.series = series.map((item, index) => {
                    const next = window.Highcharts.merge({}, item);
                    const seriesType = next.type || chartType;
                    const fallbackColor = (seriesType === 'pie' || chartType === 'pie')
                        ? piePalette[index % piePalette.length]
                        : linePalette[index % linePalette.length];

                    if (! next.color) {
                        next.color = fallbackColor;
                    }

                    if (Array.isArray(next.data) && (seriesType === 'pie' || chartType === 'pie')) {
                        next.data = next.data.map((point, pointIndex) => {
                            if (typeof point !== 'object' || point === null) {
                                return point;
                            }

                            return {
                                ...point,
                                color: point.color || piePalette[pointIndex % piePalette.length],
                            };
                        });
                    }

                    if (! next.marker && ['line', 'area', 'spline'].includes(seriesType)) {
                        next.marker = {
                            lineColor: '#ffffff',
                            lineWidth: 2,
                            radius: 2.5,
                            symbol: 'circle',
                        };
                    }

                    return next;
                });

                return normalized;
            },
            themePalette() {
                const css = getComputedStyle(document.documentElement);
                const pick = (prop, fallback) => css.getPropertyValue(prop).trim() || fallback;
                const rgb = (prop, fallback) => pick(prop, fallback).replace(/\s+/g, '');

                const accent = pick('--theme-accent', '#5b4cf0');
                const accentRgb = rgb('--theme-accent-rgb', '91,76,240');
                const success = pick('--theme-success-color', '#16a34a');
                const warning = pick('--theme-warning-color', '#f59e0b');
                const danger = pick('--theme-danger-color', '#ef4444');
                const info = pick('--theme-info-color', '#0ea5e9');
                const header = pick('--theme-header-text-color', '#0f172a');
                const muted = pick('--theme-muted-text-color', '#64748b');
                const borderRgb = rgb('--theme-border-color-rgb', '148,163,184');

                return {
                    accent,
                    accentRgb,
                    success,
                    warning,
                    danger,
                    info,
                    header,
                    muted,
                    border: `rgba(${borderRgb},0.18)`,
                    grid: `rgba(${borderRgb},0.14)`,
                    tooltip: 'rgba(15,23,42,0.94)',
                    tooltipBorder: `rgba(${accentRgb},0.22)`,
                    seriesColors: [
                        accent,
                        this.mixColor(accent, 0.12, '#0f172a'),
                        this.mixColor(accent, 0.16, '#ffffff'),
                        this.mixColor(accent, 0.28, '#ffffff'),
                        this.mixColor(accent, 0.42, '#ffffff'),
                        this.mixColor(accent, 0.56, '#ffffff'),
                        this.mixColor(accent, 0.24, '#0f172a'),
                        this.mixColor(accent, 0.68, '#ffffff'),
                    ],
                };
            },
            withTheme(options = {}) {
                const theme = this.themePalette();

                return this.normalizeSeries(window.Highcharts.merge({
                    colors: theme.seriesColors,
                    chart: {
                        animation: {
                            duration: 380,
                        },
                        backgroundColor: 'transparent',
                        spacingTop: 8,
                        spacingRight: 4,
                        spacingBottom: 0,
                        spacingLeft: 0,
                        style: {
                            fontFamily: getComputedStyle(document.documentElement).getPropertyValue('--theme-font-sans').trim() || 'Inter, sans-serif',
                        },
                    },
                    title: { text: null },
                    credits: { enabled: false },
                    xAxis: {
                        lineColor: 'transparent',
                        tickColor: 'transparent',
                        gridLineColor: 'transparent',
                        labels: {
                            style: {
                                color: theme.muted,
                                fontSize: '11px',
                            },
                        },
                    },
                    yAxis: {
                        title: { text: null },
                        gridLineColor: theme.grid,
                        gridLineDashStyle: 'ShortDash',
                        labels: {
                            style: {
                                color: theme.muted,
                                fontSize: '11px',
                            },
                        },
                    },
                    legend: {
                        itemDistance: 18,
                        itemMarginTop: 2,
                        itemMarginBottom: 2,
                        symbolRadius: 999,
                        symbolHeight: 10,
                        symbolWidth: 10,
                        itemStyle: {
                            color: theme.header,
                            fontSize: '12px',
                            fontWeight: '500',
                        },
                        itemHoverStyle: {
                            color: theme.header,
                        },
                    },
                    tooltip: {
                        shared: true,
                        useHTML: true,
                        borderWidth: 1,
                        borderRadius: 14,
                        backgroundColor: theme.tooltip,
                        borderColor: theme.tooltipBorder,
                        shadow: false,
                        style: {
                            color: '#ffffff',
                            fontSize: '12px',
                        },
                    },
                    plotOptions: {
                        series: {
                            animation: {
                                duration: 380,
                            },
                            marker: {
                                enabled: false,
                                states: {
                                    hover: {
                                        enabled: false,
                                    },
                                },
                            },
                            states: {
                                hover: {
                                    halo: {
                                        size: 0,
                                        opacity: 0,
                                    },
                                },
                            },
                        },
                        line: {
                            linecap: 'round',
                            lineWidth: 3,
                            marker: {
                                enabled: false,
                                fillColor: '#ffffff',
                                lineWidth: 2,
                                radius: 2.5,
                            },
                        },
                        spline: {
                            linecap: 'round',
                            lineWidth: 3,
                            marker: {
                                enabled: false,
                                fillColor: '#ffffff',
                                lineWidth: 2,
                                radius: 2.5,
                            },
                        },
                        area: {
                            linecap: 'round',
                            lineWidth: 3,
                            fillOpacity: 0.16,
                            marker: {
                                enabled: false,
                            },
                        },
                        areaspline: {
                            linecap: 'round',
                            lineWidth: 3,
                            fillOpacity: 0.16,
                            marker: {
                                enabled: false,
                            },
                        },
                        column: {
                            borderRadius: 0,
                            borderWidth: 0,
                        },
                        bar: {
                            borderRadius: 0,
                            borderWidth: 0,
                        },
                        pie: {
                            borderRadius: 0,
                            borderWidth: 0,
                            slicedOffset: 0,
                            center: ['50%', '48%'],
                            dataLabels: {
                                enabled: false,
                                style: {
                                    color: theme.header,
                                    textOutline: 'none',
                                },
                            },
                            showInLegend: true,
                        },
                    },
                    responsive: {
                        rules: [
                            {
                                condition: {
                                    maxWidth: 640,
                                },
                                chartOptions: {
                                    legend: {
                                        align: 'left',
                                        verticalAlign: 'bottom',
                                        layout: 'horizontal',
                                        itemDistance: 12,
                                        itemStyle: {
                                            fontSize: '11px',
                                        },
                                    },
                                    plotOptions: {
                                        pie: {
                                            size: '78%',
                                            center: ['50%', '42%'],
                                            dataLabels: {
                                                enabled: false,
                                            },
                                        },
                                    },
                                },
                            },
                        ],
                    },
                }, options), theme);
            },
            ensureLibrary() {
                if (window.StackpostsHighcharts) {
                    return Promise.resolve(this.registerBridge());
                }

                if (window.Highcharts) {
                    return Promise.resolve(this.registerBridge());
                }

                if (window.__stackpostsHighchartsLoader) {
                    return window.__stackpostsHighchartsLoader;
                }

                window.__stackpostsHighchartsLoader = new Promise((resolve, reject) => {
                    const bootstrap = () => {
                        if (!window.Highcharts) {
                            reject(new Error('Highcharts library failed to load.'));
                            return;
                        }

                        this.registerBridge();

                        window.dispatchEvent(new CustomEvent('stackposts:highcharts-ready'));
                        resolve(window.StackpostsHighcharts);
                    };

                    const existingScript = document.querySelector('script[data-stackposts-highcharts-cdn]');

                    if (existingScript) {
                        existingScript.addEventListener('load', bootstrap, { once: true });
                        existingScript.addEventListener('error', () => reject(new Error('Failed to load local Highcharts script.')), { once: true });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = @js($fallbackScriptUrl);
                    script.async = true;
                    script.dataset.stackpostsHighchartsCdn = 'local';
                    script.addEventListener('load', bootstrap, { once: true });
                    script.addEventListener('error', () => reject(new Error('Failed to load local Highcharts script.')), { once: true });
                    document.head.appendChild(script);
                });

                return window.__stackpostsHighchartsLoader;
            },
            render() {
                if (!this.$refs?.chart || !this.$root?.isConnected) {
                    return;
                }

                this.ensureLibrary()
                    .then(() => {
                        if (!this.$refs?.chart || !this.$root?.isConnected) {
                            return;
                        }

                        if (this.$refs.chart.__stackpostsChart?.destroy) {
                            this.$refs.chart.__stackpostsChart.destroy();
                        }

                        this.$refs.chart.__stackpostsChart = window.StackpostsHighcharts.render(this.$refs.chart, this.options);
                        this.error = '';
                    })
                    .catch((error) => {
                        this.error = error?.message || 'Chart render failed.';
                        console.error('Stackposts chart render failed', error);
                    });
            },
            reflow() {
                if (!this.$refs?.chart || !this.$root?.isConnected) {
                    return;
                }

                if (this.resizeFrame) {
                    cancelAnimationFrame(this.resizeFrame);
                }

                this.resizeFrame = requestAnimationFrame(() => {
                    if (!this.$refs?.chart || !this.$root?.isConnected) {
                        return;
                    }

                    const chart = this.$refs.chart.__stackpostsChart;

                    if (!chart) {
                        return;
                    }

                    chart.reflow?.();

                    const width = Math.max(0, Math.round(this.$root.getBoundingClientRect().width));
                    const height = Math.max(0, Math.round(this.$root.getBoundingClientRect().height));

                    if (width > 0 && height > 0 && (Math.abs((chart.chartWidth || 0) - width) > 1 || Math.abs((chart.chartHeight || 0) - height) > 1)) {
                        chart.setSize(width, height, false);
                        chart.redraw(false);
                    }
                });
            },
            scheduleRender() {
                requestAnimationFrame(() => this.render());
            },
            startLayoutReflow() {
                if (this.layoutInterval) {
                    clearInterval(this.layoutInterval);
                    this.layoutInterval = null;
                }

                this.layoutStableCount = 0;
                this.layoutTickCount = 0;
                this.lastLayoutWidth = Math.round(this.$root.getBoundingClientRect().width || 0);
                this.reflow();

                this.layoutInterval = setInterval(() => {
                    this.layoutTickCount += 1;

                    const currentWidth = Math.round(this.$root.getBoundingClientRect().width || 0);
                    const widthChanged = Math.abs(currentWidth - this.lastLayoutWidth) > 1;

                    if (widthChanged) {
                        this.lastLayoutWidth = currentWidth;
                        this.layoutStableCount = 0;
                        this.reflow();
                    } else {
                        this.layoutStableCount += 1;
                    }

                    if (this.layoutStableCount >= 4 || this.layoutTickCount >= 30) {
                        clearInterval(this.layoutInterval);
                        this.layoutInterval = null;
                        this.reflow();
                    }
                }, 80);
            },
            bindResize() {
                window.addEventListener('resize', () => this.reflow());
                window.addEventListener('app-shell:layout-change', () => {
                    this.startLayoutReflow();
                });

                if (window.ResizeObserver) {
                    this.resizeObserver = new ResizeObserver(() => this.reflow());
                    this.resizeObserver.observe(this.$root);
                    if (this.$root.parentElement) {
                        this.resizeObserver.observe(this.$root.parentElement);
                    }
                }
            },
            bindLivewire() {
                if (!window.Livewire?.hook) {
                    return;
                }

                window.Livewire.hook('morph.updated', ({ el }) => {
                    if (el === this.$root || el?.contains?.(this.$root) || this.$root?.contains?.(el)) {
                        this.scheduleRender();
                    }
                });

                window.Livewire.hook('morph.added', ({ el }) => {
                    if (el === this.$root || el?.contains?.(this.$root) || this.$root?.contains?.(el)) {
                        this.scheduleRender();
                    }
                });
            },
        }"
        x-init="
            scheduleRender();
            window.addEventListener('stackposts:highcharts-ready', () => scheduleRender());
            window.addEventListener('theme-mode-changed', () => scheduleRender());
            document.addEventListener('livewire:navigated', () => scheduleRender());
            bindResize();
            bindLivewire();
        "
        style="width: 100%; height: {{ $resolvedHeight }};"
    >
        <div x-ref="chart" style="width: 100%; height: 100%;"></div>
        <div x-show="error" x-cloak class="flex h-full items-center justify-center px-4 text-center text-sm" style="color: var(--theme-danger-color);" x-text="error"></div>
    </div>
</div>
