import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["name", "sku", "category", "categoryName"];

    connect() {
        this.manualSku = this.skuTarget.value.trim() !== "";
        this.generatedPreview = false;
    }

    nameChanged() {
        if (!this.manualSku) {
            this.skuTarget.value = this.slugify(this.nameTarget.value);
            this.generatedPreview = true;
        }
    }

    skuChanged() {
        this.manualSku = true;
        this.generatedPreview = false;
    }

    generateSku() {
        const base = this.slugify(this.nameTarget.value) || "PRODUCT";
        const random = Math.random().toString(36).slice(2, 8).toUpperCase();
        this.skuTarget.value = `${base.slice(0, 92)}-${random}`.slice(0, 100);
        this.manualSku = true;
        this.generatedPreview = false;
    }

    categoryChanged() {
        if (this.categoryTarget.value !== "" && this.hasCategoryNameTarget) {
            this.categoryNameTarget.value = "";
        }
    }

    submit(event) {
        if (this.hasCategoryTarget && this.hasCategoryNameTarget
            && this.categoryTarget.value !== ""
            && this.categoryNameTarget.value.trim() !== "") {
            event.preventDefault();
            this.categoryNameTarget.focus();
            this.categoryNameTarget.setCustomValidity("Chỉ chọn một danh mục hoặc tạo danh mục mới.");
            this.categoryNameTarget.reportValidity();
            this.categoryNameTarget.setCustomValidity("");
            return;
        }

        // The visible SKU is only a friendly preview. If the cashier has not
        // edited it, let the backend generate the final collision-safe SKU.
        if (this.generatedPreview && !this.manualSku) {
            this.skuTarget.value = "";
        }
    }

    slugify(value) {
        return value
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/gi, "d")
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }
}
