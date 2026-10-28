import { __ } from '@wordpress/i18n';
import { CheckboxControl, SelectControl, Stack } from '@wordpress/ui';
import SettingsCard from './SettingsCard';

export default function WidgetLook( {
	look,
	showSummary,
	showLogButton,
	ideasCount = true,
	onChange,
} ) {
	return (
		<SettingsCard title={ __( 'Your dashboard widget', 'blog-rhythm' ) }>
			<Stack direction="column" gap="md">
				<SelectControl
					label={ __( 'Show', 'blog-rhythm' ) }
					items={ [
						{
							value: 'auto',
							label: __(
								'Match my pace (recommended)',
								'blog-rhythm'
							),
						},
						{
							value: 'dots',
							label: __( 'Calendar of dots', 'blog-rhythm' ),
						},
						{
							value: 'weeks',
							label: __( 'Week by week', 'blog-rhythm' ),
						},
					] }
					value={ look }
					onValueChange={ ( value ) => onChange( { look: value } ) }
				/>
				<CheckboxControl
					label={ __( 'Show the one-line summary', 'blog-rhythm' ) }
					checked={ !! showSummary }
					onCheckedChange={ ( checked ) =>
						onChange( { show_summary: !! checked } )
					}
				/>
				<CheckboxControl
					label={ __( 'Show "I showed up today"', 'blog-rhythm' ) }
					description={
						ideasCount
							? undefined
							: __(
									'The "I showed up today" button only appears when ideas and outlines count.',
									'blog-rhythm'
							  )
					}
					disabled={ ! ideasCount }
					checked={ !! showLogButton }
					onCheckedChange={ ( checked ) =>
						onChange( { show_log_button: !! checked } )
					}
				/>
			</Stack>
		</SettingsCard>
	);
}
