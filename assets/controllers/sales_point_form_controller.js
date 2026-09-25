import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["code", "type"];

    generateCode() {
        const type = this.hasTypeTarget ? this.typeTarget.value : "POS";
        const prefix = type === "TABLE" ? "TABLE" : "POS";
        const random = Math.random().toString(36).slice(2, 8).toUpperCase();
        this.codeTarget.value = `${prefix}-${random}`.slice(0, 50);
        this.codeTarget.focus();
    }
}
