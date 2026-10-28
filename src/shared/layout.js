/**
 * Pure layout builders for the Blog Rhythm visuals.
 *
 * Everything here takes ISO `Y-m-d` strings and the `days` map from
 * `GET /blog-rhythm/v1/rhythm`. Dates are handled as UTC midnights so a
 * daylight saving change can never shift a cell to a neighboring day.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

const DAY_MS = 86400000;

function toMs( ymd ) {
	const [ y, m, d ] = ymd.split( '-' ).map( Number );
	return Date.UTC( y, m - 1, d );
}

function fromMs( ms ) {
	return new Date( ms ).toISOString().slice( 0, 10 );
}

function addDays( ymd, n ) {
	return fromMs( toMs( ymd ) + n * DAY_MS );
}

function dayOfWeek( ymd ) {
	return new Date( toMs( ymd ) ).getUTCDay();
}

/**
 * Format a Y-m-d string for display, in the viewer's locale.
 *
 * @param {string} ymd    Date as Y-m-d.
 * @param {string} format PHP-style date format.
 * @return {string} Formatted date.
 */
export function formatDate( ymd, format = 'M j' ) {
	return dateI18n( format, `${ ymd }T12:00:00`, 'UTC' );
}

/**
 * Pick the visual: an explicit look wins; "auto" follows the pace.
 *
 * @param {string} look "auto", "dots" or "weeks".
 * @param {string} pace "daily", "weekly", "monthly" or "open".
 * @return {string} "dots", "weeks" or "months".
 */
export function resolveLook( look, pace ) {
	if ( look === 'dots' || look === 'weeks' ) {
		return look;
	}
	if ( pace === 'weekly' ) {
		return 'weeks';
	}
	if ( pace === 'monthly' ) {
		return 'months';
	}
	return 'dots';
}

/**
 * Calendar-style columns of 7 days, oldest first.
 *
 * The window is `weeks * 7` days ending today. The first column is padded
 * with nulls so rows line up with the site's first day of the week, and the
 * last column is completed with empty future cells.
 *
 * @param {Object} days      Map of Y-m-d to activity type.
 * @param {string} today     Today as Y-m-d.
 * @param {number} weeks     How many weeks of history.
 * @param {number} weekStart First day of the week, 0 (Sunday) to 6.
 * @return {{columns: Array, monthLabels: Array<{index: number, label: string}>}} Grid.
 */
export function buildDotColumns( days, today, weeks, weekStart = 0 ) {
	const map = days || {};
	const first = addDays( today, -( weeks * 7 - 1 ) );
	const pad = ( dayOfWeek( first ) - weekStart + 7 ) % 7;

	const cells = new Array( pad ).fill( null );
	for ( let i = 0; i < weeks * 7; i++ ) {
		const date = addDays( first, i );
		cells.push( {
			date,
			type: map[ date ] || null,
			isToday: date === today,
			isFuture: false,
		} );
	}
	while ( cells.length % 7 !== 0 ) {
		const date = addDays( cells[ cells.length - 1 ].date, 1 );
		cells.push( { date, type: null, isToday: false, isFuture: true } );
	}

	const columns = [];
	for ( let i = 0; i < cells.length; i += 7 ) {
		columns.push( cells.slice( i, i + 7 ) );
	}

	// One label where the month changes. The opening month is labeled only
	// when it has room (3+ columns) before the next label.
	const monthOf = ( col ) => col.find( Boolean ).date.slice( 0, 7 );
	const changes = [];
	columns.forEach( ( col, index ) => {
		if ( index > 0 && monthOf( col ) !== monthOf( columns[ index - 1 ] ) ) {
			changes.push( index );
		}
	} );
	const monthLabels = [];
	if ( changes.length === 0 || changes[ 0 ] >= 3 ) {
		monthLabels.push( {
			index: 0,
			label: formatDate( columns[ 0 ].find( Boolean ).date, 'M' ),
		} );
	}
	changes.forEach( ( index ) => {
		monthLabels.push( {
			index,
			label: formatDate( columns[ index ].find( Boolean ).date, 'M' ),
		} );
	} );

	return { columns, monthLabels };
}

/**
 * One stack per calendar week, oldest first, the last running from the start
 * of this week through today. This matches the server's week-aligned
 * `summary.count`.
 *
 * `count` is capped at `slots` so going over a target never looks different.
 * `actual` keeps the true number of days.
 *
 * @param {Object} days      Map of Y-m-d to activity type.
 * @param {string} today     Today as Y-m-d.
 * @param {number} target    Target per week (0 for none).
 * @param {number} weeks     How many weeks to show.
 * @param {number} weekStart First day of the week, 0 (Sunday) to 6.
 * @return {Array} Weeks.
 */
export function buildWeekStack(
	days,
	today,
	target,
	weeks = 12,
	weekStart = 0
) {
	const map = days || {};
	const slots = Math.max( Number( target ) || 0, 2 );
	const thisWeek = addDays(
		today,
		-( ( dayOfWeek( today ) - weekStart + 7 ) % 7 )
	);
	const result = [];
	for ( let w = weeks - 1; w >= 0; w-- ) {
		const start = addDays( thisWeek, -w * 7 );
		const last = w === 0 ? today : addDays( start, 6 );
		let actual = 0;
		for ( let d = start; d <= last; d = addDays( d, 1 ) ) {
			if ( map[ d ] ) {
				actual++;
			}
		}
		let label = '';
		if ( w === 0 ) {
			label = __( 'Now', 'blog-rhythm' );
		} else if ( w % 4 === 0 ) {
			label = formatDate( start, 'M j' );
		}
		result.push( {
			start,
			end: last,
			slots,
			actual,
			count: Math.min( actual, slots ),
			label,
		} );
	}
	return result;
}

