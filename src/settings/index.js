import { createRoot } from '@wordpress/element';
import { Page } from '@wordpress/admin-ui';
import { Card, SwitchControl } from '@wordpress/ui';

function Spike() {
	return (
		<Page title="Blog rhythm">
			<Card.Root>
				<Card.Content>
					<p style={ { color: 'var(--wpds-color-fg-content-neutral)' } }>
						Spike settings
					</p>
					<SwitchControl label="Spike switch" />
				</Card.Content>
			</Card.Root>
		</Page>
	);
}

const root = document.getElementById( 'blog-rhythm-settings-root' );
if ( root ) {
	createRoot( root ).render( <Spike /> );
}
