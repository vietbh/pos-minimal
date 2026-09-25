import { gsap } from 'gsap';
import { Quaternion, Vector3, type Camera } from 'three';

export type CameraSnapshot = {
	position: Vector3;
	quaternion: Quaternion;
};

export class CameraFocusService {
	private camera: Camera | null = null;
	private snapshot: CameraSnapshot | null = null;
	private animation?: gsap.core.Tween;

	setCamera(camera: Camera) {
		this.camera = camera;
	}

	captureSnapshot() {
		if (!this.camera || this.snapshot) return;

		this.snapshot = {
			position: this.camera.position.clone(),
			quaternion: this.camera.quaternion.clone()
		};
	}

	focus(
		position: [number, number, number],
		targetHeight = 0.8,
		onComplete?: () => void
	) {
		if (!this.camera) {
			onComplete?.();
			return;
		}

		this.animation?.kill();

		const target = new Vector3(
			position[0],
			position[1] + targetHeight,
			position[2]
		);

		const cameraPosition = new Vector3(
			position[0],
			position[1] + 2.2,
			position[2] + 3.5
		);

		this.animation = gsap.to(this.camera.position, {
			x: cameraPosition.x,
			y: cameraPosition.y,
			z: cameraPosition.z,
			duration: 0.8,
			ease: 'power3.out',
			onUpdate: () => {
				this.camera?.lookAt(target);
			},
			onComplete
		});
	}

	restoreSnapshot(onComplete?: () => void) {
		if (!this.camera || !this.snapshot) {
			onComplete?.();
			return;
		}

		this.animation?.kill();

		const snapshot = this.snapshot;
		const startQuaternion = this.camera.quaternion.clone();
		const progress = { value: 0 };

		this.animation = gsap.to(this.camera.position, {
			x: snapshot.position.x,
			y: snapshot.position.y,
			z: snapshot.position.z,
			duration: 0.8,
			ease: 'power3.inOut',
			onUpdate: () => {
				if (!this.camera || !this.animation) return;

				progress.value = this.animation.progress();
				this.camera.quaternion.slerpQuaternions(
					startQuaternion,
					snapshot.quaternion,
					progress.value
				);
			},
			onComplete: () => {
				if (this.camera) {
					this.camera.position.copy(snapshot.position);
					this.camera.quaternion.copy(snapshot.quaternion);
				}

				this.snapshot = null;
				onComplete?.();
			}
		});
	}
}
