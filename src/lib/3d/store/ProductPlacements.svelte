<script lang="ts">
	import Product3D from '$lib/3d/product/Product3D.svelte';
	import { products } from '$lib/product/data/products';
	import type { Product } from '$lib/product/domain/Product';

	type Props = {
		selectedProduct?: Product | null;
		onProductSelect?: (product: Product) => void;
	};

	let {
		selectedProduct = null,
		onProductSelect
	}: Props = $props();

	let hoveredProductId = $state<string | null>(null);

	const placements = [
		{ productId: 'rose-001', position: [-6.5, 0.5, -2] },
		{ productId: 'tulip-001', position: [-6.5, 1.5, -2] },
		{ productId: 'sunflower-001', position: [-6.5, 2.5, -2] },
		{ productId: 'plant-001', position: [6.5, 0.5, -2] },
		{ productId: 'pot-001', position: [6.5, 1.5, -2] },
		{ productId: 'gift-001', position: [6.5, 2.5, -2] }
	];

	function getProduct(productId: string) {
		return products.find((product) => product.id === productId);
	}
</script>

{#each placements as placement}
	{@const product = getProduct(placement.productId)}

	{#if product}
		<Product3D
			{product}
			position={placement.position as [number, number, number]}
			scale={0.8}
			hovered={hoveredProductId === product.id}
			selected={selectedProduct?.id === product.id}
			onHover={(hoveredProduct) => {
				hoveredProductId = hoveredProduct.id;
			}}
			onLeave={(hoveredProduct) => {
				if (hoveredProductId === hoveredProduct.id) {
					hoveredProductId = null;
				}
			}}
			onSelect={onProductSelect}
		/>
	{/if}
{/each}
