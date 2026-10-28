/**
 * The presentational Blog Rhythm widget. It renders one rhythm payload and
 * calls back for the log button, so the dashboard and the settings preview
 * draw exactly the same thing.
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	ButtonLink,
	Notice,
	Skeleton,
	VisuallyHidden,
} from '@wordpress/ui';
import { summaryParts } from '../shared/layout';
import Visualization from '../shared/Visualization';

function Loading() {
	return (
		<div className="blog-rhythm-skeleton" aria-busy="true">
			<VisuallyHidden>
				{ __( 'Loading your rhythm', 'blog-rhythm' ) }
			</VisuallyHidden>
			<Skeleton style={ { height: 14, width: '60%' } } />
			<Skeleton style={ { height: 90 } } />
			<Skeleton style={ { height: 14, width: '40%' } } />
		</div>
	);
}

function EmptyState( { settingsUrl } ) {
	return (
		<div className="blog-rhythm-empty">
			<div className="blog-rhythm-empty-art" aria-hidden="true">
				{ [ 0, 1, 2, 3, 4 ].map( ( i ) => (
					<i key={ i } />
				) ) }
			</div>
			<h3>{ __( 'Set a pace that suits you', 'blog-rhythm' ) }</h3>
			<p>
				{ __(
					"Choose how often you'd like to write, and what counts. No pressure, nothing public.",
					'blog-rhythm'
				) }
			</p>
			<ButtonLink href={ settingsUrl } variant="solid">
				{ __( 'Choose your pace', 'blog-rhythm' ) }
			</ButtonLink>
		</div>
	);
}

// New writers who haven't saved settings yet and have nothing to show.
function isNewWriter( data ) {
	const { configured, days, summary } = data;
	return (
		! configured &&
		! ( summary && summary.resting ) &&
		Object.keys( days || {} ).length === 0
	);
}

/**
 * @param {Object}     props
 * @param {?Object}    props.data        Rhythm payload, or null while loading.
 * @param {boolean}    props.failed      Whether the first load failed.
 * @param {boolean}    props.saveFailed  Whether the last log or undo failed.
 * @param {string}     props.settingsUrl Settings page URL for the empty state.
 * @param {boolean}    props.logged      Whether the writer just logged today.
 * @param {boolean}    props.busy        Whether a log request is running.
 * @param {() => void} props.onLog       Log today.
 * @param {() => void} props.onUndo      Undo today's log.
 * @param {boolean}    props.preview     Settings preview: no empty state, inert buttons.
 */
export default function WidgetView( {
	data = null,
	failed = false,
	saveFailed = false,
	settingsUrl = '',
	logged = false,
	busy = false,
	onLog = () => {},
	onUndo = () => {},
	preview = false,
} ) {
	let body;
	if ( failed ) {
		body = (
			<Notice.Root intent="neutral">
				<Notice.Description>
					{ __(
						"Blog Rhythm couldn't load right now. Try again in a moment.",
						'blog-rhythm'
					) }
				</Notice.Description>
			</Notice.Root>
		);
	} else if ( ! data ) {
		body = <Loading />;
	} else if ( ! preview && isNewWriter( data ) ) {
		body = <EmptyState settingsUrl={ settingsUrl } />;
	} else {
		const {
			settings,
			days,
			summary,
			today,
			note,
			week_start: weekStart,
		} = data;
		const resting = !! summary.resting;
		const showSummary = !! settings.show_summary || resting;
		const canLog =
			!! settings.show_log_button &&
			!! ( settings.counts && settings.counts.idea ) &&
			! resting;
		const hasLogged = logged || days[ today ] === 'idea';
		const { main, muted } = summaryParts( summary, settings );
		// While resting there are no targets to draw.
		const vizSettings = resting ? { ...settings, target: 0 } : settings;

		body = (
			<>
				{ settings.intention && (
					<p className="blog-rhythm-intention">
						{ settings.intention }
					</p>
				) }
				{ note && <p className="blog-rhythm-note">{ note }</p> }
				<div className="blog-rhythm-save-status" aria-live="polite">
					{ saveFailed && (
						<Notice.Root intent="neutral">
							<Notice.Description>
								{ __(
									"That didn't save. Try again in a moment.",
									'blog-rhythm'
								) }
							</Notice.Description>
						</Notice.Root>
					) }
				</div>
				<Visualization
					look={ settings.look }
					days={ days }
					today={ today }
					settings={ vizSettings }
					summary={ summary }
					weekStart={ weekStart }
					hideSummary={ showSummary }
				/>
				{ ( showSummary || canLog ) && (
					<div className="blog-rhythm-summary">
						{ showSummary ? (
							<p>
								{ main }
								{ muted && (
									<>
										{ ' ' }
										<span className="is-muted">
											{ muted }
										</span>
									</>
								) }
							</p>
						) : (
							<span />
						) }
						{ canLog && (
							<Button
								variant="outline"
								disabled={ busy || preview }
								onClick={ hasLogged ? onUndo : onLog }
								aria-label={
									hasLogged
										? __(
												"Undo today's entry",
												'blog-rhythm'
											)
										: undefined
								}
							>
								{ hasLogged
									? __( 'Undo', 'blog-rhythm' )
									: __( 'I showed up today', 'blog-rhythm' ) }
							</Button>
						) }
					</div>
				) }
			</>
		);
	}

	return <div className="blog-rhythm-widget">{ body }</div>;
}
