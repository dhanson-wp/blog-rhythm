import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import SettingsApp from '../SettingsApp';
import { previewData } from '../form';
import * as api from '../../shared/api';

jest.mock( '../../shared/api' );

const MESSAGE = 'Your rhythm is here whenever you are. Want to write a little?';

const stored = ( overrides = {} ) => ( {
	pace: 'daily',
	target: 3,
	counts: { publish: true, draft: true, idea: true, edit: false },
	intention: 'Write a little, most days.',
	resting: false,
	rest_until: '',
	reminders: {
		enabled: false,
		day: 'mon',
		hour: 9,
		email: true,
		widget_note: false,
		skip_if_done: true,
		message: MESSAGE,
	},
	look: 'auto',
	show_summary: true,
	show_log_button: true,
	...overrides,
} );

const rhythm = ( overrides = {} ) => ( {
	settings: stored(),
	days: { '2026-10-26': 'published', '2026-10-01': 'drafted' },
	summary: {
		count: 1,
		target: 3,
		pace: 'daily',
		period: 'week',
		resting: false,
		rest_until: '',
	},
	today: '2026-10-27',
	note: null,
	week_start: 1,
	...overrides,
} );

async function setup( settings = stored(), payload = rhythm() ) {
	api.getSettings.mockResolvedValue( settings );
	api.getRhythm.mockResolvedValue( payload );
	render( <SettingsApp timezone="America/New_York" email="writer@example.test" /> );
	await screen.findByRole( 'radio', { name: 'Most days' } );
	return screen.getByRole( 'complementary', { name: 'Live preview of your dashboard widget' } );
}

