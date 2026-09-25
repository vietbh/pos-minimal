<script lang="ts">
	import SceneRoot from '$lib/3d/scene/SceneRoot.svelte';
	import ProductDetail from '$lib/product/components/ProductDetail.svelte';
	import type { Product } from '$lib/product/domain/Product';

	let selectedProduct = $state<Product | null>(null);
</script>

<div class="relative h-screen w-full overflow-hidden">
	<SceneRoot
	{selectedProduct}
	onProductSelect={(product) => {
		console.log('PAGE PRODUCT SELECT:', product);

		selectedProduct = product;
	}}
/>

	{#if selectedProduct}
		<ProductDetail
			product={selectedProduct}
			onClose={() => {
				selectedProduct = null;
			}}
		/>
	{/if}
</div>