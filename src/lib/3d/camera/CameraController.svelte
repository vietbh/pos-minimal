<script lang="ts">
	import { useThrelte } from '@threlte/core';
	import type { Product } from '$lib/product/domain/Product';

	import { CameraFocusService } from './CameraFocusService';

	type Props = {
		selectedProduct?: Product | null;
		onCameraInteractionLockChange?: (locked: boolean) => void;
	};

	let {
		selectedProduct = null,
		onCameraInteractionLockChange
	}: Props = $props();

	const { camera } = useThrelte();
	const cameraFocus = new CameraFocusService();

	$effect(() => {
		const currentCamera = camera.current;

		if (!currentCamera) {
			return;
		}

		cameraFocus.setCamera(currentCamera);

		if (!selectedProduct) {
			cameraFocus.restoreSnapshot(() => {
				onCameraInteractionLockChange?.(false);
			});
			return;
		}

		onCameraInteractionLockChange?.(true);
		cameraFocus.captureSnapshot();

		const position = getProductPosition(selectedProduct.id);

		if (position) {
			cameraFocus.focus(position);
			return;
		}

		onCameraInteractionLockChange?.(false);
	});

	function getProductPosition(
		productId: string
	): [number, number, number] | null {
		const placements: Record<string, [number, number, number]> = {
			'rose-001': [-6.5, 0.5, -2],
			'tulip-001': [-6.5, 1.5, -2],
			'sunflower-001': [-6.5, 2.5, -2],
			'plant-001': [6.5, 0.5, -2],
			'pot-001': [6.5, 1.5, -2],
			'gift-001': [6.5, 2.5, -2]
		};

		return placements[productId] ?? null;
	}
</script>
