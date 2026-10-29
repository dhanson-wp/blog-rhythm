/**
 * The Blog Rhythm dashboard widget: fetches the rhythm and handles the log
 * button. Rendering lives in WidgetView, which the settings preview reuses.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';
import { __ } from '@wordpress/i18n';
import { getRhythm, logToday, undoToday } from '../shared/api';
import WidgetView from './WidgetView';
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

export default function Widget( { settingsUrl = '' } ) {
	const [ data, setData ] = useState( null );
	const [ failed, setFailed ] = useState( false );
	const [ saveFailed, setSaveFailed ] = useState( false );
	const [ logged, setLogged ] = useState( false );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( async () => {
		try {
			setData( await fetchRhythm() );
			setFailed( false );
		} catch {
			setFailed( true );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	// A failed log or undo keeps the rhythm on screen with an inline notice;
	// the "couldn't load" message is only for the first load.
	const act = async ( request, nextLogged, announcement ) => {
		setBusy( true );
		try {
			await request();
		} catch {
			setSaveFailed( true );
			setBusy( false );
			return;
		}
		setSaveFailed( false );
		setLogged( nextLogged );
		speak( announcement );
		try {
			setData( await fetchRhythm() );
		} catch {
			// The entry saved; keep showing what we have.
		}
		setBusy( false );
	};

	return (
		<WidgetView
			data={ data }
			failed={ failed }
			saveFailed={ saveFailed }
			settingsUrl={ settingsUrl }
			logged={ logged }
			busy={ busy }
			onLog={ () =>
				act( logToday, true, __( 'Logged for today.', 'blog-rhythm' ) )
			}
			onUndo={ () =>
				act(
					undoToday,
					false,
					__( "Removed today's entry.", 'blog-rhythm' )
				)
			}
		/>
	);
}