const targetInput = () => screen.queryByRole( 'spinbutton', { name: /Times I'd like to show up/ } );
const saveButton = () => screen.getAllByRole( 'button', { name: 'Save changes' } )[ 0 ];

beforeEach( () => {
	jest.resetAllMocks();
} );

describe( 'Settings page', () => {
	test( 'changing pace updates preview and default target', async () => {
		const preview = await setup();
		expect( within( preview ).getByText( /Your pace: 3 a week\./ ) ).toBeTruthy();

		fireEvent.click( screen.getByRole( 'radio', { name: 'Every month' } ) );
		expect( targetInput().value ).toBe( '4' );
		expect( screen.getByText( 'a month' ) ).toBeTruthy();
		expect( within( preview ).getByText( /Your pace: 4 a month\./ ) ).toBeTruthy();
		expect( within( preview ).getByText( /2 times this month\./ ) ).toBeTruthy();

		fireEvent.click( screen.getByRole( 'radio', { name: 'Every week' } ) );
		expect( targetInput().value ).toBe( '2' );
		expect( within( preview ).getByText( /Your pace: 2 a week\./ ) ).toBeTruthy();

		fireEvent.click( screen.getByRole( 'radio', { name: 'Most days' } ) );
		expect( targetInput().value ).toBe( '3' );
	} );

	test( 'no target field when pace is open', async () => {
		const preview = await setup();
		expect( targetInput() ).toBeTruthy();
		fireEvent.click( screen.getByRole( 'radio', { name: 'No target, just show me' } ) );
		expect( targetInput() ).toBeNull();
		expect( within( preview ).queryByText( /Your pace/ ) ).toBeNull();
		expect( within( preview ).getByText( /1 day this week\./ ) ).toBeTruthy();
	} );

	test( 'target input rejects out-of-range values client-side', async () => {
		await setup();
		fireEvent.change( targetInput(), { target: { value: '9' } } );
		expect( screen.getByText( 'Choose a number from 1 to 7.' ) ).toBeTruthy();
		expect( targetInput().getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		fireEvent.click( saveButton() );
		expect( api.saveSettings ).not.toHaveBeenCalled();

		fireEvent.click( screen.getByRole( 'radio', { name: 'Every month' } ) );
		fireEvent.change( targetInput(), { target: { value: '31' } } );
		expect( screen.queryByText( /Choose a number/ ) ).toBeNull();
		fireEvent.change( targetInput(), { target: { value: '0' } } );
		expect( screen.getByText( 'Choose a number from 1 to 31.' ) ).toBeTruthy();
		fireEvent.change( targetInput(), { target: { value: 'abc' } } );
		expect( screen.getByText( 'Choose a number from 1 to 31.' ) ).toBeTruthy();
	} );

	test( 'reminders options appear only when switched on', async () => {
		await setup();
		expect( screen.queryByRole( 'textbox', { name: 'What it says' } ) ).toBeNull();
		expect( screen.queryByText( /America\/New_York/ ) ).toBeNull();

		fireEvent.click( screen.getByRole( 'switch', { name: /Remind me to write/ } ) );
		expect( screen.getByRole( 'textbox', { name: 'What it says' } ).value ).toBe( MESSAGE );
		expect( screen.getByText( /In your site's time zone \(America\/New_York\)\./ ) ).toBeTruthy();
		expect( screen.getByText( /approximate/ ) ).toBeTruthy();
		expect( screen.getByText( /able to send email/ ) ).toBeTruthy();
		expect( screen.getByRole( 'checkbox', { name: 'Email me at writer@example.test' } ) ).toBeTruthy();

		fireEvent.click( screen.getByRole( 'switch', { name: /Remind me to write/ } ) );
		expect( screen.queryByRole( 'textbox', { name: 'What it says' } ) ).toBeNull();
	} );

	test( 'resting hides reminders note and shows return date', async () => {
		const settings = stored( {
			rest_until: '2026-11-15',
			reminders: { ...stored().reminders, enabled: true, widget_note: true },
		} );
		const preview = await setup( settings, rhythm( { settings, note: MESSAGE } ) );
		expect( within( preview ).getByText( MESSAGE ) ).toBeTruthy();
		expect( screen.queryByLabelText( 'Until' ) ).toBeNull();

		fireEvent.click( screen.getByRole( 'switch', { name: /Rest for a while/ } ) );
		expect( screen.getByLabelText( 'Until' ).value ).toBe( '2026-11-15' );
		expect( within( preview ).queryByText( MESSAGE ) ).toBeNull();
		expect( within( preview ).getByText( /Resting until Nov 15\./ ) ).toBeTruthy();
		expect( within( preview ).queryByRole( 'button', { name: 'I showed up today' } ) ).toBeNull();
	} );

	test( 'save sends sanitized payload and shows a success Notice', async () => {
		await setup();
		api.saveSettings.mockImplementation( async ( data ) => ( { ...stored(), ...data } ) );

		fireEvent.click( screen.getByRole( 'radio', { name: 'Every week' } ) );
		fireEvent.change( targetInput(), { target: { value: '5' } } );
		fireEvent.change( screen.getByRole( 'textbox', { name: 'What are you doing this for?' } ), {
			target: { value: '  Two posts a week.  ' },
		} );
		fireEvent.click( screen.getByRole( 'checkbox', { name: 'Updating an older post' } ) );

		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( api.saveSettings ).toHaveBeenCalledTimes( 1 );
		const sent = api.saveSettings.mock.calls[ 0 ][ 0 ];
		expect( Object.keys( sent ).sort() ).toEqual(
			[
				'counts',
				'intention',
				'look',
				'pace',
				'reminders',
				'rest_until',
				'resting',
				'show_log_button',
				'show_summary',
				'target',
			].sort()
		);
		expect( sent.pace ).toBe( 'weekly' );
		expect( sent.target ).toBe( 5 );
		expect( sent.intention ).toBe( 'Two posts a week.' );
		expect( sent.counts ).toEqual( { publish: true, draft: true, idea: true, edit: true } );
		expect( sent.reminders.hour ).toBe( 9 );
		expect( await screen.findByText( 'Settings saved.' ) ).toBeTruthy();
	} );

	test( 'server validation error shows inline and keeps form state', async () => {
		await setup();
		api.saveSettings.mockRejectedValue( {
			code: 'blog_rhythm_invalid_settings',
			message: 'Settings must be sent as an object.',
		} );
		const intention = screen.getByRole( 'textbox', { name: 'What are you doing this for?' } );
		fireEvent.change( intention, { target: { value: 'Keep this.' } } );

		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( await screen.findByText( 'Settings must be sent as an object.' ) ).toBeTruthy();
		expect( screen.queryByText( 'Settings saved.' ) ).toBeNull();
		expect(
			screen.getByRole( 'textbox', { name: 'What are you doing this for?' } ).value
		).toBe( 'Keep this.' );
	} );

	test( 'clear history asks for confirmation before calling the API', async () => {
		await setup();
		api.clearHistory.mockResolvedValue( rhythm( { days: {} } ) );

		expect( screen.getByText( /There's no admin screen for it, either\./ ) ).toBeTruthy();
		expect( screen.queryByText( /Admins can't see it/ ) ).toBeNull();

		fireEvent.click( screen.getByRole( 'button', { name: 'Clear my history' } ) );
		const dialog = await screen.findByRole( 'alertdialog' );
		expect( within( dialog ).getByText( /hidden, not deleted/ ) ).toBeTruthy();
		expect( api.clearHistory ).not.toHaveBeenCalled();

		await act( async () => {
			fireEvent.click( within( dialog ).getByRole( 'button', { name: 'Clear my history' } ) );
		} );
		await waitFor( () => expect( api.clearHistory ).toHaveBeenCalledTimes( 1 ) );
	} );

	test( 'export triggers a JSON download', async () => {
		await setup();
		const data = { settings: stored(), activity: {} };
		api.exportData.mockResolvedValue( data );

		const createObjectURL = jest.fn( () => 'blob:export' );
		const revokeObjectURL = jest.fn();
		global.URL.createObjectURL = createObjectURL;
		global.URL.revokeObjectURL = revokeObjectURL;
		let clicked = null;
		const click = jest
			.spyOn( HTMLAnchorElement.prototype, 'click' )
			.mockImplementation( function () {
				clicked = { download: this.download, href: this.href };
			} );

		await act( async () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Export my data' } ) );
		} );

		await waitFor( () => expect( click ).toHaveBeenCalled() );
		expect( api.exportData ).toHaveBeenCalledTimes( 1 );
		expect( clicked.download ).toBe( 'blog-rhythm-export-2026-10-27.json' );
		expect( clicked.href ).toBe( 'blob:export' );
		const blob = createObjectURL.mock.calls[ 0 ][ 0 ];
		expect( blob.type ).toBe( 'application/json' );
		const text = await new Promise( ( resolve ) => {
			const reader = new FileReader();
			reader.onload = () => resolve( reader.result );
			reader.readAsText( blob );
		} );
		expect( JSON.parse( text ) ).toEqual( data );
		click.mockRestore();
	} );
	test( 'unchecking ideas explains and disables the log button option', async () => {
		await setup();
		const reason =
			'The "I showed up today" button only appears when ideas and outlines count.';
		const logOption = () =>
			screen.getByRole( 'checkbox', { name: 'Show "I showed up today"' } );
		expect( screen.queryAllByText( reason ) ).toHaveLength( 0 );
		const isDisabled = ( el ) =>
			el.getAttribute( 'aria-disabled' ) === 'true' || el.disabled === true;
		expect( isDisabled( logOption() ) ).toBe( false );

		fireEvent.click( screen.getByRole( 'checkbox', { name: /Ideas and outlines/ } ) );
		expect( screen.getAllByText( reason ) ).toHaveLength( 2 );
		expect( isDisabled( logOption() ) ).toBe( true );
	} );

	test( 'preview never shows the empty state', () => {
		expect( previewData( stored(), rhythm( { days: {} } ) ).configured ).toBe( true );
	} );
} );
