/**
 * jsdom has no PointerEvent; Base UI controls (radio, switch, checkbox)
 * create one when clicked. A MouseEvent subclass is enough for tests.
 */
if (
	typeof window !== 'undefined' &&
	typeof window.PointerEvent === 'undefined'
) {
	class PointerEvent extends window.MouseEvent {
		constructor( type, init = {} ) {
			super( type, init );
			this.pointerId = init.pointerId || 0;
			this.pointerType = init.pointerType || 'mouse';
		}
	}
	window.PointerEvent = PointerEvent;
}
