/**
 * One settings section: a Card with a heading and an optional lead line.
 */
import { Card, Text } from '@wordpress/ui';

export default function SettingsCard( { title, description, children } ) {
	return (
		<Card.Root className="blog-rhythm-card">
			<Card.Header>
				<Card.Title render={ <h2 /> }>{ title }</Card.Title>
				{ description && (
					<Text render={ <p /> } className="blog-rhythm-card-lead">
						{ description }
					</Text>
				) }
			</Card.Header>
			<Card.Content>{ children }</Card.Content>
		</Card.Root>
	);
}
