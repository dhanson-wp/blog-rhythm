import { getSettings as getDateSettings, dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import {
	CheckboxControl,
	Fieldset,
	InputControl,
	SelectControl,
	Stack,
	SwitchControl,
	Text,
} from '@wordpress/ui';
import SettingsCard from './SettingsCard';
import { MESSAGE_MAX } from '../form';

function dayItems() {
	return [
		{ value: 'daily', label: __( 'Every day', 'blog-rhythm' ) },
		{ value: 'weekdays', label: __( 'Weekdays', 'blog-rhythm' ) },
		{ value: 'mon', label: __( 'Mondays', 'blog-rhythm' ) },
		{ value: 'wed', label: __( 'Wednesdays', 'blog-rhythm' ) },
		{ value: 'fri', label: __( 'Fridays', 'blog-rhythm' ) },
		{ value: 'sun', label: __( 'Sundays', 'blog-rhythm' ) },
	];
}

function hourItems() {
	const format = ( getDateSettings().formats || {} ).time || 'g:i a';
	return Array.from( { length: 24 }, ( _, hour ) => ( {
		value: String( hour ),
		label: dateI18n(
			format,
			`2026-01-01T${ String( hour ).padStart( 2, '0' ) }:00:00`,
			'UTC'
		),
	} ) );
}

export default function Reminders( { reminders, timezone, email, onChange } ) {
	const set = ( patch ) => onChange( { ...reminders, ...patch } );

	return (
		<SettingsCard
			title={ __( 'Reminders', 'blog-rhythm' ) }
			description={ __(
				'Off unless you want one. A nudge you chose, in your words, never a scolding.',
				'blog-rhythm'
			) }
		>
			<Stack direction="column" gap="lg">
				<SwitchControl
					label={ __( 'Remind me to write', 'blog-rhythm' ) }
					description={ __( 'Sent only to you.', 'blog-rhythm' ) }
					checked={ !! reminders.enabled }
					onCheckedChange={ ( checked ) =>
						set( { enabled: !! checked } )
					}
				/>
				{ reminders.enabled && (
					<>
						<Fieldset.Root>
							<Fieldset.Legend>
								{ __( 'When', 'blog-rhythm' ) }
							</Fieldset.Legend>
							<Stack
								direction="row"
								gap="sm"
								align="center"
								wrap="wrap"
							>
								<SelectControl
									label={ __( 'Day', 'blog-rhythm' ) }
									hideLabelFromVision
									items={ dayItems() }
									value={ reminders.day }
									onValueChange={ ( day ) => set( { day } ) }
								/>
								<Text>{ __( 'at', 'blog-rhythm' ) }</Text>
								<SelectControl
									label={ __( 'Time', 'blog-rhythm' ) }
									hideLabelFromVision
									items={ hourItems() }
									value={ String( reminders.hour ) }
									onValueChange={ ( hour ) =>
										set( {
											hour: parseInt( hour, 10 ) || 0,
										} )
									}
								/>
							</Stack>
							<Fieldset.Description>
								{ sprintf(
									/* translators: %s: site time zone, such as America/New_York or UTC+02:00 */
									__(
										"In your site's time zone (%s). Delivery times are approximate.",
										'blog-rhythm'
									),
									timezone
								) }
							</Fieldset.Description>
						</Fieldset.Root>
						<Fieldset.Root>
							<Fieldset.Legend>
								{ __( 'How', 'blog-rhythm' ) }
							</Fieldset.Legend>
							<Stack direction="column" gap="md">
								<CheckboxControl
									label={
										email
											? sprintf(
													/* translators: %s: the writer's email address */
													__(
														'Email me at %s',
														'blog-rhythm'
													),
													email
												)
											: __( 'Email me', 'blog-rhythm' )
									}
									description={ __(
										'Your site needs to be able to send email.',
										'blog-rhythm'
									) }
									checked={ !! reminders.email }
									onCheckedChange={ ( checked ) =>
										set( { email: !! checked } )
									}
								/>
								<CheckboxControl
									label={ __(
										'Show a quiet note on my dashboard',
										'blog-rhythm'
									) }
									checked={ !! reminders.widget_note }
									onCheckedChange={ ( checked ) =>
										set( { widget_note: !! checked } )
									}
								/>
							</Stack>
						</Fieldset.Root>
						<CheckboxControl
							label={ __(
								"Skip it if I've already shown up",
								'blog-rhythm'
							) }
							description={ __(
								"So it never nags after you've done the thing.",
								'blog-rhythm'
							) }
							checked={ !! reminders.skip_if_done }
							onCheckedChange={ ( checked ) =>
								set( { skip_if_done: !! checked } )
							}
						/>
						<InputControl
							label={ __( 'What it says', 'blog-rhythm' ) }
							description={ __(
								'Resting pauses reminders automatically. Every email has a one-click way to turn them off.',
								'blog-rhythm'
							) }
							value={ reminders.message }
							maxLength={ MESSAGE_MAX }
							onChange={ ( event ) =>
								set( { message: event.target.value } )
							}
						/>
					</>
				) }
			</Stack>
		</SettingsCard>
	);
}
