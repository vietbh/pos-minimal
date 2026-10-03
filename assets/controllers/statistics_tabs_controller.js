import { Controller } from '@hotwired/stimulus';
import ApexCharts from 'apexcharts';

/** Fetches tab-specific statistics from Symfony and renders readable dark-theme charts. */
export default class extends Controller {
    static targets = ['tab', 'panel', 'activeInput'];

    connect() {
        this.active = this.tabTargets.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.tab || 'overview';
        this.cache = new Map();
        this.abortController = null;
        this.chart = null;
        this.applyVisibility();
        if (this.active !== 'overview') this.load(this.active);
    }

    disconnect() {
        this.abortController?.abort();
        this.destroyChart();
    }

    select(event) {
        event.preventDefault();
        const tab = event.currentTarget?.dataset?.tab || 'overview';
        this.active = tab;
        this.tabTargets.forEach((button) => {
            const selected = button.dataset.tab === tab;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-selected', selected ? 'true' : 'false');
            button.tabIndex = selected ? 0 : -1;
        });
        if (this.hasActiveInputTarget) this.activeInputTarget.value = tab;
        this.applyVisibility();
        if (tab !== 'overview') this.load(tab);
    }

    applyVisibility() {
        if (this.hasPanelTarget) this.panelTarget.hidden = this.active === 'overview';
        this.element.querySelectorAll('[data-statistics-category]').forEach((element) => {
            const category = element.dataset.statisticsCategory;
            element.hidden = this.active === 'overview'
                ? !['overview', 'sales'].includes(category)
                : true;
        });
        this.element.querySelectorAll('.statistics-chart-grid, .statistics-detail-grid, .statistics-lists-grid, .statistics-goals').forEach((element) => {
            element.hidden = this.active !== 'overview';
        });
    }

