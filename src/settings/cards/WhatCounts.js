import { __ } from '@wordpress/i18n';
import { CheckboxControl, Stack } from '@wordpress/ui';
import SettingsCard from './SettingsCard';

export default function WhatCounts( { counts, onChange } ) {
	const options = [
		{ key: 'publish', label: __( 'Publishing a post', 'blog-rhythm' ) },
		{
			key: 'draft',
			label: __( 'Working on a draft', 'blog-rhythm' ),
			description: __(
				'Saving or editing an unpublished post.',
				'blog-rhythm'
			),
		},
		{
			key: 'idea',
			label: __( 'Ideas and outlines', 'blog-rhythm' ),
			description: __(
				"Quick notes you log yourself. WordPress can't see these on its own.",
				'blog-rhythm'
			),
		},
		{ key: 'edit', label: __( 'Updating an older post', 'blog-rhythm' ) },
	];

	return (
		<SettingsCard
			title={ __( 'What counts as showing up', 'blog-rhythm' ) }
			description={ __(
				'Pick everything that feels like real writing time to you.',
				'blog-rhythm'
			) }
		>
			<Stack direction="column" gap="md">
				{ options.map( ( option ) => (
					<CheckboxControl
						key={ option.key }
						label={ option.label }
						description={ option.description }
						checked={ !! counts[ option.key ] }
						onCheckedChange={ ( checked ) =>
							onChange( {
								...counts,
								[ option.key ]: !! checked,
							} )
						}
					/>
				) ) }
			</Stack>
		</SettingsCard>
	);
}
