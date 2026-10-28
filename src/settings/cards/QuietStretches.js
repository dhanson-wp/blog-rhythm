import { __ } from '@wordpress/i18n';
import { InputControl, Stack, SwitchControl, Text } from '@wordpress/ui';
import SettingsCard from './SettingsCard';

export default function QuietStretches( {
	resting,
	restUntil,
	onResting,
	onRestUntil,
} ) {
	return (
		<SettingsCard
			title={ __( 'Quiet stretches', 'blog-rhythm' ) }
			description={ __(
				'Life happens. Resting is part of a rhythm.',
				'blog-rhythm'
			) }
		>
			<Stack direction="column" gap="lg">
				<SwitchControl
					label={ __( 'Rest for a while', 'blog-rhythm' ) }
					description={ __(
						"Hides targets and nudges until you're back. Past days stay as they were.",
						'blog-rhythm'
					) }
					checked={ resting }
					onCheckedChange={ ( checked ) => onResting( !! checked ) }
				/>
				{ resting && (
					<InputControl
						type="date"
						className="blog-rhythm-date"
						label={ __( 'Until', 'blog-rhythm' ) }
						description={ __(
							'Optional. Leave blank to come back whenever.',
							'blog-rhythm'
						) }
						value={ restUntil }
						onChange={ ( event ) =>
							onRestUntil( event.target.value )
						}
					/>
				) }
				<Text
					render={ <p /> }
					variant="body-sm"
					className="blog-rhythm-help"
				>
					{ __(
						'A quiet stretch is never counted against you. Rest as long as you need.',
						'blog-rhythm'
					) }
				</Text>
			</Stack>
		</SettingsCard>
	);
}
