<script lang="ts">
    import { T } from '@threlte/core';
	type Props = {
		position?: [number, number, number];
		rotation?: [number, number, number];
		width?: number;
		height?: number;
		depth?: number;
		levels?: number;
	};

	let {
		position = [0, 0, 0],
		rotation = [0, 0, 0],
		width = 4,
		height = 3,
		depth = 0.8,
		levels = 3
	}: Props = $props();

	const shelfThickness = 0.12;
	const postThickness = 0.12;

	const levelHeight = $derived(height / levels);
</script>

<T.Group {position} {rotation}>
	<!-- Left post -->
	<T.Mesh
		position={[-width / 2, height / 2, 0]}
		castShadow
		receiveShadow
	>
		<T.BoxGeometry
			args={[postThickness, height, depth]}
		/>

		<T.MeshStandardMaterial
			color="#6b4f3a"
			roughness={0.75}
		/>
	</T.Mesh>

	<!-- Right post -->
	<T.Mesh
		position={[width / 2, height / 2, 0]}
		castShadow
		receiveShadow
	>
		<T.BoxGeometry
			args={[postThickness, height, depth]}
		/>

		<T.MeshStandardMaterial
			color="#6b4f3a"
			roughness={0.75}
		/>
	</T.Mesh>

	{#each Array(levels) as _, index}
		<T.Mesh
			position={[
				0,
				index * levelHeight + shelfThickness / 2,
				0
			]}
			castShadow
			receiveShadow
		>
			<T.BoxGeometry
				args={[
					width,
					shelfThickness,
					depth
				]}
			/>

			<T.MeshStandardMaterial
				color="#9a7252"
				roughness={0.7}
			/>
		</T.Mesh>
	{/each}
</T.Group>