import { __ } from '@wordpress/i18n';
import { InputControl } from '@wordpress/ui';
import SettingsCard from './SettingsCard';
import { INTENTION_MAX } from '../form';

export default function InYourWords( { intention, onChange } ) {
	return (
		<SettingsCard title={ __( 'In your own words', 'blog-rhythm' ) }>
			<InputControl
				label={ __( 'What are you doing this for?', 'blog-rhythm' ) }
				description={ __(
					'Shown at the top of your dashboard widget. Leave it empty to hide it.',
					'blog-rhythm'
				) }
				value={ intention }
				maxLength={ INTENTION_MAX }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</SettingsCard>
	);
}
