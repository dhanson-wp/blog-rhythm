import { __ } from '@wordpress/i18n';
import { AlertDialog, Button, Stack, Text } from '@wordpress/ui';
import SettingsCard from './SettingsCard';

export default function Privacy( { onExport, onClear, exporting } ) {
	return (
		<SettingsCard title={ __( 'Privacy and your data', 'blog-rhythm' ) }>
			<Stack direction="column" gap="lg">
				<Text render={ <p /> } className="blog-rhythm-callout">
					{ __(
						"Your rhythm is private to your account. It's never counted in traffic, stats, or anything other people see. Admins can't see it either.",
						'blog-rhythm'
					) }
				</Text>
				<Stack direction="row" gap="md" align="center" wrap="wrap">
					<Button
						variant="outline"
						onClick={ onExport }
						loading={ exporting }
					>
						{ __( 'Export my data', 'blog-rhythm' ) }
					</Button>
					<AlertDialog.Root onConfirm={ onClear }>
						<AlertDialog.Trigger
							render={
								<Button
									variant="minimal"
									className="blog-rhythm-danger"
								/>
							}
						>
							{ __( 'Clear my history', 'blog-rhythm' ) }
						</AlertDialog.Trigger>
						<AlertDialog.Popup
							intent="irreversible"
							title={ __( 'Clear your history?', 'blog-rhythm' ) }
							description={ __(
								'Your rhythm starts fresh from today. Earlier post activity is hidden, not deleted, and your posts stay just as they are. Ideas and outlines you logged are removed.',
								'blog-rhythm'
							) }
							confirmButtonText={ __(
								'Clear my history',
								'blog-rhythm'
							) }
							cancelButtonText={ __( 'Cancel', 'blog-rhythm' ) }
						/>
					</AlertDialog.Root>
				</Stack>
			</Stack>
		</SettingsCard>
	);
}