/**
 * Shade for a month by days shown up: 0 none, 1-3 light, 4-8 mid, 9+ strong.
 *
 * @param {number} count Days shown up.
 * @return {string} "none", "light", "mid" or "strong".
 */
function shadeFor( count ) {
	if ( count >= 9 ) {
		return 'strong';
	}
	if ( count >= 4 ) {
		return 'mid';
	}
	return count >= 1 ? 'light' : 'none';
}

/**
 * Calendar months, oldest first, the last being the current month.
 *
 * @param {Object} days   Map of Y-m-d to activity type.
 * @param {string} today  Today as Y-m-d.
 * @param {number} months How many months to show.
 * @return {Array} Months.
 */
export function buildMonthBlocks( days, today, months = 12 ) {
	const map = days || {};
	const counts = {};
	Object.keys( map ).forEach( ( date ) => {
		if ( map[ date ] && date <= today ) {
			const key = date.slice( 0, 7 );
			counts[ key ] = ( counts[ key ] || 0 ) + 1;
		}
	} );

	const [ y, m ] = today.split( '-' ).map( Number );
	const result = [];
	for ( let i = months - 1; i >= 0; i-- ) {
		const d = new Date( Date.UTC( y, m - 1 - i, 1 ) );
		const key = d.toISOString().slice( 0, 7 );
		const count = counts[ key ] || 0;
		result.push( {
			key,
			label: formatDate( `${ key }-01`, 'M' ),
			count,
			shade: shadeFor( count ),
		} );
	}
	return result;
}

/**
 * The summary line as two parts: the main sentence and a quieter follow-up.
 *
 * @param {Object} summary  `summary` from the rhythm payload.
 * @param {Object} settings User settings.
 * @return {{main: string, muted: string}} Parts, muted may be empty.
 */
export function summaryParts( summary, settings ) {
	const s = summary || {};
	const pace = ( settings && settings.pace ) || s.pace;

	if ( s.resting ) {
		return s.rest_until
			? {
					main: sprintf(
						/* translators: %s: date such as Nov 15 */
						__( 'Resting until %s.', 'blog-rhythm' ),
						formatDate( s.rest_until )
					),
					muted: __( 'Welcome back whenever.', 'blog-rhythm' ),
				}
			: {
					main: __( 'Resting for a while.', 'blog-rhythm' ),
					muted: __( 'Welcome back whenever.', 'blog-rhythm' ),
				};
	}

	const monthly = s.period === 'month';
	const count = Number( s.count ) || 0;
	const target = Number( s.target ) || 0;

	if ( count === 0 ) {
		return {
			main: monthly
				? __( 'A quiet month so far.', 'blog-rhythm' )
				: __( 'A quiet week so far.', 'blog-rhythm' ),
			muted: __( "That's part of it.", 'blog-rhythm' ),
		};
	}

	const main = monthly
		? sprintf(
				/* translators: %d: number of times */
				_n(
					'%d time this month.',
					'%d times this month.',
					count,
					'blog-rhythm'
				),
				count
			)
		: sprintf(
				/* translators: %d: number of days */
				_n(
					'%d day this week.',
					'%d days this week.',
					count,
					'blog-rhythm'
				),
				count
			);

	if ( pace === 'open' || target < 1 ) {
		return { main, muted: '' };
	}

	return {
		main,
		muted: monthly
			? sprintf(
					/* translators: %d: target per month */
					__( 'Your pace: %d a month.', 'blog-rhythm' ),
					target
				)
			: sprintf(
					/* translators: %d: target per week */
					__( 'Your pace: %d a week.', 'blog-rhythm' ),
					target
				),
	};
}

/**
 * The summary as one plain sentence. Never uses negative wording.
 *
 * @param {Object} summary  `summary` from the rhythm payload.
 * @param {Object} settings User settings.
 * @return {string} Sentence.
 */
export function describeRhythm( summary, settings ) {
	const { main, muted } = summaryParts( summary, settings );
	return muted ? `${ main } ${ muted }` : main;
}

/**
 * Words for an activity type, matching the legend.
 *
 * @param {string} type "published", "drafted" or "idea".
 * @return {string} Lowercase label.
 */
export function typeLabel( type ) {
	switch ( type ) {
		case 'published':
			return __( 'published', 'blog-rhythm' );
		case 'drafted':
			return __( 'drafted', 'blog-rhythm' );
		default:
			return __( 'idea or outline', 'blog-rhythm' );
	}
}

/**
 * One entry per active day, newest first, for the text alternative.
 *
 * @param {Object} days Map of Y-m-d to activity type.
 * @return {Array<{date: string, text: string}>} Entries.
 */
export function listActiveDays( days ) {
	return Object.keys( days || {} )
		.filter( ( date ) => days[ date ] )
		.sort()
		.reverse()
		.map( ( date ) => ( {
			date,
			text: sprintf(
				/* translators: 1: date such as Oct 27, 2: activity such as published */
				__( '%1$s: %2$s', 'blog-rhythm' ),
				formatDate( date ),
				typeLabel( days[ date ] )
			),
		} ) );
}
