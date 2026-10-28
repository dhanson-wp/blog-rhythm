/**
 * The shared Blog Rhythm visual: a dot calendar, a week-by-week stack, or
 * month blocks, plus a visually hidden list that is the text alternative.
 *
 * Accessibility: the drawn grid is aria-hidden (decorative) and the list
 * right beside it carries the same information, one entry per active day.
 */
import { __ } from '@wordpress/i18n';
import { VisuallyHidden } from '@wordpress/ui';
import {
	buildDotColumns,
	buildMonthBlocks,
	buildWeekStack,
	listActiveDays,
	resolveLook,
	describeRhythm,
} from './layout';
import './style.scss';

const DOT_WEEKS = 26;
const WEEK_COUNT = 12;
const MONTH_COUNT = 12;
const COLUMN_STEP = 13; // 10px cell + 3px gap.

function cellClass( type, extra = '' ) {
	return [ 'blog-rhythm-cell', type ? `is-${ type }` : '', extra ]
		.filter( Boolean )
		.join( ' ' );
}

function Dots( { days, today, weekStart, resting } ) {
	const { columns, monthLabels } = buildDotColumns( days, today, DOT_WEEKS, weekStart );
	return (
		<div className="blog-rhythm-dots">
			<div className="blog-rhythm-month-labels">
				{ monthLabels.map( ( { index, label } ) => (
					<span key={ index } style={ { left: index * COLUMN_STEP } }>
						{ label }
					</span>
				) ) }
			</div>
			<div className="blog-rhythm-grid">
				{ columns.map( ( column, ci ) => (
					<div className="blog-rhythm-col" key={ ci }>
						{ column.map( ( cell, ri ) => {
							if ( ! cell || cell.isFuture ) {
								return <div key={ ri } className="blog-rhythm-cell is-blank" />;
							}
							const classes = [
								cell.isToday ? 'is-today' : '',
								resting && cell.isToday && ! cell.type ? 'is-resting' : '',
							]
								.filter( Boolean )
								.join( ' ' );
							return <div key={ ri } className={ cellClass( cell.type, classes ) } />;
						} ) }
					</div>
				) ) }
			</div>
		</div>
	);
}

function Weeks( { days, today, target, weekStart } ) {
	const weeks = buildWeekStack( days, today, target, WEEK_COUNT, weekStart );
	return (
		<div className="blog-rhythm-weeks">
			{ weeks.map( ( week ) => (
				<div className="blog-rhythm-week" key={ week.start }>
					{ Array.from( { length: week.slots }, ( _, i ) => (
						<div
							key={ i }
							className={ cellClass( i < week.count ? 'published' : null, 'is-round' ) }
						/>
					) ) }
					<small>{ week.label }</small>
				</div>
			) ) }
		</div>
	);
}

function Months( { days, today } ) {
	const months = buildMonthBlocks( days, today, MONTH_COUNT );
	return (
		<div className="blog-rhythm-months">
			{ months.map( ( month ) => (
				<div className="blog-rhythm-month" key={ month.key }>
					<div className={ `blog-rhythm-block is-${ month.shade }` } />
					<small>{ month.label }</small>
				</div>
			) ) }
		</div>
	);
}

function Legend( { look } ) {
	if ( look === 'weeks' ) {
		return (
			<div className="blog-rhythm-legend" aria-hidden="true">
				<span>
					<i className={ cellClass( 'published', 'is-round' ) } />
					{ __( 'A time you showed up', 'blog-rhythm' ) }
				</span>
			</div>
		);
	}
	if ( look === 'months' ) {
		return null;
	}
	return (
		<div className="blog-rhythm-legend" aria-hidden="true">
			{ [ 'published', 'drafted', 'idea' ].map( ( type ) => (
				<span key={ type }>
					<i className={ cellClass( type ) } />
					{ type === 'published' && __( 'Published', 'blog-rhythm' ) }
					{ type === 'drafted' && __( 'Drafted', 'blog-rhythm' ) }
					{ type === 'idea' && __( 'Idea or outline', 'blog-rhythm' ) }
				</span>
			) ) }
		</div>
	);
}

export default function Visualization( {
	look = 'auto',
	days = {},
	today,
	settings = {},
	summary = {},
	weekStart = 0,
} ) {
	const visual = resolveLook( look, settings.pace );
	const entries = listActiveDays( days );
	const resting = !! summary.resting;
	const target = Number( settings.target ) || 0;

	return (
		<div className="blog-rhythm-viz">
			<div aria-hidden="true">
				{ visual === 'dots' && (
					<Dots days={ days } today={ today } weekStart={ weekStart } resting={ resting } />
				) }
				{ visual === 'weeks' && <Weeks days={ days } today={ today } target={ target } weekStart={ weekStart } /> }
				{ visual === 'months' && <Months days={ days } today={ today } /> }
			</div>
			<Legend look={ visual } />
			<VisuallyHidden>
				<p>{ describeRhythm( summary, settings ) }</p>
				{ entries.length > 0 && (
					<ul aria-label={ __( 'Days you showed up', 'blog-rhythm' ) }>
						{ entries.map( ( entry ) => (
							<li key={ entry.date }>{ entry.text }</li>
						) ) }
					</ul>
				) }
			</VisuallyHidden>
		</div>
	);
}

