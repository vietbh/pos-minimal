<script lang="ts">
	import { Canvas, T } from '@threlte/core';
	import { OrbitControls } from '@threlte/extras';
	import type { Product } from '$lib/product/domain/Product';

	type Props = {
		product: Product;
	};

	let { product }: Props = $props();

	const background = '#f3f4f6';

	function isFlower(category: string) {
		return category === 'flowers';
	}

	function isPlant(category: string) {
		return category === 'plants';
	}
</script>

<div class="relative h-full w-full overflow-hidden rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100">
	<Canvas dpr={[1, 1.5]}>
		<T.Color attach="background" args={[background]} />

		<T.PerspectiveCamera
			makeDefault
			position={[0, 1.6, 4.2]}
			fov={38}
			near={0.1}
			far={50}
		/>

		<T.AmbientLight intensity={1.8} />

		<T.DirectionalLight
			position={[3, 5, 4]}
			intensity={3}
		/>

		<T.DirectionalLight
			position={[-3, 2, 2]}
			intensity={1.2}
		/>

		<T.Group position={[0, -0.55, 0]}>
			<T.Mesh receiveShadow>
				<T.CylinderGeometry args={[1.15, 1.15, 0.08, 48]} />
				<T.MeshStandardMaterial color="#ffffff" roughness={0.8} />
			</T.Mesh>
		</T.Group>

		{#if isFlower(product.category)}
			<T.Group position={[0, 0, 0]}>
				<T.Mesh position={[0, 0.35, 0]} castShadow>
					<T.CylinderGeometry args={[0.045, 0.055, 1.5, 12]} />
					<T.MeshStandardMaterial color="#4d8b57" roughness={0.7} />
				</T.Mesh>

				<T.Mesh position={[0, 1.1, 0]} castShadow>
					<T.SphereGeometry args={[0.48, 24, 16]} />
					<T.MeshStandardMaterial color="#f48fb1" roughness={0.55} />
				</T.Mesh>

				<T.Mesh position={[-0.3, 0.72, 0]} rotation={[0, 0, -0.5]}>
					<T.SphereGeometry args={[0.2, 16, 12]} />
					<T.MeshStandardMaterial color="#66a96f" roughness={0.7} />
				</T.Mesh>

				<T.Mesh position={[0.3, 0.62, 0]} rotation={[0, 0, 0.5]}>
					<T.SphereGeometry args={[0.18, 16, 12]} />
					<T.MeshStandardMaterial color="#66a96f" roughness={0.7} />
				</T.Mesh>
			</T.Group>
		{:else if isPlant(product.category)}
			<T.Group>
				<T.Mesh position={[0, -0.05, 0]} castShadow>
					<T.CylinderGeometry args={[0.48, 0.36, 0.65, 24]} />
					<T.MeshStandardMaterial color="#c98255" roughness={0.75} />
				</T.Mesh>

				<T.Mesh position={[0, 0.75, 0]} castShadow>
					<T.SphereGeometry args={[0.7, 24, 18]} />
					<T.MeshStandardMaterial color="#69a86f" roughness={0.8} />
				</T.Mesh>
			</T.Group>
		{:else}
			<T.Mesh position={[0, 0.2, 0]} castShadow>
				<T.BoxGeometry args={[1.2, 1.1, 1.2]} />
				<T.MeshStandardMaterial color="#d6a86c" roughness={0.7} />
			</T.Mesh>
		{/if}

		<OrbitControls
			enablePan={false}
			enableZoom={false}
			enableDamping
			rotateSpeed={0.7}
		/>
	</Canvas>

	<div class="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-white/75 px-3 py-1.5 text-[11px] font-medium text-gray-500 backdrop-blur-sm">
		Kéo để xoay
	</div>
</div>
