<script lang="ts">
	import { T } from '@threlte/core';
	import { gsap } from 'gsap';
	import type { Product } from '$lib/product/domain/Product';
	import InteractionIndicator from '$lib/3d/interaction/InteractionIndicator.svelte';

	type Props = {
		product: Product;
		position?: [number, number, number];
		scale?: number;
		hovered?: boolean;
		selected?: boolean;
		onSelect?: (product: Product) => void;
		onHover?: (product: Product) => void;
		onLeave?: (product: Product) => void;
	};

	let {
		product,
		position = [0, 0, 0],
		scale = 1,
		hovered = false,
		selected = false,
		onSelect,
		onHover,
		onLeave
	}: Props = $props();

	let animatedScale = $state<number | null>(null);
	const renderScale = $derived(animatedScale ?? scale);

	$effect(() => {
		const targetScale = selected
			? scale * 1.12
			: hovered
				? scale * 1.08
				: scale;

		const currentScale = animatedScale ?? scale;

		if (animatedScale === null) {
			animatedScale = scale;
		}

		const state = { value: currentScale };

		const animation = gsap.to(state, {
			value: targetScale,
			duration: selected ? 0.24 : hovered ? 0.22 : 0.18,
			ease: selected || hovered ? 'power2.out' : 'power2.inOut',
			onUpdate: () => {
				animatedScale = state.value;
			}
		});

		return () => {
			animation.kill();
		};
	});

	function handlePointerEnter(event: PointerEvent) {
		if (event.pointerType === 'touch') return;

		onHover?.(product);
	}

	function handlePointerLeave(event: PointerEvent) {
		if (event.pointerType === 'touch') return;

		onLeave?.(product);
	}

	function handlePointerUp(event: PointerEvent) {
		if (event.pointerType !== 'touch') return;

		onSelect?.(product);
	}

	function handleClick(event: MouseEvent) {
		event.stopPropagation();

		// Touch selection is handled by pointerup so mobile interaction
		// does not depend on a synthetic click after a touch gesture.
		if ('pointerType' in event) return;

		onSelect?.(product);
	}
</script>

<T.Group
	{position}
	scale={[renderScale, renderScale, renderScale]}
	onclick={handleClick}
	onpointerenter={handlePointerEnter}
	onpointerleave={handlePointerLeave}
	onpointerup={handlePointerUp}
>
	{#if hovered}
		<T.Group position={[0, 1.35, 0]}>
			<InteractionIndicator />
		</T.Group>
	{/if}

	{#if selected}
		<T.Mesh position={[0, 0.03, 0]} rotation={[-Math.PI / 2, 0, 0]}>
			<T.TorusGeometry args={[0.58, 0.035, 8, 32]} />
			<T.MeshBasicMaterial color="#ffd166" />
		</T.Mesh>
	{/if}

	<T.Group>
		{#if product.category === 'flowers'}
			<T.Mesh castShadow>
				<T.CylinderGeometry args={[0.08, 0.12, 0.8, 12]} />
				<T.MeshStandardMaterial color="#3f7d3f" />
			</T.Mesh>

			<T.Mesh position={[0, 0.5, 0]} castShadow>
				<T.SphereGeometry args={[0.3, 16, 16]} />
				<T.MeshStandardMaterial
					color={selected ? '#ff6b9a' : hovered ? '#ff4f81' : '#e88aa8'}
				/>
			</T.Mesh>

		{:else if product.category === 'plants'}
			<T.Mesh castShadow>
				<T.CylinderGeometry args={[0.3, 0.25, 0.35, 16]} />
				<T.MeshStandardMaterial color="#b86f4b" />
			</T.Mesh>

			<T.Mesh position={[0, 0.55, 0]} castShadow>
				<T.SphereGeometry args={[0.45, 16, 16]} />
				<T.MeshStandardMaterial
					color={selected ? '#7bd67b' : hovered ? '#68b968' : '#4d914d'}
				/>
			</T.Mesh>

		{:else}
			<T.Mesh castShadow>
				<T.BoxGeometry args={[0.6, 0.6, 0.6]} />
				<T.MeshStandardMaterial
					color={selected ? '#ffd08a' : hovered ? '#f0c080' : '#d4a574'}
				/>
			</T.Mesh>
		{/if}
	</T.Group>
</T.Group>
