import { createRoot } from '@wordpress/element';
import Widget from './Widget';

const root = document.getElementById( 'blog-rhythm-widget-root' );
if ( root ) {
	createRoot( root ).render(
		<Widget settingsUrl={ root.dataset.settingsUrl || '' } />
	);
}
