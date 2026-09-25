import {
	Raycaster,
	Vector2,
	type Camera,
	type Object3D
} from 'three';

export type InteractiveObject = {
	id: string;
	object: Object3D;
};

export class InteractionManager {
	private readonly raycaster = new Raycaster();

	private readonly pointer = new Vector2();

	private readonly objects = new Map<string, Object3D>();

	private camera: Camera | null = null;

	setCamera(camera: Camera) {
		this.camera = camera;
	}

	register(id: string, object: Object3D) {
		this.objects.set(id, object);
	}

	unregister(id: string) {
		this.objects.delete(id);
	}

	setPointer(
		clientX: number,
		clientY: number,
		element: HTMLElement
	) {
		const rect = element.getBoundingClientRect();

		this.pointer.x =
			((clientX - rect.left) / rect.width) * 2 - 1;

		this.pointer.y =
			-((clientY - rect.top) / rect.height) * 2 + 1;
	}

	intersect(): InteractiveObject | null {
		if (!this.camera) {
			return null;
		}

		this.raycaster.setFromCamera(
			this.pointer,
			this.camera
		);

		const objects = [...this.objects.entries()];

		for (const [id, object] of objects) {
			const intersections =
				this.raycaster.intersectObject(
					object,
					true
				);

			if (intersections.length > 0) {
				return {
					id,
					object
				};
			}
		}

		return null;
	}

	clear() {
		this.objects.clear();
	}
}