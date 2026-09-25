import {
	getPerformanceConfig,
	type PerformanceConfig,
	type PerformanceQuality
} from './PerformanceConfig';

export class PerformanceManager {
	private readonly config: PerformanceConfig;

	constructor(config: PerformanceConfig = getPerformanceConfig()) {
		this.config = config;
	}

	get quality(): PerformanceQuality {
		return this.config.quality;
	}

	get pixelRatio(): [number, number] {
		return this.config.pixelRatio;
	}

	get shadows(): boolean {
		return this.config.shadows;
	}

	getConfig(): PerformanceConfig {
		return this.config;
	}
}
