import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import Widget from '../Widget';
import * as api from '../../shared/api';

jest.mock( '../../shared/api' );

const SETTINGS_URL = 'http://example.test/wp-admin/edit.php?page=blog-rhythm';

const baseSettings = {
	pace: 'weekly',
	target: 2,
	look: 'auto',
	intention: 'Two posts a week.',
	resting: false,
	rest_until: '',
	counts: { publish: true, draft: true, idea: true, edit: false },
	show_summary: true,
	show_log_button: true,
};

function payload( overrides = {} ) {
	return {
		settings: { ...baseSettings, ...( overrides.settings || {} ) },
		days: overrides.days || { '2026-10-26': 'published' },
		summary: {
			count: 1,
			target: 2,
			pace: 'weekly',
			period: 'week',
			resting: false,
			rest_until: null,
			...( overrides.summary || {} ),
		},
		today: '2026-10-27',
		note: overrides.note === undefined ? null : overrides.note,
		week_start: 1,
	};
}

beforeEach( () => {
	jest.resetAllMocks();
} );

describe( 'Widget', () => {
	test( 'renders empty state for new user', async () => {
		api.getRhythm.mockResolvedValue(
			payload( {
				settings: { pace: 'daily', target: 3 },
				days: {},
				summary: { count: 0, target: 3, pace: 'daily' },
			} )
		);
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		expect( await screen.findByText( 'Set a pace that suits you' ) ).toBeTruthy();
		const link = screen.getByRole( 'link', { name: 'Choose your pace' } );
		expect( link.getAttribute( 'href' ) ).toBe( SETTINGS_URL );
		expect( screen.queryByRole( 'button', { name: 'I showed up today' } ) ).toBeNull();
	} );

	test( 'renders resting copy and no log button when resting', async () => {
		api.getRhythm.mockResolvedValue(
			payload( {
				settings: { resting: true, rest_until: '2026-11-15' },
				summary: { resting: true, rest_until: '2026-11-15' },
			} )
		);
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		expect( await screen.findByText( /Resting until Nov 15\./ ) ).toBeTruthy();
		expect( screen.queryByRole( 'button', { name: 'I showed up today' } ) ).toBeNull();
		expect( screen.queryByText( /Your pace/ ) ).toBeNull();
	} );

	test( 'hides intention when empty and shows it otherwise', async () => {
		api.getRhythm.mockResolvedValue( payload( { settings: { intention: '' } } ) );
		const { unmount } = render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findByText( /1 day this week\./ );
		expect( screen.queryByText( 'Two posts a week.' ) ).toBeNull();
		unmount();

		api.getRhythm.mockResolvedValue( payload() );
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		expect( await screen.findByText( 'Two posts a week.' ) ).toBeTruthy();
	} );

	test( 'log button calls logToday then refreshes and offers undo', async () => {
		api.getRhythm
			.mockResolvedValueOnce( payload() )
			.mockResolvedValueOnce( payload( { days: { '2026-10-26': 'published', '2026-10-27': 'idea' } } ) );
		api.logToday.mockResolvedValue( payload() );
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		fireEvent.click( await screen.findByRole( 'button', { name: 'I showed up today' } ) );
		expect( api.logToday ).toHaveBeenCalledTimes( 1 );
		expect( await screen.findByRole( 'button', { name: 'Undo' } ) ).toBeTruthy();
		expect( api.getRhythm ).toHaveBeenCalledTimes( 2 );
		expect( screen.queryByRole( 'button', { name: 'I showed up today' } ) ).toBeNull();
	} );

	test( 'undo calls undoToday then restores the log button', async () => {
		api.getRhythm
			.mockResolvedValueOnce( payload( { days: { '2026-10-27': 'idea' } } ) )
			.mockResolvedValueOnce( payload( { days: {} } ) );
		api.undoToday.mockResolvedValue( payload( { days: {} } ) );
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Undo' } ) );
		expect( api.undoToday ).toHaveBeenCalledTimes( 1 );
		expect( await screen.findByRole( 'button', { name: 'I showed up today' } ) ).toBeTruthy();
	} );

	test( 'log button is hidden when ideas do not count', async () => {
		api.getRhythm.mockResolvedValue(
			payload( { settings: { counts: { publish: true, draft: true, idea: false, edit: false } } } )
		);
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findByText( /1 day this week\./ );
		expect( screen.queryByRole( 'button', { name: 'I showed up today' } ) ).toBeNull();
	} );

	test( 'shows note only when API returns one', async () => {
		api.getRhythm.mockResolvedValue( payload( { note: 'Your rhythm is here whenever you are.' } ) );
		const { unmount } = render( <Widget settingsUrl={ SETTINGS_URL } /> );
		expect( await screen.findByText( 'Your rhythm is here whenever you are.' ) ).toBeTruthy();
		unmount();

		api.getRhythm.mockResolvedValue( payload() );
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findByText( /1 day this week\./ );
		expect( screen.queryByText( 'Your rhythm is here whenever you are.' ) ).toBeNull();
	} );

	test( 'requests 26 weeks, and 53 for monthly pace', async () => {
		api.getRhythm.mockResolvedValue( payload() );
		const { unmount } = render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findByText( /1 day this week\./ );
		expect( api.getRhythm ).toHaveBeenCalledWith( 26 );
		unmount();

		api.getRhythm.mockClear();
		api.getRhythm.mockResolvedValue(
			payload( { settings: { pace: 'monthly' }, summary: { pace: 'monthly', period: 'month' } } )
		);
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await waitFor( () => expect( api.getRhythm ).toHaveBeenCalledWith( 53 ) );
	} );

	test( 'error from API shows a quiet inline Notice, not a crash', async () => {
		api.getRhythm.mockRejectedValue( new Error( 'nope' ) );
		render( <Widget settingsUrl={ SETTINGS_URL } /> );
		expect(
			await screen.findByText( "Blog Rhythm couldn't load right now. Try again in a moment." )
		).toBeTruthy();
	} );
} );
