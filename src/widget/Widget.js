/**
 * The Blog Rhythm dashboard widget: loading, empty, resting, and normal
 * states, plus a quiet inline error.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, ButtonLink, Notice, Skeleton } from '@wordpress/ui';
import { getRhythm, logToday, undoToday } from '../shared/api';
import { summaryParts } from '../shared/layout';
import Visualization from '../shared/Visualization';
import './style.scss';

const DEFAULT_WEEKS = 26;
const MONTHLY_WEEKS = 53;

async function fetchRhythm() {
	const first = await getRhythm( DEFAULT_WEEKS );
	if ( first && first.settings && first.settings.pace === 'monthly' ) {
		return getRhythm( MONTHLY_WEEKS );
	}
	return first;
}

function Loading() {
	return (
		<div className="blog-rhythm-skeleton" aria-busy="true">
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

function isDefaultSetup( data ) {
	const { settings, days, summary } = data;
	return (
		settings.pace === 'daily' &&
		Number( settings.target ) === 3 &&
		! summary.resting &&
		Object.keys( days || {} ).length === 0
	);
}

export default function Widget( { settingsUrl = '' } ) {
	const [ data, setData ] = useState( null );
	const [ failed, setFailed ] = useState( false );
	const [ logged, setLogged ] = useState( false );
	const [ busy, setBusy ] = useState( false );

	const refresh = useCallback( async () => {
		try {
			setData( await fetchRhythm() );
			setFailed( false );
		} catch ( e ) {
			setFailed( true );
		}
	}, [] );

	useEffect( () => {
		refresh();
	}, [ refresh ] );

	const act = async ( request, nextLogged ) => {
		setBusy( true );
		try {
			await request();
			setLogged( nextLogged );
			await refresh();
		} catch ( e ) {
			setFailed( true );
		}
		setBusy( false );
	};

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
	} else if ( isDefaultSetup( data ) ) {
		body = <EmptyState settingsUrl={ settingsUrl } />;
	} else {
		const { settings, days, summary, today, note, week_start: weekStart } = data;
		const resting = !! summary.resting;
		const showSummary = !! settings.show_summary || resting;
		const canLog =
			!! settings.show_log_button && !! ( settings.counts && settings.counts.idea ) && ! resting;
		const hasLogged = logged || days[ today ] === 'idea';
		const { main, muted } = summaryParts( summary, settings );
		// While resting there are no targets to draw.
		const vizSettings = resting ? { ...settings, target: 0 } : settings;

		body = (
			<>
				{ settings.intention && (
					<p className="blog-rhythm-intention">{ settings.intention }</p>
				) }
				{ note && <p className="blog-rhythm-note">{ note }</p> }
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
										<span className="is-muted">{ muted }</span>
									</>
								) }
							</p>
						) : (
							<span />
						) }
						{ canLog && (
							<Button
								variant="outline"
								disabled={ busy }
								onClick={ () =>
									hasLogged ? act( undoToday, false ) : act( logToday, true )
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
