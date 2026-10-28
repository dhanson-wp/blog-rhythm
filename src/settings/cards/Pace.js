import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Field, Input, RadioGroupControl, Stack, Text } from '@wordpress/ui';
import SettingsCard from './SettingsCard';
import { targetError, targetMax } from '../form';

export default function Pace( { form, onPace, onTarget, targetRef } ) {
	const error = targetError( form.pace, form.target );
	const errorId = useId();

	return (
		<SettingsCard
			title={ __( 'Your pace', 'blog-rhythm' ) }
			description={ __(
				'How often would you like to show up? Change it any time; nothing is lost.',
				'blog-rhythm'
			) }
		>
			<Stack direction="column" gap="lg">
				<RadioGroupControl
					label={ __( 'Cadence', 'blog-rhythm' ) }
					hideLabelFromVision
					value={ form.pace }
					onValueChange={ onPace }
					items={ [
						{
							value: 'daily',
							label: __( 'Most days', 'blog-rhythm' ),
							description: __(
								'A small daily habit.',
								'blog-rhythm'
							),
						},
						{
							value: 'weekly',
							label: __( 'Every week', 'blog-rhythm' ),
							description: __(
								'A few times, spread how you like.',
								'blog-rhythm'
							),
						},
						{
							value: 'monthly',
							label: __( 'Every month', 'blog-rhythm' ),
							description: __(
								'A slower, steadier rhythm.',
								'blog-rhythm'
							),
						},
						{
							value: 'open',
							label: __(
								'No target, just show me',
								'blog-rhythm'
							),
							description: __(
								'See what happens without a goal.',
								'blog-rhythm'
							),
						},
					] }
				/>
				{ form.pace !== 'open' && (
					<Field.Root
						invalid={ !! error }
						className="blog-rhythm-target"
					>
						<Field.Label>
							{ __( "Times I'd like to show up", 'blog-rhythm' ) }
						</Field.Label>
						<Stack direction="row" gap="sm" align="center">
							<Input
								ref={ targetRef }
								type="number"
								min={ 1 }
								max={ targetMax( form.pace ) }
								step={ 1 }
								className="blog-rhythm-target-input"
								value={ String( form.target ) }
								onChange={ ( event ) =>
									onTarget( event.target.value )
								}
								aria-invalid={ error ? 'true' : 'false' }
								aria-errormessage={
									error ? errorId : undefined
								}
							/>
							<Text>
								{ form.pace === 'monthly'
									? __( 'a month', 'blog-rhythm' )
									: __( 'a week', 'blog-rhythm' ) }
							</Text>
						</Stack>
						{ error && (
							<Field.Description
								id={ errorId }
								className="blog-rhythm-field-error"
							>
								{ error }
							</Field.Description>
						) }
						<Field.Description>
							{ __(
								'A gentle aim, not a requirement. Missing it never shows up as a miss.',
								'blog-rhythm'
							) }
						</Field.Description>
					</Field.Root>
				) }
			</Stack>
		</SettingsCard>
	);
}
