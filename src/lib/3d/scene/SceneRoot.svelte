<script lang="ts">
	import { Canvas } from '@threlte/core';
	import type { Product } from '$lib/product/domain/Product';
	import { PerformanceManager } from '$lib/3d/performance/PerformanceManager';
	import StoreScene from './StoreScene.svelte';

	type Props = {
		selectedProduct?: Product | null;
		onProductSelect?: (product: Product) => void;
	};

	let {
		selectedProduct = null,
		onProductSelect
	}: Props = $props();

	const performanceManager = new PerformanceManager();
</script>

<Canvas dpr={performanceManager.pixelRatio}>
	<StoreScene
		{selectedProduct}
		{onProductSelect}
		performanceConfig={performanceManager.getConfig()}
	/>
</Canvas>
