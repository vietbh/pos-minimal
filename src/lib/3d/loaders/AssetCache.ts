export class AssetCache<T> {
	private readonly cache = new Map<string, Promise<T>>();

	get(url: string): Promise<T> | undefined {
		return this.cache.get(url);
	}

	set(url: string, value: Promise<T>): void {
		this.cache.set(url, value);
	}

	has(url: string): boolean {
		return this.cache.has(url);
	}

	delete(url: string): void {
		this.cache.delete(url);
	}

	clear(): void {
		this.cache.clear();
	}
}