    async load(tab) {
        // Guard every optional DOM dependency: malformed/older Twig markup must not crash tabs.
        if (!this.hasPanelTarget || !this.panelTarget) return;
        const form = this.element.querySelector('form');
        const endpoint = this.element.dataset.statisticsDataUrl;
        if (!endpoint || !form) {
            this.showMessage('Không tìm thấy cấu hình tải dữ liệu thống kê.');
            return;
        }
        const query = new URLSearchParams(new FormData(form));
        query.set('tab', tab);
        const key = `${tab}?${query.toString()}`;
        if (this.cache.has(key)) { this.render(tab, this.cache.get(key)); return; }
        this.abortController?.abort();
        const abortController = new AbortController();
        this.abortController = abortController;
        this.destroyChart();
        this.panelTarget.hidden = false;
        this.panelTarget.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'statistics-lazy-status';
        loading.textContent = 'Đang tải dữ liệu…';
        this.panelTarget.append(loading);
        const url = endpoint.replace('__TAB__', encodeURIComponent(tab));
        try {
            const response = await fetch(`${url}?${query.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: abortController.signal,
            });
            if (!response.ok) throw new Error(response.status === 403 ? 'Bạn không có quyền xem thống kê này.' : `Không thể tải dữ liệu thống kê (HTTP ${response.status}).`);
            const payload = await response.json();
            if (!payload || !payload.data || !Array.isArray(payload.data.rows)) throw new Error('Dữ liệu thống kê trả về không đúng định dạng.');
            this.cache.set(key, payload);
            if (this.active === tab && this.abortController === abortController) this.render(tab, payload);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            if (this.active === tab) this.showMessage(error?.message || 'Có lỗi khi tải dữ liệu.');
        }
    }

    render(tab, payload) {
        if (!this.hasPanelTarget || !this.panelTarget || this.active !== tab) return;
        this.destroyChart();
        this.panelTarget.replaceChildren();
        const titles = { sales: 'Doanh số', products: 'Sản phẩm', payments: 'Thanh toán', customers: 'Khách hàng', debts: 'Công nợ' };
        const chartTitles = { sales: 'Tổng quan doanh số', products: 'Sản phẩm nổi bật', payments: 'Cơ cấu thanh toán', customers: 'Khách hàng nổi bật', debts: 'Tổng quan công nợ' };
        const title = document.createElement('h2');
        title.className = 'statistics-tab-title';
        title.textContent = titles[tab] || 'Thống kê';
        this.panelTarget.append(title);

        const rows = payload.data.rows.map((row) => ({
            label: this.translatePaymentMethod(String(row.label ?? 'Khác'), tab),
            value: row.value,
        }));
        const card = document.createElement('section');
        card.className = 'statistics-card statistics-lazy-chart-card';
        const heading = document.createElement('h3');
        heading.className = 'statistics-lazy-chart-title';
        heading.textContent = chartTitles[tab] || 'Biểu đồ thống kê';
        card.append(heading);

        const points = rows.map((row) => ({ ...row, numeric: this.numericValue(row.value, tab) }))
            .filter((row) => Number.isFinite(row.numeric));
        // Attach the card before creating/rendering ApexCharts. Rendering while the
        // target is detached gives ApexCharts a zero-width container and a blank chart.
        this.panelTarget.append(card);
        if (points.length) {
            const chartElement = document.createElement('div');
            chartElement.className = 'statistics-lazy-chart';
            chartElement.setAttribute('role', 'img');
            chartElement.setAttribute('aria-label', heading.textContent);
            card.append(chartElement);
            const isPayments = tab === 'payments';
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const common = {
                chart: { type: isPayments ? 'donut' : 'bar', height: Math.max(280, Math.min(440, points.length * 44 + 100)), toolbar: { show: false }, animations: { enabled: !reducedMotion }, foreColor: '#E5EFED', background: 'transparent', fontFamily: 'Inter, system-ui, sans-serif' },
                theme: { mode: 'dark' },
                colors: ['#2CC7B5', '#60A5FA', '#FBBF24', '#F87171', '#A78BFA', '#34D399', '#FB923C', '#E879F9'],
                tooltip: { theme: 'dark', style: { fontSize: '13px' } },
                grid: { borderColor: 'rgba(203, 220, 216, 0.28)', strokeDashArray: 4 },
                noData: { text: 'Không có dữ liệu trong khoảng thời gian đã chọn.', style: { color: '#E5EFED' } },
            };
            const options = isPayments ? {
                ...common, series: points.map((point) => point.numeric), labels: points.map((point) => point.label),
                legend: { position: 'bottom', fontSize: '13px', labels: { colors: '#E5EFED' } },
                dataLabels: { enabled: true, style: { colors: ['#FFFFFF'], fontSize: '12px', fontWeight: 600 } },
                stroke: { width: 2, colors: ['#14211F'] }, plotOptions: { pie: { donut: { size: '62%' } } },
            } : {
                ...common,
                series: [{ name: titles[tab] || 'Giá trị', data: points.map((point) => point.numeric) }],
                xaxis: { categories: points.map((point) => point.label), labels: { style: { colors: '#D5E2DF', fontSize: '12px' }, formatter: (value) => this.compact(value) }, axisBorder: { color: 'rgba(203,220,216,.45)' }, axisTicks: { color: 'rgba(203,220,216,.45)' } },
                yaxis: { labels: { style: { colors: '#E5EFED', fontSize: '13px', fontWeight: 500 }, maxWidth: 210 } },
                plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '58%', distributed: ['products', 'customers'].includes(tab) } },
                dataLabels: { enabled: false }, legend: { show: false },
            };
            this.chart = new ApexCharts(chartElement, options);
            this.chart.render().catch((error) => {
                console.error('Statistics chart render failed:', error);
                chartElement.replaceChildren();
                const message = document.createElement('p');
                message.className = 'statistics-muted statistics-empty';
                message.textContent = 'Không thể hiển thị biểu đồ. Vui lòng tải lại trang.';
                chartElement.append(message);
            });
        } else {
            const empty = document.createElement('p');
            empty.className = 'statistics-muted statistics-empty';
            empty.textContent = 'Không có dữ liệu để vẽ biểu đồ trong khoảng thời gian đã chọn.';
            card.append(empty);
        }
        const results = document.createElement('div');
        results.className = 'statistics-lazy-results';
        rows.forEach((row) => {
            const item = document.createElement('div'); item.className = 'statistics-lazy-row';
            const label = document.createElement('span'); label.textContent = row.label;
            const value = document.createElement('strong'); value.textContent = row.value == null ? '—' : String(row.value);
            item.append(label, value); results.append(item);
        });
        this.panelTarget.append(results);
    }

    translatePaymentMethod(label, tab) {
        if (tab !== 'payments') return label;
        const normalized = label.trim().toUpperCase().replace(/[\s-]+/g, '_');
        const translations = {
            BANK_TRANSFER: 'Chuyển khoản ngân hàng',
            BANKTRANSFER: 'Chuyển khoản ngân hàng',
            CASH: 'Tiền mặt',
            CASH_PAYMENT: 'Tiền mặt',
        };
        return translations[normalized] || label;
    }

    numericValue(value, tab) {
        if (typeof value === 'number' && Number.isFinite(value)) return value;
        const text = String(value ?? '').trim();
        if (!text) return NaN;
        // API labels use "quantity sản phẩm · amount" for product/customer/payment rows.
        const candidates = text.match(/-?\d[\d.,]*/g);
        if (!candidates?.length) return NaN;
        const token = (tab === 'products' || tab === 'customers' || tab === 'payments') && candidates.length > 1
            ? candidates[candidates.length - 1] : candidates[0];
        const normalized = token.replace(/\.(?=\d{3}(?:\D|$))/g, '').replace(',', '.');
        const number = Number(normalized);
        return Number.isFinite(number) ? number : NaN;
    }

    compact(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) return value;
        return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(number);
    }

    showMessage(text) {
        if (!this.hasPanelTarget || !this.panelTarget) return;
        this.destroyChart();
        this.panelTarget.hidden = false;
        this.panelTarget.replaceChildren();
        const message = document.createElement('p');
        message.className = 'statistics-lazy-error'; message.setAttribute('role', 'alert'); message.textContent = text;
        this.panelTarget.append(message);
    }

    destroyChart() {
        if (this.chart) { this.chart.destroy(); this.chart = null; }
    }
}
