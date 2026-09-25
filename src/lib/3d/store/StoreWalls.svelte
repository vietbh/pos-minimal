<script lang="ts">
    import { T } from '@threlte/core';
	const width = 20;
	const depth = 20;
	const height = 5;
	const thickness = 0.3;
</script>

<!-- Back -->
<T.Mesh
	position={[0, height / 2, -depth / 2]}
	castShadow
	receiveShadow
>
	<T.BoxGeometry args={[width, height, thickness]} />

	<T.MeshStandardMaterial
		color="#dedede"
		roughness={0.8}
	/>
</T.Mesh>

<!-- Left -->
<T.Mesh
	position={[-width / 2, height / 2, 0]}
	castShadow
	receiveShadow
>
	<T.BoxGeometry args={[thickness, height, depth]} />

	<T.MeshStandardMaterial
		color="#dedede"
		roughness={0.8}
	/>
</T.Mesh>

<!-- Right -->
<T.Mesh
	position={[width / 2, height / 2, 0]}
	castShadow
	receiveShadow
>
	<T.BoxGeometry args={[thickness, height, depth]} />

	<T.MeshStandardMaterial
		color="#dedede"
		roughness={0.8}
	/>
</T.Mesh>