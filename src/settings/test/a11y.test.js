import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import SettingsApp from '../SettingsApp';
import * as api from '../../shared/api';

jest.mock( '../../shared/api' );
expect.extend( toHaveNoViolations );

const settings = {
	pace: 'daily',
	target: 3,
	counts: { publish: true, draft: true, idea: true, edit: false },
	intention: 'Write a little, most days.',
	resting: false,
	rest_until: '',
	reminders: {
		enabled: true,
		day: 'mon',
		hour: 9,
		email: true,
		widget_note: true,
		skip_if_done: true,
		message: 'Your rhythm is here whenever you are.',
	},
	look: 'auto',
	show_summary: true,
	show_log_button: true,
};

describe( 'Settings page accessibility (axe)', () => {
	test( 'has no violations', async () => {
		api.getSettings.mockResolvedValue( settings );
		api.getRhythm.mockResolvedValue( {
			settings,
			days: { '2026-10-26': 'published', '2026-10-01': 'drafted' },
			summary: { count: 1, target: 3, pace: 'daily', period: 'week', resting: false, rest_until: '' },
			today: '2026-10-27',
			note: null,
			week_start: 1,
		} );
		const { container } = render(
			<SettingsApp timezone="America/New_York" email="writer@example.test" />
		);
		await screen.findByRole( 'radio', { name: 'Most days' } );
		expect( await axe( container ) ).toHaveNoViolations();
	} );
} );
