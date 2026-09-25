<script lang="ts">
	import Shelf from './components/Shelf.svelte';
</script>

<!-- Left wall shelves -->
<Shelf
	position={[-7, 0, -2]}
	rotation={[0, Math.PI / 2, 0]}
	width={5}
	height={3.5}
	depth={1}
	levels={4}
/>

<Shelf
	position={[-7, 0, 4]}
	rotation={[0, Math.PI / 2, 0]}
	width={5}
	height={3.5}
	depth={1}
	levels={4}
/>

<!-- Right wall shelves -->
<Shelf
	position={[7, 0, -2]}
	rotation={[0, -Math.PI / 2, 0]}
	width={5}
	height={3.5}
	depth={1}
	levels={4}
/>

<Shelf
	position={[7, 0, 4]}
	rotation={[0, -Math.PI / 2, 0]}
	width={5}
	height={3.5}
	depth={1}
	levels={4}
/>