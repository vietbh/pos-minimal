import { Controller } from '@hotwired/stimulus';

/** Refreshes the current authorized statistics snapshot after a private Mercure invalidation. */
export default class extends Controller {
    static values = { hubUrl: String, topic: String, enabled: { type: Boolean, default: false } };

    connect() {
        // Polling-based workflows remain unchanged unless Mercure is explicitly enabled.
        if (!this.enabledValue) return;
        if (!this.hasHubUrlValue || !this.hubUrlValue || !this.hasTopicValue || !this.topicValue) return;
        this.refreshTimer = null;
        const url = new URL(this.hubUrlValue, window.location.origin);
        url.searchParams.append('topic', this.topicValue);
        this.source = new EventSource(url, { withCredentials: true });
        this.source.onmessage = (event) => {
            try {
                const message = JSON.parse(event.data);
                if (message.type !== 'statistics.updated') return;
                window.clearTimeout(this.refreshTimer);
                this.refreshTimer = window.setTimeout(() => window.location.reload(), 450);
            } catch (error) {
                // Ignore malformed/non-application messages; the HTTP page remains authoritative.
            }
        };
        this.source.onerror = () => {
            // EventSource reconnects automatically. A manual refresh remains available if the hub is down.
        };
    }

    disconnect() {
        window.clearTimeout(this.refreshTimer);
        this.source?.close();
    }
}
