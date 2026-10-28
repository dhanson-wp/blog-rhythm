import {
	buildDotColumns,
	buildWeekStack,
	buildMonthBlocks,
	describeRhythm,
	resolveLook,
} from '../layout';

// 2026-10-27 is a Tuesday.
const TODAY = '2026-10-27';

describe( 'buildDotColumns', () => {
	test( 'pads first column and marks today', () => {
		const cols = buildDotColumns( { [ TODAY ]: 'published' }, TODAY, 4, 0 );
		const flat = cols.columns.flat();

		// Every column is a full week of 7 slots.
		cols.columns.forEach( ( col ) => expect( col ).toHaveLength( 7 ) );

		// 4 weeks = 28 days ending today; 2026-09-30 is a Wednesday, so with
		// a Sunday start the first column is padded with 3 empty slots.
		expect( cols.columns[ 0 ].slice( 0, 3 ) ).toEqual( [
			null,
			null,
			null,
		] );
		expect( cols.columns[ 0 ][ 3 ].date ).toBe( '2026-09-30' );

		const todays = flat.filter( ( c ) => c && c.isToday );
		expect( todays ).toHaveLength( 1 );
		expect( todays[ 0 ] ).toMatchObject( {
			date: TODAY,
			type: 'published',
			isFuture: false,
		} );

		// Cells after today in the last column are future and empty.
		const future = flat.filter( ( c ) => c && c.isFuture );
		expect( future.map( ( c ) => c.date ) ).toEqual( [
			'2026-10-28',
			'2026-10-29',
			'2026-10-30',
			'2026-10-31',
		] );
	} );

	test( 'respects a Monday week start', () => {
		const cols = buildDotColumns( {}, TODAY, 4, 1 );
		// Wednesday start with Monday-first weeks pads 2 slots.
		expect( cols.columns[ 0 ].slice( 0, 2 ) ).toEqual( [ null, null ] );
		expect( cols.columns[ 0 ][ 2 ].date ).toBe( '2026-09-30' );
	} );

	test( 'month label appears once per month change and skips a crowded first label', () => {
		const cols = buildDotColumns( {}, TODAY, 26, 0 );
		const labels = cols.monthLabels.map( ( l ) => l.label );
		// Window starts 2026-04-29 (Wed); April has only 1 column, so its
		// label is skipped as crowded. Then one label per month change.
		expect( labels ).toEqual( [
			'May',
			'Jun',
			'Jul',
			'Aug',
			'Sep',
			'Oct',
		] );
		expect( new Set( labels ).size ).toBe( labels.length );
		cols.monthLabels.forEach( ( l ) => {
			expect( l.index ).toBeGreaterThan( 0 );
			expect( l.index ).toBeLessThan( cols.columns.length );
		} );
	} );

	test( 'keeps the first label when it has room', () => {
		const cols = buildDotColumns( {}, TODAY, 8, 0 );
		// Window starts 2026-09-02; September spans several columns.
		expect( cols.monthLabels[ 0 ] ).toEqual( { index: 0, label: 'Sep' } );
	} );

	test( 'empty days map yields all-empty grid without throwing', () => {
		const cols = buildDotColumns( {}, TODAY, 26, 0 );
		const real = cols.columns.flat().filter( Boolean );
		expect( real.length ).toBeGreaterThan( 0 );
		expect( real.every( ( c ) => c.type === null ) ).toBe( true );
		expect( () => buildWeekStack( {}, TODAY, 3 ) ).not.toThrow();
		expect( () => buildMonthBlocks( {}, TODAY ) ).not.toThrow();
		expect(
			buildMonthBlocks( {}, TODAY ).every( ( m ) => m.shade === 'none' )
		).toBe( true );
		expect(
			buildWeekStack( {}, TODAY, 3 ).every( ( w ) => w.count === 0 )
		).toBe( true );
	} );
} );

describe( 'buildWeekStack', () => {
	test( 'never exceeds slot count when over target', () => {
		const days = {};
		// Show up all 7 days of the previous Sun-Sat week; target is 2.
		for ( let i = 18; i <= 24; i++ ) {
			days[ `2026-10-${ i }` ] = 'drafted';
		}
		const weeks = buildWeekStack( days, TODAY, 2 );
		expect( weeks ).toHaveLength( 12 );
		const prev = weeks[ weeks.length - 2 ];
		expect( prev.slots ).toBe( 2 );
		expect( prev.count ).toBe( 2 );
		expect( prev.actual ).toBe( 7 );
	} );

	test( 'newest stack matches the calendar-week count', () => {
		// Tuesday Oct 27: Sun/Mon/Tue this week, Fri/Sat last week.
		const days = {
			'2026-10-25': 'published',
			'2026-10-26': 'drafted',
			'2026-10-27': 'idea',
			'2026-10-23': 'published',
			'2026-10-24': 'published',
		};
		const weeks = buildWeekStack( days, TODAY, 5, 12, 0 );
		const newest = weeks[ weeks.length - 1 ];
		expect( newest.start ).toBe( '2026-10-25' );
		expect( newest.count ).toBe( 3 );
		expect( weeks[ weeks.length - 2 ].count ).toBe( 2 );
		// Monday-first weeks: Mon/Tue this week only.
		const monday = buildWeekStack( days, TODAY, 5, 12, 1 );
		expect( monday[ monday.length - 1 ].count ).toBe( 2 );
		expect( monday[ monday.length - 1 ].start ).toBe( '2026-10-26' );
	} );

	test( 'uses at least two slots', () => {
		expect( buildWeekStack( {}, TODAY, 0 )[ 0 ].slots ).toBe( 2 );
		expect( buildWeekStack( {}, TODAY, 1 )[ 0 ].slots ).toBe( 2 );
		expect( buildWeekStack( {}, TODAY, 5 )[ 0 ].slots ).toBe( 5 );
	} );
} );

