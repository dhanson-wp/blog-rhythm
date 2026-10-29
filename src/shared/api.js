import apiFetch from '@wordpress/api-fetch';

const NS = '/blog-rhythm/v1';

export function getRhythm( weeks = 26 ) {
	return apiFetch( {
		path: `${ NS }/rhythm?weeks=${ encodeURIComponent( Number( weeks ) || 26 ) }`,
	} );
}

export function getSettings() {
	return apiFetch( { path: `${ NS }/settings` } );
}

export function saveSettings( settings ) {
	return apiFetch( {
		path: `${ NS }/settings`,
		method: 'POST',
		data: settings,
	} );
}

export function logToday() {
	return apiFetch( { path: `${ NS }/log`, method: 'POST' } );
}

export function undoToday() {
	return apiFetch( { path: `${ NS }/log`, method: 'DELETE' } );
}

export function clearHistory() {
	return apiFetch( { path: `${ NS }/history/clear`, method: 'POST' } );
}

export function exportData() {
	return apiFetch( { path: `${ NS }/export` } );
}
