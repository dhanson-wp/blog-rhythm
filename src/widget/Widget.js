/**
 * The Blog Rhythm dashboard widget: fetches the rhythm and handles the log
 * button. Rendering lives in WidgetView, which the settings preview reuses.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
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

	return (
		<WidgetView
			data={ data }
			failed={ failed }
			settingsUrl={ settingsUrl }
			logged={ logged }
			busy={ busy }
			onLog={ () => act( logToday, true ) }
			onUndo={ () => act( undoToday, false ) }
		/>
	);
}
