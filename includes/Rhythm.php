<?php
/**
 * Rhythm math: counted days, period bounds, and summary.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns raw activity into show-up days, period bounds, and a summary.
 */
class Rhythm {

	/**
	 * Reduce per-day activity to the single top counted type per day.
	 *
	 * Priority is published, then drafted, then idea. An "updated" entry
	 * counts only when edits are enabled, and is reported as drafted.
	 * Days with nothing counted are omitted.
	 *
	 * @param array<string, string[]> $activity Date => activity types.
	 * @param array                   $counts   Settings counts (publish, draft, idea, edit).
	 * @return array<string, string> Date => published|drafted|idea.
	 */
	public static function counted_days( array $activity, array $counts ): array {
		$out = array();
		foreach ( $activity as $date => $types ) {
			$types = (array) $types;
			if ( ! empty( $counts['publish'] ) && in_array( 'published', $types, true ) ) {
				$out[ $date ] = 'published';
			} elseif (
				( ! empty( $counts['draft'] ) && in_array( 'drafted', $types, true ) )
				|| ( ! empty( $counts['edit'] ) && in_array( 'updated', $types, true ) )
			) {
				$out[ $date ] = 'drafted';
			} elseif ( ! empty( $counts['idea'] ) && in_array( 'idea', $types, true ) ) {
				$out[ $date ] = 'idea';
			}
		}
		return $out;
	}

	/**
	 * Period shown in the summary for a pace.
	 *
	 * @param string $pace daily|weekly|monthly|open.
	 * @return string week|month.
	 */
	public static function summary_period( string $pace ): string {
		return 'monthly' === $pace ? 'month' : 'week';
	}

	/**
	 * Period a reminder looks at for a pace.
	 *
	 * @param string $pace daily|weekly|monthly|open.
	 * @return string day|week|month.
	 */
	public static function reminder_period( string $pace ): string {
		if ( 'daily' === $pace ) {
			return 'day';
		}
		return 'monthly' === $pace ? 'month' : 'week';
	}

	/**
	 * Inclusive bounds of the period containing $now, in site time.
	 *
	 * @param string             $period day|week|month.
	 * @param \DateTimeImmutable $now    Any moment; converted to site time.
	 * @return array{start: string, end: string} Y-m-d strings.
	 */
	public static function period_bounds( string $period, \DateTimeImmutable $now ): array {
		$local = $now->setTimezone( wp_timezone() )->setTime( 0, 0, 0 );

		if ( 'month' === $period ) {
			$start = $local->modify( 'first day of this month' );
			$end   = $local->modify( 'last day of this month' );
		} elseif ( 'week' === $period ) {
			$first  = (int) get_option( 'start_of_week' );
			$offset = ( (int) $local->format( 'w' ) - $first + 7 ) % 7;
			$start  = $local->modify( "-{$offset} days" );
			$end    = $start->modify( '+6 days' );
		} else {
			$start = $local;
			$end   = $local;
		}

		return array(
			'start' => $start->format( 'Y-m-d' ),
			'end'   => $end->format( 'Y-m-d' ),
		);
	}

	/**
	 * Summary of this period's show-up days.
	 *
	 * @param int                $user_id User ID.
	 * @param \DateTimeImmutable $now     Current moment.
	 * @return array{count: int, target: int, pace: string, period: string, resting: bool, rest_until: string}
	 */
	public static function summary( int $user_id, \DateTimeImmutable $now ): array {
		$now      = $now->setTimezone( wp_timezone() );
		$settings = Settings::get( $user_id );
		$pace     = $settings['pace'];
		$period   = self::summary_period( $pace );

		return array(
			'count'      => count( self::days_through_today( $user_id, $settings, $period, $now ) ),
			'target'     => 'open' === $pace ? 0 : (int) $settings['target'],
			'pace'       => $pace,
			'period'     => $period,
			'resting'    => Settings::is_resting( $settings, $now ),
			'rest_until' => $settings['rest_until'],
		);
	}

	/**
	 * Whether the writer has any counted day in the current reminder period.
	 *
	 * @param int                $user_id User ID.
	 * @param \DateTimeImmutable $now     Current moment.
	 * @return bool
	 */
	public static function has_shown_up( int $user_id, \DateTimeImmutable $now ): bool {
		$now      = $now->setTimezone( wp_timezone() );
		$settings = Settings::get( $user_id );
		$period   = self::reminder_period( $settings['pace'] );

		return array() !== self::days_through_today( $user_id, $settings, $period, $now );
	}

	/**
	 * Counted days from the period start through today, using one query.
	 *
	 * @param int                $user_id  User ID.
	 * @param array              $settings User settings.
	 * @param string             $period   day|week|month.
	 * @param \DateTimeImmutable $now      Site-time moment.
	 * @return array<string, string> Date => type.
	 */
	private static function days_through_today( int $user_id, array $settings, string $period, \DateTimeImmutable $now ): array {
		$bounds = self::period_bounds( $period, $now );
		$today  = $now->format( 'Y-m-d' );
		$to     = min( $bounds['end'], $today );

		$activity = Activity::for_range( $user_id, $bounds['start'], $to );
		return self::counted_days( $activity, $settings['counts'] );
	}
}
