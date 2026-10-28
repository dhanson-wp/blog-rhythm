import { createRoot } from '@wordpress/element';
import { Card, SwitchControl } from '@wordpress/ui';

function Spike() {
	return (
		<Card.Root>
			<Card.Content>
				<p style={ { color: 'var(--wpds-color-fg-content-neutral)' } }>
					Spike widget
				</p>
				<SwitchControl label="Spike switch" />
			</Card.Content>
		</Card.Root>
	);
}

const root = document.getElementById( 'blog-rhythm-widget-root' );
if ( root ) {
	createRoot( root ).render( <Spike /> );
}
