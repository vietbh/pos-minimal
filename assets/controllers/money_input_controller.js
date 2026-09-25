import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        this.inputs = Array.from(this.element.querySelectorAll("[data-money-input]"));
        this.inputs.forEach((input) => {
            this.format(input);
            input.addEventListener("input", this.handleInput);
        });
    }

    disconnect() {
        this.inputs.forEach((input) => input.removeEventListener("input", this.handleInput));
    }

    handleInput = (event) => this.format(event.currentTarget);

    format(input) {
        const value = String(input.value || "");
        const caret = Number.isInteger(input.selectionStart) ? input.selectionStart : value.length;
        const digitsBeforeCaret = value.slice(0, caret).replace(/[^0-9]/g, "").length;
        const raw = value.replace(/[^0-9]/g, "");
        if (raw === "") return;
        const normalized = raw.replace(/^0+(?=\d)/, "");
        const formatted = Number(normalized).toLocaleString("en-US");
        input.value = formatted;
        let digits = 0;
        let nextCaret = formatted.length;
        for (let i = 0; i < formatted.length; i += 1) {
            if (/\d/.test(formatted[i])) digits += 1;
            if (digits >= digitsBeforeCaret) { nextCaret = i + 1; break; }
        }
        if (input === document.activeElement && typeof input.setSelectionRange === "function") {
            input.setSelectionRange(nextCaret, nextCaret);
        }
    }

    submit() {
        this.inputs.forEach((input) => {
            input.value = String(input.value || "").replace(/,/g, "");
        });
    }
}
