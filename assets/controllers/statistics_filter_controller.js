import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['custom', 'loading'];

    connect() {
        this.toggle();
    }

    preset(event) {
        const value = event.target.value;
        const from = this.element.querySelector('#from');
        const to = this.element.querySelector('#to');
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const format = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        let start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        let end = new Date(start);

        if (value === 'yesterday') {
            start.setDate(start.getDate() - 1);
            end.setDate(end.getDate() - 1);
        } else if (value === 'week') {
            const day = start.getDay() || 7;
            start.setDate(start.getDate() - day + 1);
        } else if (value === 'month') {
            start = new Date(start.getFullYear(), start.getMonth(), 1);
            end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
        }

        if (value !== 'custom') {
            from.value = format(start);
            to.value = format(end);
        }
        this.toggle();
    }

    toggle() {
        const preset = this.element.querySelector('select[name="preset"]');
        const show = !preset || preset.value === 'custom';
        this.customTargets.forEach((element) => { element.hidden = !show; });
    }

    loading() {
        this.loadingTargets.forEach((element) => { element.hidden = false; });
    }
}