describe( 'buildMonthBlocks', () => {
	test( 'shades by thresholds', () => {
		const days = {};
		const put = ( month, n ) => {
			for ( let d = 1; d <= n; d++ ) {
				days[ `2026-${ month }-${ String( d ).padStart( 2, '0' ) }` ] =
					'idea';
			}
		};
		put( '10', 9 ); // strong
		put( '09', 8 ); // mid
		put( '08', 4 ); // mid
		put( '07', 3 ); // light
		put( '06', 1 ); // light
		const blocks = buildMonthBlocks( days, TODAY, 6 );
		expect( blocks.map( ( b ) => b.key ) ).toEqual( [
			'2026-05',
			'2026-06',
			'2026-07',
			'2026-08',
			'2026-09',
			'2026-10',
		] );
		expect( blocks.map( ( b ) => b.shade ) ).toEqual( [
			'none',
			'light',
			'light',
			'mid',
			'mid',
			'strong',
		] );
		expect( blocks[ 5 ].count ).toBe( 9 );
	} );
} );

describe( 'describeRhythm', () => {
	const settings = { pace: 'weekly', target: 3 };

	test( 'reads as a plain sentence', () => {
		expect(
			describeRhythm(
				{
					count: 3,
					target: 3,
					pace: 'weekly',
					period: 'week',
					resting: false,
					rest_until: null,
				},
				settings
			)
		).toBe( '3 days this week. Your pace: 3 a week.' );
		expect(
			describeRhythm(
				{
					count: 1,
					target: 3,
					pace: 'weekly',
					period: 'week',
					resting: false,
					rest_until: null,
				},
				settings
			)
		).toBe( '1 day this week. Your pace: 3 a week.' );
	} );

	test( 'never contains missed, behind, streak', () => {
		const variants = [
			[
				{ count: 0, target: 3, pace: 'weekly', period: 'week' },
				settings,
			],
			[
				{ count: 9, target: 3, pace: 'weekly', period: 'week' },
				settings,
			],
			[
				{ count: 0, target: 0, pace: 'open', period: 'week' },
				{ pace: 'open', target: 0 },
			],
			[
				{ count: 0, target: 4, pace: 'monthly', period: 'month' },
				{ pace: 'monthly', target: 4 },
			],
			[
				{ count: 2, target: 4, pace: 'monthly', period: 'month' },
				{ pace: 'monthly', target: 4 },
			],
			[
				{
					count: 0,
					target: 3,
					pace: 'daily',
					period: 'week',
					resting: true,
					rest_until: '2026-11-15',
				},
				settings,
			],
			[
				{
					count: 0,
					target: 3,
					pace: 'daily',
					period: 'week',
					resting: true,
					rest_until: null,
				},
				settings,
			],
		];
		variants.forEach( ( [ summary, s ] ) => {
			const text = describeRhythm( summary, s );
			expect( typeof text ).toBe( 'string' );
			expect( text.length ).toBeGreaterThan( 0 );
			expect( text ).not.toMatch( /missed|behind|streak/i );
		} );
	} );

	test( 'quiet week variant', () => {
		expect(
			describeRhythm(
				{ count: 0, target: 3, pace: 'weekly', period: 'week' },
				settings
			)
		).toBe( "A quiet week so far. That's part of it." );
	} );

	test( 'monthly variant', () => {
		expect(
			describeRhythm(
				{ count: 2, target: 4, pace: 'monthly', period: 'month' },
				{ pace: 'monthly', target: 4 }
			)
		).toBe( '2 times this month. Your pace: 4 a month.' );
	} );

	test( 'open pace has no target phrase', () => {
		expect(
			describeRhythm(
				{ count: 2, target: 0, pace: 'open', period: 'week' },
				{ pace: 'open', target: 0 }
			)
		).toBe( '2 days this week.' );
	} );

	test( 'resting variant', () => {
		expect(
			describeRhythm(
				{
					count: 0,
					target: 3,
					pace: 'daily',
					period: 'week',
					resting: true,
					rest_until: '2026-11-15',
				},
				settings
			)
		).toBe( 'Resting until Nov 15. Welcome back whenever.' );
		expect(
			describeRhythm(
				{
					count: 0,
					target: 3,
					pace: 'daily',
					period: 'week',
					resting: true,
					rest_until: null,
				},
				settings
			)
		).toBe( 'Resting for a while. Welcome back whenever.' );
	} );
} );

describe( 'resolveLook', () => {
	test( 'auto follows pace; explicit look wins', () => {
		expect( resolveLook( 'auto', 'daily' ) ).toBe( 'dots' );
		expect( resolveLook( 'auto', 'open' ) ).toBe( 'dots' );
		expect( resolveLook( 'auto', 'weekly' ) ).toBe( 'weeks' );
		expect( resolveLook( 'auto', 'monthly' ) ).toBe( 'months' );
		expect( resolveLook( 'dots', 'monthly' ) ).toBe( 'dots' );
		expect( resolveLook( 'weeks', 'daily' ) ).toBe( 'weeks' );
	} );
} );
