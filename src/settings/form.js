/**
 * Pure helpers for the settings form: defaults per pace, client-side
 * validation, the payload sent on save, and the preview payload built from
 * unsaved form state.
 */
import { sprintf, __ } from '@wordpress/i18n';

export const PACE_TARGETS = { daily: 3, weekly: 2, monthly: 4 };

export const INTENTION_MAX = 80;
export const MESSAGE_MAX = 120;

const DAY_MS = 86400000;

/**
 * Highest target for a pace: days in a week, or days in a month.
 *
 * @param {string} pace Pace.
 * @return {number} Max.
 */
export function targetMax( pace ) {
	return pace === 'monthly' ? 31 : 7;
}

/**
 * Inline error for the target field, or an empty string when it's fine.
 *
 * @param {string}        pace   Pace.
 * @param {string|number} target Raw field value.
 * @return {string} Error message.
 */
export function targetError( pace, target ) {
	if ( pace === 'open' ) {
		return '';
	}
	const max = targetMax( pace );
	const raw = String( target ).trim();
	const n = Number( raw );
	if ( raw === '' || ! Number.isInteger( n ) || n < 1 || n > max ) {
		return sprintf(
			/* translators: %d: highest allowed number, 7 or 31 */
			__( 'Choose a number from 1 to %d.', 'blog-rhythm' ),
			max
		);
	}
	return '';
}

function isDate( value ) {
	return /^\d{4}-\d{2}-\d{2}$/.test( String( value || '' ) );
}

/**
 * The settings object sent to POST /settings: known keys only, typed values.
 * The server sanitizes again; this keeps the request honest.
 *
 * @param {Object} form Form state.
 * @return {Object} Payload.
 */
export function toPayload( form ) {
	const counts = form.counts || {};
	const reminders = form.reminders || {};
	const payload = {
		pace: form.pace,
		target: parseInt( form.target, 10 ) || PACE_TARGETS[ form.pace ] || 3,
		counts: {
			publish: !! counts.publish,
			draft: !! counts.draft,
			idea: !! counts.idea,
			edit: !! counts.edit,
		},
		intention: String( form.intention || '' )
			.trim()
			.slice( 0, INTENTION_MAX ),
		resting: !! form.resting,
		rest_until: isDate( form.rest_until ) ? form.rest_until : '',
		reminders: {
			enabled: !! reminders.enabled,
			day: reminders.day,
			hour: parseInt( reminders.hour, 10 ) || 0,
			email: !! reminders.email,
			widget_note: !! reminders.widget_note,
			skip_if_done: !! reminders.skip_if_done,
			message: String( reminders.message || '' )
				.trim()
				.slice( 0, MESSAGE_MAX ),
		},
		look: form.look,
		show_summary: !! form.show_summary,
		show_log_button: !! form.show_log_button,
	};
	return payload;
}

function toMs( ymd ) {
	const [ y, m, d ] = ymd.split( '-' ).map( Number );
	return Date.UTC( y, m - 1, d );
}

function weekStartOf( today, weekStart ) {
	const dow = new Date( toMs( today ) ).getUTCDay();
	const offset = ( dow - weekStart + 7 ) % 7;
	return new Date( toMs( today ) - offset * DAY_MS )
		.toISOString()
		.slice( 0, 10 );
}

/**
 * Show-up days in the current week or month, through today.
 *
 * @param {Object} days      Map of Y-m-d to activity type.
 * @param {string} today     Today as Y-m-d.
 * @param {string} period    "week" or "month".
 * @param {number} weekStart First day of the week, 0 to 6.
 * @return {number} Count.
 */
export function countThisPeriod( days, today, period, weekStart = 0 ) {
	const start =
		period === 'month'
			? `${ today.slice( 0, 7 ) }-01`
			: weekStartOf( today, weekStart );
	return Object.keys( days || {} ).filter(
		( date ) => days[ date ] && date >= start && date <= today
	).length;
}

/**
 * Today in the browser, as Y-m-d. Only a fallback when the rhythm can't load.
 *
 * @return {string} Date.
 */
export function localToday() {
	const now = new Date();
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${ now.getFullYear() }-${ pad( now.getMonth() + 1 ) }-${ pad( now.getDate() ) }`;
}

/**
 * A rhythm payload for the preview: the form's settings over the server's
 * days. Which days count is decided on the server, so those follow a save.
 *
 * @param {Object}  form   Form state.
 * @param {?Object} rhythm GET /rhythm payload, or null.
 * @return {Object} Payload for WidgetView.
 */
export function previewData( form, rhythm ) {
	const days = ( rhythm && rhythm.days ) || {};
	const today = ( rhythm && rhythm.today ) || localToday();
	const weekStart = rhythm ? Number( rhythm.week_start ) || 0 : 0;
	const period = form.pace === 'monthly' ? 'month' : 'week';
	const max = targetMax( form.pace );
	const target = Math.min(
		max,
		Math.max( 0, parseInt( form.target, 10 ) || 0 )
	);
	const restUntil = isDate( form.rest_until ) ? form.rest_until : '';
	const resting =
		!! form.resting && ( restUntil === '' || today <= restUntil );
	const reminders = form.reminders || {};
	const note =
		! resting &&
		reminders.enabled &&
		reminders.widget_note &&
		rhythm &&
		rhythm.note
			? reminders.message
			: null;

	return {
		settings: { ...form, target },
		days,
		summary: {
			count: countThisPeriod( days, today, period, weekStart ),
			target: form.pace === 'open' ? 0 : target,
			pace: form.pace,
			period,
			resting,
			rest_until: restUntil,
		},
		today,
		note,
		week_start: weekStart,
	};
}

/**
 * Today's date for the export file name.
 *
 * @param {?Object} rhythm Rhythm payload.
 * @return {string} Y-m-d.
 */
export function exportDate( rhythm ) {
	return ( rhythm && rhythm.today ) || localToday();
}
