import { createRoot } from '@wordpress/element';
import SettingsApp from './SettingsApp';

const root = document.getElementById( 'blog-rhythm-settings-root' );
if ( root ) {
	const data = window.blogRhythmSettings || {};
	createRoot( root ).render(
		<SettingsApp
			timezone={ data.timezone || '' }
			email={ data.email || '' }
		/>
	);
}
