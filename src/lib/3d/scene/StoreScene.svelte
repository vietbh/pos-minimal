<script lang="ts">
	import { interactivity, OrbitControls } from '@threlte/extras';
	import { T } from '@threlte/core';

	import type { Product } from '$lib/product/domain/Product';
	import type { PerformanceConfig } from '$lib/3d/performance/PerformanceConfig';

	import StoreFloor from '../store/StoreFloor.svelte';
	import StoreWalls from '../store/StoreWalls.svelte';
	import StoreShelves from '../store/StoreShelves.svelte';
	import StoreCounter from '../store/StoreCounter.svelte';
	import ProductPlacements from '../store/ProductPlacements.svelte';
	import CameraController from '../camera/CameraController.svelte';

	type Props = {
		selectedProduct?: Product | null;
		onProductSelect?: (product: Product) => void;
		performanceConfig?: PerformanceConfig;
	};

	let {
		selectedProduct = null,
		onProductSelect,
		performanceConfig = {
			quality: 'high',
			pixelRatio: [1, 1.5],
			shadows: true,
			maxPixelRatio: 1.5
		}
	}: Props = $props();

	let controlsEnabled = $state(true);

	interactivity();
</script>

<T.PerspectiveCamera
	makeDefault
	position={[8, 6, 10]}
	fov={55}
	near={0.1}
	far={100}
/>

<CameraController
	{selectedProduct}
	onCameraInteractionLockChange={(locked) => {
		controlsEnabled = !locked;
	}}
/>

<T.AmbientLight intensity={1.2} />

<T.DirectionalLight
	position={[5, 10, 5]}
	intensity={3}
	castShadow={performanceConfig.shadows}
/>

<StoreFloor />
<StoreWalls />
<StoreShelves />

<ProductPlacements
	{selectedProduct}
	{onProductSelect}
/>

<StoreCounter />

<OrbitControls
	enabled={controlsEnabled}
	enableDamping
	enablePan
	enableZoom
/>
