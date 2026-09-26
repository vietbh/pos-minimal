// @ts-ignore
import { Controller } from '@hotwired/stimulus';
// @ts-ignore
import ApexCharts from 'apexcharts';

/**
 * Presentation-only controller for the statistics filter and charts.
 * The server remains authoritative for date ranges, limits and all metrics.
 * Charts only visualize server-provided values; no business calculation happens here.
 */
export default class extends Controller {
    static targets = ['custom', 'loading', 'submit', 'financialChart', 'paymentChart', 'stockChart'];

    connect() {
        this.toggleCustomFields();
        this.renderCharts();
    }

    disconnect() {
        this.destroyCharts();
    }

    preset() {
        this.toggleCustomFields();
    }

    toggleCustomFields() {
        const preset = this.element.querySelector('select[name="preset"]');
        const show = !preset || preset.value === 'custom';

        this.customTargets.forEach((element) => {
            element.hidden = !show;
        });
    }

    loading() {
        this.loadingTargets.forEach((element) => {
            element.hidden = false;
        });

        this.submitTargets.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
    }

    renderCharts() {
        if (typeof ApexCharts === 'undefined') {
            return;
        }

        this.destroyCharts();

        if (this.hasFinancialChartTarget) {
            this.financialChart = new ApexCharts(
                this.financialChartTarget,
                this.financialOptions(this.readJson(this.financialChartTarget.dataset.chart)),
            );
            this.financialChart.render();
        }

        if (this.hasPaymentChartTarget) {
            const paymentData = this.readJson(this.paymentChartTarget.dataset.chart);

            if (paymentData.items?.length > 0) {
                this.paymentChart = new ApexCharts(
                    this.paymentChartTarget,
                    this.paymentOptions(paymentData),
                );
                this.paymentChart.render();
            }
        }

        if (this.hasStockChartTarget) {
            const stockData = this.readJson(this.stockChartTarget.dataset.chart);
            if (stockData.items?.length > 0) {
                this.stockChart = new ApexCharts(
                    this.stockChartTarget,
                    this.stockOptions(stockData),
                );
                this.stockChart.render();
            }
        }
    }

    destroyCharts() {
        if (this.financialChart) {
            this.financialChart.destroy();
            this.financialChart = null;
        }

        if (this.paymentChart) {
            this.paymentChart.destroy();
            this.paymentChart = null;
        }

        if (this.stockChart) {
            this.stockChart.destroy();
            this.stockChart = null;
        }
    }

    readJson(value) {
        try {
            return JSON.parse(value || '[]');
        } catch {
            return [];
        }
    }

    financialOptions(data) {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        return {
            chart: {
                type: 'bar',
                height: 300,
                toolbar: { show: false },
                animations: { enabled: !reducedMotion },
                fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            },
            series: [{
                name: data.seriesLabel,
                data: data.values,
            }],
            xaxis: {
                categories: data.categories,
                labels: {
                    style: { fontSize: '13px' },
                    formatter: (value) => this.formatCompactVnd(value),
                },
            },
            yaxis: {
                labels: {
                    style: { fontSize: '13px', fontWeight: 600 },
                    maxWidth: 150,
                },
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 5,
                    barHeight: '58%',
                },
            },
            dataLabels: {
                enabled: false,
            },
            tooltip: {
                y: {
                    formatter: (value) => this.formatVnd(value),
                },
            },
            grid: {
                borderColor: '#E2E8F0',
                strokeDashArray: 3,
            },
            legend: { show: false },
            colors: ['#2563EB'],
            states: {
                hover: { filter: { type: 'lighten', value: 0.04 } },
            },
        };
    }

    paymentOptions(data) {
        const items = data.items || [];
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        return {
            chart: {
                type: 'donut',
                height: 280,
                toolbar: { show: false },
                animations: { enabled: !reducedMotion },
                fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            },
            series: items.map((item) => Number(item.amount)),
            labels: items.map((item) => item.label),
            legend: {
                position: 'bottom',
                fontSize: '14px',
                labels: { useSeriesColors: false },
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                width: 2,
                colors: ['#FFFFFF'],
            },
            tooltip: {
                y: {
                    formatter: (value) => this.formatVnd(value),
                },
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '66%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: data.totalLabel || 'Total',
                                formatter: (w) => this.formatVnd(
                                    w.globals.seriesTotals.reduce((sum, value) => sum + value, 0),
                                ),
                            },
                        },
                    },
                },
            },
            responsive: [{
                breakpoint: 640,
                options: {
                    chart: { height: 250 },
                    legend: { fontSize: '13px' },
                },
            }],
        };
    }

    stockOptions(data) {
        const items = data.items || [];
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        return {
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false },
                animations: { enabled: !reducedMotion },
                fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            },
            series: [{
                name: data.seriesLabel || 'Products',
                data: items.map((item) => Number(item.value) || 0),
            }],
            xaxis: { categories: items.map((item) => item.label), labels: { style: { fontSize: '13px' } } },
            yaxis: { labels: { style: { fontSize: '13px', fontWeight: 600 } } },
            plotOptions: { bar: { borderRadius: 5, columnWidth: '52%' } },
            dataLabels: { enabled: true, formatter: (value) => String(Number(value) || 0) },
            tooltip: { y: { formatter: (value) => `${Number(value) || 0} products` } },
            grid: { borderColor: '#E2E8F0', strokeDashArray: 3 },
            legend: { show: false },
            colors: ['#2563EB'],
        };
    }

    formatVnd(value) {
        return `${new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 0,
        }).format(Number(value) || 0)} ₫`;
    }

    formatCompactVnd(value) {
        const number = Number(value) || 0;

        if (Math.abs(number) >= 1_000_000_000) {
            return `${(number / 1_000_000_000).toFixed(1)} tỷ`;
        }

        if (Math.abs(number) >= 1_000_000) {
            return `${(number / 1_000_000).toFixed(1)} tr`;
        }

        if (Math.abs(number) >= 1_000) {
            return `${Math.round(number / 1_000)}k`;
        }

        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 0,
        }).format(number);
    }
}
