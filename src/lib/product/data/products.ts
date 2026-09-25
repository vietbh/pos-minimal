import type { Product } from '../domain/Product';

export const products: Product[] = [
	{
		id: 'rose-001',
		name: 'Rose Bouquet',
		price: 250000,
		category: 'flowers'
	},
	{
		id: 'tulip-001',
		name: 'Tulip Bouquet',
		price: 180000,
		category: 'flowers'
	},
	{
		id: 'sunflower-001',
		name: 'Sunflower Bouquet',
		price: 220000,
		category: 'flowers'
	},
	{
		id: 'plant-001',
		name: 'Small Indoor Plant',
		price: 150000,
		category: 'plants'
	},
	{
		id: 'pot-001',
		name: 'Ceramic Flower Pot',
		price: 90000,
		category: 'accessories'
	},
	{
		id: 'gift-001',
		name: 'Flower Gift Box',
		price: 320000,
		category: 'gifts'
	}
];