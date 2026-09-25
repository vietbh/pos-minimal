<script lang="ts">
	const width = 20;
	const depth = 20;
    import { T } from '@threlte/core';
</script>

<T.Mesh
	rotation={[-Math.PI / 2, 0, 0]}
	position={[0, 0, 0]}
	receiveShadow
>
	<T.PlaneGeometry args={[width, depth]} />

	<T.MeshStandardMaterial
		color="#2b2b2b"
		roughness={0.85}
	/>
</T.Mesh>