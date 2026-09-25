import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import type { GLTF } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { AssetCache } from './AssetCache';

export class GLTFLoaderService {
	private readonly loader = new GLTFLoader();
	private readonly cache = new AssetCache<GLTF>();

	load(url: string): Promise<GLTF> {
		const cached = this.cache.get(url);

		if (cached) {
			return cached;
		}

		const request = new Promise<GLTF>((resolve, reject) => {
			this.loader.load(url, resolve, undefined, reject);
		});

		this.cache.set(url, request);

		request.catch(() => {
			this.cache.delete(url);
		});

		return request;
	}

	clearCache(): void {
		this.cache.clear();
	}
}

export const gltfLoaderService = new GLTFLoaderService();
