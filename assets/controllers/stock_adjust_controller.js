import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["submit"];

    submit() {
        if (!this.hasSubmitTarget) return;
        this.submitTarget.disabled = true;
        this.submitTarget.setAttribute("aria-disabled", "true");
        this.element.setAttribute("aria-busy", "true");
    }
}
