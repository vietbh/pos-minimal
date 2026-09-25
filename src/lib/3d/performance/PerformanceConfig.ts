export type PerformanceQuality = 'low' | 'medium' | 'high';

export type PerformanceConfig = {
	quality: PerformanceQuality;
	pixelRatio: [number, number];
	shadows: boolean;
	maxPixelRatio: number;
};

const HIGH_CONFIG: PerformanceConfig = {
	quality: 'high',
	pixelRatio: [1, 1.5],
	shadows: true,
	maxPixelRatio: 1.5
};

const MEDIUM_CONFIG: PerformanceConfig = {
	quality: 'medium',
	pixelRatio: [1, 1.25],
	shadows: true,
	maxPixelRatio: 1.25
};

const LOW_CONFIG: PerformanceConfig = {
	quality: 'low',
	pixelRatio: [1, 1],
	shadows: false,
	maxPixelRatio: 1
};

function isMobileDevice(): boolean {
	if (typeof navigator === 'undefined') return false;
	return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
}

function getMemoryGB(): number | null {
	if (typeof navigator === 'undefined') return null;

	const memory = (navigator as Navigator & { deviceMemory?: number }).deviceMemory;
	return typeof memory === 'number' ? memory : null;
}

export function getPerformanceConfig(): PerformanceConfig {
	const mobile = isMobileDevice();
	const memoryGB = getMemoryGB();

	if (memoryGB !== null && memoryGB <= 2) {
		return LOW_CONFIG;
	}

	if (mobile) {
		return MEDIUM_CONFIG;
	}

	return HIGH_CONFIG;
}
