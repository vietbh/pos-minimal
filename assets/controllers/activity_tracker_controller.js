import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        csrf: String,
    };

    connect() {
        this.ping = this.ping.bind(this);
        this.onVisibilityChange = this.onVisibilityChange.bind(this);

        this.timer = window.setInterval(() => {
            if (!document.hidden) {
                this.ping();
            }
        }, 60000);

        document.addEventListener('visibilitychange', this.onVisibilityChange);
        this.ping();
    }

    disconnect() {
        if (this.timer) {
            window.clearInterval(this.timer);
        }
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
    }

    onVisibilityChange() {
        if (!document.hidden) {
            this.ping();
        }
    }

    async ping() {
        if (!this.hasUrlValue || !this.hasCsrfValue || document.hidden) {
            return;
        }

        const body = new URLSearchParams({ _token: this.csrfValue });
        try {
            await fetch(this.urlValue, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
                keepalive: true,
            });
        } catch {
            // Presence tracking is non-blocking UX telemetry. Authentication
            // remains server-authoritative and must never depend on heartbeat.
        }
    }
}
