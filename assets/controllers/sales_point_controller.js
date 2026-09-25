import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['select'];
    static values = { csrfToken: String, endpoint: String };
    connect() { this.selectTarget.addEventListener('change', this.change); }
    disconnect() { this.selectTarget.removeEventListener('change', this.change); }
    change = async () => {
        this.selectTarget.disabled = true;
        try {
            const body = new URLSearchParams({salesPointId: this.selectTarget.value});
            const response = await fetch(this.endpointValue, {method:'POST', headers:{'X-CSRF-TOKEN':this.csrfTokenValue,'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Unable to change sales point.');
            window.location.reload();
        } catch (error) {
            window.alert(error?.message || 'Unable to change sales point.');
            this.selectTarget.disabled = false;
        }
    };
}
