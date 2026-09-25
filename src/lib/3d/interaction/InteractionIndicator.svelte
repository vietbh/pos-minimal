<script lang="ts">
	import { T } from '@threlte/core';
	import { onMount } from 'svelte';

	let offset = $state(0);

	let frame = 0;

	onMount(() => {
		let start = performance.now();

		const animate = (time: number) => {
			const elapsed = (time - start) / 1000;

			offset = Math.sin(elapsed * 3) * 0.08;

			frame = requestAnimationFrame(animate);
		};

		frame = requestAnimationFrame(animate);

		return () => {
			cancelAnimationFrame(frame);
		};
	});
</script>

<T.Group position={[0, offset, 0]}>
	<T.Mesh position={[0, 0.12, 0]}>
		<T.CylinderGeometry args={[0.035, 0.035, 0.22, 8]} />

		<T.MeshStandardMaterial
			color="#ffffff"
			emissive="#ffffff"
			emissiveIntensity={0.5}
		/>
	</T.Mesh>

	<T.Mesh
		position={[0, -0.05, 0]}
		rotation={[Math.PI, 0, 0]}
	>
		<T.ConeGeometry args={[0.13, 0.25, 4]} />

		<T.MeshStandardMaterial
			color="#ff6b6b"
			emissive="#ff3333"
			emissiveIntensity={0.4}
		/>
	</T.Mesh>
</T.Group>