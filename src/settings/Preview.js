/**
 * Sticky live preview: the real dashboard widget, fed from unsaved form state.
 * Its buttons are disabled, so it adds no tab stops and never traps focus.
 */
import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Text } from '@wordpress/ui';
import WidgetView from '../widget/WidgetView';

export default function Preview( { data } ) {
	const labelId = useId();
	return (
		<aside className="blog-rhythm-preview" aria-labelledby={ labelId }>
			<Text
				render={ <h2 /> }
				id={ labelId }
				variant="body-sm"
				className="blog-rhythm-preview-label"
			>
				{ __( 'Live preview of your dashboard widget', 'blog-rhythm' ) }
			</Text>
			<div className="postbox blog-rhythm-preview-box">
				<div className="postbox-header">
					<h3 className="hndle">
						{ __( 'Blog Rhythm', 'blog-rhythm' ) }
					</h3>
				</div>
				<div className="inside">
					<WidgetView data={ data } preview />
				</div>
			</div>
		</aside>
	);
}
