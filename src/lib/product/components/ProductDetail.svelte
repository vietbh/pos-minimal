<script lang="ts">
	import { fly, fade } from 'svelte/transition';
	import type { Product } from '$lib/product/domain/Product';
	import ProductPreview3D from './ProductPreview3D.svelte';
	import { onMount } from 'svelte';

	type Props = {
		product: Product;
		onClose?: () => void;
	};

	let { product, onClose }: Props = $props();

	const formatPrice = (price: number) =>
		new Intl.NumberFormat('vi-VN').format(price) + ' ₫';

	function close() {
		onClose?.();
	}

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'Escape') {
			close();
		}
	}

	onMount(() => {
		window.addEventListener('keydown', handleKeydown);

		return () => {
			window.removeEventListener('keydown', handleKeydown);
		};
	});
</script>

<div class="fixed inset-0 z-50 pointer-events-none">
	<button
		type="button"
		class="pointer-events-auto absolute inset-0 h-full w-full cursor-default border-0 bg-black/25 p-0 backdrop-blur-[2px]"
		aria-label="Đóng chi tiết sản phẩm"
		onclick={close}
		transition:fade={{ duration: 220 }}
	></button>

	<dialog
		open
		class="pointer-events-auto absolute bottom-0 left-0 right-0 m-0 flex max-h-[90vh] w-full max-w-none flex-col overflow-y-auto rounded-t-[2rem] bg-white p-5 shadow-[0_-20px_60px_rgba(0,0,0,0.18)] md:bottom-6 md:left-auto md:right-6 md:top-6 md:m-0 md:block md:max-h-none md:w-[420px] md:rounded-3xl md:p-6"
		aria-labelledby="product-detail-title"
		transition:fly={{ y: 40, duration: 350, opacity: 0 }}
	>
		<div class="mb-4 flex justify-center md:hidden">
			<div class="h-1.5 w-12 rounded-full bg-gray-300"></div>
		</div>

		<div class="flex items-start justify-between gap-4">
			<div class="min-w-0">
				<p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">
					{product.category}
				</p>

				<h2
					id="product-detail-title"
					class="mt-1 truncate text-xl font-semibold text-gray-900 md:text-2xl"
				>
					{product.name}
				</h2>
			</div>

			<button
				type="button"
				class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xl leading-none text-gray-500 transition hover:bg-gray-200 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 active:scale-95"
				aria-label="Đóng chi tiết sản phẩm"
				onclick={close}
			>
				<span aria-hidden="true">×</span>
			</button>
		</div>

		<div class="mt-5 h-64 overflow-hidden rounded-2xl md:h-[280px]">
			<ProductPreview3D {product} />
		</div>

		<div class="mt-5">
			<p class="text-xs font-medium uppercase tracking-wider text-gray-400">
				Giá sản phẩm
			</p>

			<p class="mt-1 text-2xl font-bold tracking-tight text-gray-900">
				{formatPrice(product.price)}
			</p>
		</div>

		<button
			type="button"
			class="mt-5 w-full rounded-xl bg-gray-900 px-4 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-gray-800 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-gray-900/30 active:translate-y-0 active:scale-[0.99]"
			onclick={close}
		>
			Tiếp tục khám phá
		</button>

		<p class="mt-3 text-center text-xs text-gray-400">
			Kéo sản phẩm để xoay · Nhấn Esc để đóng
		</p>
	</dialog>
</div>
