<script lang="ts">
    import { T } from '@threlte/core';
</script>

<T.Group position={[0, 0, -7]}>
	<!-- Counter body -->
	<T.Mesh
		position={[0, 0.6, 0]}
		castShadow
		receiveShadow
	>
		<T.BoxGeometry args={[4, 1.2, 1.2]} />

		<T.MeshStandardMaterial
			color="#8b5e3c"
			roughness={0.7}
		/>
	</T.Mesh>

	<!-- Counter top -->
	<T.Mesh
		position={[0, 1.25, 0]}
		castShadow
	>
		<T.BoxGeometry args={[4.2, 0.15, 1.35]} />

		<T.MeshStandardMaterial
			color="#d4a574"
			roughness={0.5}
		/>
	</T.Mesh>
</T.Group>