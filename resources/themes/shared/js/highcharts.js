import Highcharts from 'highcharts';

const readCssVar = (name, fallback) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value || fallback;
};

const defaultTheme = () => ({
    chart: {
        backgroundColor: 'transparent',
        style: {
            fontFamily: 'Inter, Roboto, sans-serif',
        },
    },
    exporting: {
        enabled: false,
    },
    title: {
        style: {
            color: readCssVar('--theme-header-text-color', '#0f172a'),
            fontWeight: '600',
        },
    },
    subtitle: {
        style: {
            color: readCssVar('--theme-muted-text-color', '#64748b'),
        },
    },
    xAxis: {
        lineColor: '#e5e7eb',
        tickColor: '#e5e7eb',
        labels: {
            style: {
                color: readCssVar('--theme-muted-text-color', '#64748b'),
            },
        },
    },
    yAxis: {
        gridLineColor: '#e5e7eb',
        gridLineDashStyle: 'Dash',
        title: {
            style: {
                color: readCssVar('--theme-muted-text-color', '#64748b'),
            },
        },
        labels: {
            style: {
                color: readCssVar('--theme-muted-text-color', '#64748b'),
            },
        },
    },
    legend: {
        itemStyle: {
            color: readCssVar('--theme-header-text-color', '#0f172a'),
        },
        itemHoverStyle: {
            color: readCssVar('--theme-accent', '#4f46e5'),
        },
        itemHiddenStyle: {
            color: readCssVar('--theme-muted-text-color', '#94a3b8'),
        },
    },
    tooltip: {
        borderWidth: 1,
        borderColor: '#ddd',
        backgroundColor: '#ffffff',
        borderRadius: 8,
        shadow: true,
        style: {
            color: '#333',
            fontSize: '12px',
            fontFamily: 'Inter, sans-serif',
            boxShadow: false,
            padding: '0',
        },
    },
    credits: {
        enabled: false,
    },
    plotOptions: {
        pie: { borderRadius: 0 },
        column: { borderRadius: 0 },
        bar: { borderRadius: 0 },
    },
    colors: [
        '#675dff',
        '#5a4cd6',
        '#837eff',
        '#9f91ff',
        '#bcb0ff',
    ],
});

const deepMerge = (base, extra) => Highcharts.merge(base, extra || {});

const baseChartOptions = ({ type = 'line', categories = [], series = [], legend = false, donut = false } = {}) => {
    const config = {
        chart: {
            type: type === 'donut' ? 'pie' : type,
            spacingTop: 25,
            spacingRight: 15,
            marginRight: 15,
        },
        title: {
            text: null,
        },
        legend: {
            enabled: legend,
        },
        xAxis: {
            categories,
        },
        series,
    };

    if (type === 'pie' || type === 'donut') {
        config.plotOptions = {
            pie: {
                borderRadius: 0,
                innerSize: donut || type === 'donut' ? '68%' : '0%',
                borderWidth: 0,
                dataLabels: {
                    enabled: false,
                },
                showInLegend: legend,
            },
            series: {
                states: {
                    inactive: {
                        opacity: 1,
                    },
                },
            },
        };
    } else {
        config.plotOptions = {
            series: {
                borderRadius: 0,
                lineWidth: ['line', 'area', 'spline'].includes(type) ? 3 : null,
                marker: {
                    enabled: ['line', 'area', 'spline'].includes(type),
                    radius: 2.5,
                },
            },
            column: {
                borderRadius: 0,
                borderWidth: 0,
                groupPadding: 0.16,
                pointPadding: 0.08,
            },
            bar: {
                borderRadius: 0,
                borderWidth: 0,
                groupPadding: 0.16,
                pointPadding: 0.08,
            },
        };
    }

    return config;
};

window.StackpostsHighcharts = {
    lib: Highcharts,
    defaults() {
        return defaultTheme();
    },
    presets: {
        line(config = {}) {
            return baseChartOptions({ ...config, type: 'line' });
        },
        area(config = {}) {
            return baseChartOptions({ ...config, type: 'area' });
        },
        spline(config = {}) {
            return baseChartOptions({ ...config, type: 'spline' });
        },
        column(config = {}) {
            return baseChartOptions({ ...config, type: 'column' });
        },
        bar(config = {}) {
            return baseChartOptions({ ...config, type: 'bar' });
        },
        pie(config = {}) {
            return baseChartOptions({ ...config, type: 'pie' });
        },
        donut(config = {}) {
            return baseChartOptions({ ...config, type: 'donut', donut: true });
        },
    },
    make(config = {}) {
        return baseChartOptions(config);
    },
    render(elOrId, options = {}) {
        const element = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;

        if (! element) {
            return null;
        }

        const config = deepMerge(defaultTheme(), options);

        return Highcharts.chart(element, config);
    },
};

window.dispatchEvent(new CustomEvent('stackposts:highcharts-ready'));
