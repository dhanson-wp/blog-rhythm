import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import Widget from '../Widget';
import * as api from '../../shared/api';

jest.mock( '../../shared/api' );
expect.extend( toHaveNoViolations );

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
		days: overrides.days || {},
		summary: {
			count: 0,
			target: 2,
			pace: 'weekly',
			period: 'week',
			resting: false,
			rest_until: null,
			...( overrides.summary || {} ),
		},
		today: '2026-10-27',
		note: overrides.note || null,
		week_start: 1,
	};
}

const states = {
	empty: [
		payload( { settings: { pace: 'daily', target: 3 }, summary: { target: 3, pace: 'daily' } } ),
		'Set a pace that suits you',
	],
	'normal with days': [
		payload( {
			settings: { look: 'dots' },
			days: {
				'2026-10-26': 'published',
				'2026-10-24': 'drafted',
				'2026-10-20': 'idea',
			},
			summary: { count: 3 },
		} ),
		/3 days this week\./,
	],
	'weeks look': [
		payload( { settings: { look: 'weeks' }, days: { '2026-10-26': 'published' }, summary: { count: 1 } } ),
		/1 day this week\./,
	],
	'months look': [
		payload( { settings: { look: 'months' }, days: { '2026-10-26': 'published' }, summary: { count: 1 } } ),
		/1 day this week\./,
	],
	resting: [
		payload( {
			settings: { resting: true, rest_until: '2026-11-15' },
			summary: { resting: true, rest_until: '2026-11-15' },
		} ),
		/Resting until Nov 15\./,
	],
	'with a note': [
		payload( {
			days: { '2026-10-26': 'published' },
			note: 'Your rhythm is here whenever you are.',
		} ),
		'Your rhythm is here whenever you are.',
	],
};

beforeEach( () => {
	jest.resetAllMocks();
} );

describe( 'Widget accessibility (axe)', () => {
	test.each( Object.keys( states ) )( 'has no violations: %s', async ( name ) => {
		api.getRhythm.mockResolvedValue( states[ name ][ 0 ] );
		const { container } = render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findAllByText( states[ name ][ 1 ] );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	test( 'has no violations: error', async () => {
		api.getRhythm.mockRejectedValue( new Error( 'nope' ) );
		const { container } = render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findByText( /couldn't load right now/ );
		expect( await axe( container ) ).toHaveNoViolations();
	} );

	test( 'visualization hidden list is present and readable', async () => {
		api.getRhythm.mockResolvedValue( states[ 'normal with days' ][ 0 ] );
		const { container } = render( <Widget settingsUrl={ SETTINGS_URL } /> );
		await screen.findByRole( 'list', { name: 'Days you showed up' } );
		const list = screen.getByRole( 'list', { name: 'Days you showed up' } );
		expect( list.querySelectorAll( 'li' ).length ).toBe( 3 );
		list.querySelectorAll( 'li' ).forEach( ( li ) => {
			expect( li.textContent.trim().length ).toBeGreaterThan( 0 );
		} );
		// The drawn grid is decorative.
		expect( container.querySelector( '.blog-rhythm-grid' ).closest( '[aria-hidden="true"]' ) ).not.toBeNull();
	} );
} );
