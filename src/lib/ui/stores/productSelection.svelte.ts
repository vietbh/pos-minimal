import type { Product } from '$lib/product/domain/Product';

class ProductSelectionStore {
    selected = $state<Product | null>(null);

    select(product: Product) {
        this.selected = product;
    }

    clear() {
        this.selected = null;
    }
}

export const productSelection = new ProductSelectionStore();