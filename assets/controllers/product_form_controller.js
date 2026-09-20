import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["name", "sku", "category", "categoryName", "sellingPrice", "costPrice"];

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
        this.clearValidation();

        if (!this.validateRequired(this.nameTarget, "Tên sản phẩm là bắt buộc.")) {
            event.preventDefault();
            return;
        }

        if (!this.validateMoney(this.sellingPriceTarget, "Giá bán là bắt buộc.")) {
            event.preventDefault();
            return;
        }

        if (this.hasCostPriceTarget && this.costPriceTarget.value.trim() !== ""
            && !this.validateMoney(this.costPriceTarget, "Giá vốn không hợp lệ.")) {
            event.preventDefault();
            return;
        }

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

        if (this.generatedPreview && !this.manualSku) {
            this.skuTarget.value = "";
        }
    }

    validateRequired(input, message) {
        if (input.value.trim() === "") {
            input.setCustomValidity(message);
            input.reportValidity();
            input.setCustomValidity("");
            input.focus();
            return false;
        }
        return true;
    }

    validateMoney(input, requiredMessage) {
        const value = input.value.trim();
        if (value === "") {
            input.setCustomValidity(requiredMessage);
            input.reportValidity();
            input.setCustomValidity("");
            input.focus();
            return false;
        }

        if (!/^\d+(?:\.\d{1,2})?$/.test(value)) {
            input.setCustomValidity("Giá phải là số không âm, tối đa 2 chữ số thập phân.");
            input.reportValidity();
            input.setCustomValidity("");
            input.focus();
            return false;
        }

        const fraction = value.split(".")[1] ?? "";
        if (fraction !== "" && /[^0]/.test(fraction)) {
            input.setCustomValidity("Giá VND không được có phần thập phân khác 0.");
            input.reportValidity();
            input.setCustomValidity("");
            input.focus();
            return false;
        }

        return true;
    }

    clearValidation() {
        this.nameTarget.setCustomValidity("");
        this.sellingPriceTarget.setCustomValidity("");
        if (this.hasCostPriceTarget) this.costPriceTarget.setCustomValidity("");
        if (this.hasCategoryNameTarget) this.categoryNameTarget.setCustomValidity("");
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
