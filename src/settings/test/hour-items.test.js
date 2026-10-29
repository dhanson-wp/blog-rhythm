/**
 * Reminder hour labels must not shift with the browser's time zone.
 *
 * @jest-environment <rootDir>/tests/js/denver-environment.js
 */
import { hourItems } from '../cards/Reminders';

describe( 'hourItems', () => {
	test( 'labels each hour as stored, whatever the browser time zone', () => {
		expect( new Date( 2026, 0, 1 ).getTimezoneOffset() ).toBe( 420 );
		const items = hourItems();
		expect( items ).toHaveLength( 24 );
		const label = ( hour ) =>
			items.find( ( item ) => item.value === String( hour ) ).label;
		expect( label( 9 ) ).toBe( '9:00 am' );
		expect( label( 15 ) ).toBe( '3:00 pm' );
		expect( label( 0 ) ).toBe( '12:00 am' );
	} );
} );
