<?php
/**
 * Derives per-day writing activity for one user.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity types per site-time day, from the user's own posts and revisions.
 */
class Activity {

	const TYPES = array( 'published', 'drafted', 'updated', 'idea' );

	const ZERO = '0000-00-00 00:00:00';

	const STATUSES = array( 'publish', 'private', 'draft', 'pending', 'future' );

	const COLUMNS = 'ID, post_parent, post_author, post_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt';

	/**
	 * Passive scans memoized for this request: user => scanned range and days.
	 *
	 * @var array<int, array{zone: string, from: string, to: string, days: array}>
	 */
	private static $memo = array();

	/**
	 * Forget memoized scans. Runs whenever a post's cache is cleaned, and
	 * tests can call it directly.
	 */
	public static function flush(): void {
		self::$memo = array();
	}

	/**
	 * Activity per day, inclusive of both ends.
	 *
	 * Counts settings are not applied here; callers filter by type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $from    Start date, Y-m-d (site time zone).
	 * @param string $to      End date, Y-m-d (site time zone).
	 * @return array<string, string[]> Date => types, ordered by date.
	 */
	public static function for_range( int $user_id, string $from, string $to ): array {
		$days = array();

		foreach ( self::passive_cached( $user_id, $from, $to ) as $day => $types ) {
			$days[ $day ] = $types;
		}
		foreach ( Log::entries( $user_id ) as $day => $types ) {
			foreach ( $types as $type ) {
				$days[ $day ][ $type ] = true;
			}
		}

		$cleared = Log::cleared_before( $user_id );
		$out     = array();
		ksort( $days );
		foreach ( $days as $day => $set ) {
			if ( $day < $from || $day > $to || ( '' !== $cleared && $day < $cleared ) ) {
				continue;
			}
			$types = array_values( array_filter( self::TYPES, static fn( $t ) => isset( $set[ $t ] ) ) );
			if ( $types ) {
				$out[ $day ] = $types;
			}
		}
		return $out;
	}

	/**
	 * Passive activity covering [$from, $to], reusing this request's scan when
	 * it already covers the range. Days outside the range may be incomplete.
	 *
	 * @param int    $user_id User ID.
	 * @param string $from    Start date, Y-m-d.
	 * @param string $to      End date, Y-m-d.
	 * @return array<string, array<string, true>>
	 */
	private static function passive_cached( int $user_id, string $from, string $to ): array {
		$zone = wp_timezone_string();
		if ( isset( self::$memo[ $user_id ] ) ) {
			$memo = self::$memo[ $user_id ];
			if ( $memo['zone'] === $zone && $memo['from'] <= $from && $memo['to'] >= $to ) {
				return $memo['days'];
			}
			if ( $memo['zone'] === $zone ) {
				$from = min( $from, $memo['from'] );
				$to   = max( $to, $memo['to'] );
			}
		}

		$days                   = self::passive( $user_id, $from, $to );
		self::$memo[ $user_id ] = array(
			'zone' => $zone,
			'from' => $from,
			'to'   => $to,
			'days' => $days,
		);
		return $days;
	}

	/**
	 * Passive activity from posts and revisions touching [$from, $to].
	 *
	 * Selects only the columns the rules need and bounds every date column to
	 * the range padded by a day, so time zone offsets can't push a post out.
	 * Plain prepared SELECTs keep this portable to SQLite.
	 *
	 * @param int    $user_id User ID.
	 * @param string $from    Start date, Y-m-d (site time zone).
	 * @param string $to      End date, Y-m-d (site time zone).
	 * @return array<string, array<string, true>>
	 */
	private static function passive( int $user_id, string $from, string $to ): array {
		global $wpdb;

		$days = array();
		$lo   = self::bound( $from, '-1 day', '00:00:00' );
		$hi   = self::bound( $to, '+1 day', '23:59:59' );
		if ( null === $lo || null === $hi ) {
			return $days;
		}

		$statuses = implode( ',', array_fill( 0, count( self::STATUSES ), '%s' ) );
		$columns  = self::COLUMNS;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lean, range-bounded scan of a few columns (no post bodies); memoized per request above. Interpolated parts are class constants, $wpdb->posts and a placeholder list built to match the statuses; arguments are passed as one array.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$columns} FROM {$wpdb->posts}
				WHERE post_type = 'post' AND post_author = %d AND post_status IN ( {$statuses} )
				AND ( ( post_date_gmt BETWEEN %s AND %s ) OR ( post_date BETWEEN %s AND %s )
					OR ( post_modified_gmt BETWEEN %s AND %s ) OR ( post_modified BETWEEN %s AND %s ) )
				ORDER BY ID ASC",
				array_merge( array( $user_id ), self::STATUSES, array( $lo, $hi, $lo, $hi, $lo, $hi, $lo, $hi ) )
			)
		);

		$mine = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent, post_author, post_modified, post_modified_gmt FROM {$wpdb->posts}
				WHERE post_type = 'revision' AND post_status = 'inherit' AND post_author = %d
				AND ( ( post_modified_gmt BETWEEN %s AND %s ) OR ( post_modified BETWEEN %s AND %s ) )
				ORDER BY ID ASC",
				$user_id,
				$lo,
				$hi,
				$lo,
				$hi
			)
		);
		// phpcs:enable

		$parents = array();
		foreach ( (array) $rows as $post ) {
			if ( (int) $post->post_author === $user_id ) {
				$parents[ (int) $post->ID ] = $post;
			}
		}

		// Revisions in range can belong to older posts: fetch those parents by ID.
		$missing = array();
		foreach ( (array) $mine as $rev ) {
			$pid = (int) $rev->post_parent;
			if ( $pid > 0 && ! isset( $parents[ $pid ] ) ) {
				$missing[ $pid ] = $pid;
			}
		}
		foreach ( self::fetch_parents( $user_id, array_values( $missing ) ) as $post ) {
			$parents[ (int) $post->ID ] = $post;
		}
		if ( ! $parents ) {
			return $days;
		}

		// Who has saved each post, so we know whether post_modified can be
		// trusted for posts without revisions.
		$has_revisions = array();
		$others_saved  = array();
		foreach ( self::revision_authors( array_keys( $parents ) ) as $row ) {
			$pid                   = (int) $row->post_parent;
			$has_revisions[ $pid ] = true;
			if ( (int) $row->post_author !== $user_id ) {
				$others_saved[ $pid ] = true;
			}
		}

		$publish_day = array();
		foreach ( $parents as $pid => $post ) {
			if ( in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
				$day = self::day( $post->post_date_gmt, $post->post_date );
				if ( $day ) {
					$publish_day[ $pid ]       = $day;
					$days[ $day ]['published'] = true;
				}
				continue;
			}

			// Draft, pending, future. Saving a draft resets its dates, so once
			// another author has saved it neither date says anything about the
			// user: only the user's own revisions count then. A scheduled date
			// isn't writing activity, so future posts use post_modified.
			if ( ! empty( $others_saved[ $pid ] ) ) {
				continue;
			}
			$created = 'future' === $post->post_status
				? self::day( $post->post_modified_gmt, $post->post_modified )
				: ( self::day( $post->post_date_gmt, $post->post_date ) ?? self::day( $post->post_modified_gmt, $post->post_modified ) );
			if ( $created ) {
				$days[ $created ]['drafted'] = true;
			}
			if ( empty( $has_revisions[ $pid ] ) ) {
				$modified = self::day( $post->post_modified_gmt, $post->post_modified );
				if ( $modified ) {
					$days[ $modified ]['drafted'] = true;
				}
			}
		}

		foreach ( (array) $mine as $rev ) {
			$pid = (int) $rev->post_parent;
			if ( ! isset( $parents[ $pid ] ) ) {
				continue;
			}
			$day = self::day( $rev->post_modified_gmt, $rev->post_modified );
			if ( ! $day ) {
				continue;
			}
			if ( isset( $publish_day[ $pid ] ) && $day > $publish_day[ $pid ] ) {
				$days[ $day ]['updated'] = true;
			} else {
				$days[ $day ]['drafted'] = true;
			}
		}

		return $days;
	}

	/**
	 * The user's own counted posts with these IDs.
	 *
	 * @param int   $user_id User ID.
	 * @param int[] $ids     Post IDs.
	 * @return object[]
	 */
	private static function fetch_parents( int $user_id, array $ids ): array {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$statuses     = implode( ',', array_fill( 0, count( self::STATUSES ), '%s' ) );
		$columns      = self::COLUMNS;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Lean lookup by ID; placeholders are built to match the IDs, memoized per request.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$columns} FROM {$wpdb->posts}
				WHERE post_type = 'post' AND post_author = %d AND post_status IN ( {$statuses} ) AND ID IN ( {$placeholders} )",
				array_merge( array( $user_id ), self::STATUSES, $ids )
			)
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Distinct (post_parent, post_author) pairs of revisions of these posts.
	 *
	 * @param int[] $ids Parent post IDs.
	 * @return object[]
	 */
	private static function revision_authors( array $ids ): array {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Two small columns per revision; placeholders are built to match the IDs, memoized per request.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT post_parent, post_author FROM {$wpdb->posts}
				WHERE post_type = 'revision' AND post_status = 'inherit' AND post_parent IN ( {$placeholders} )",
				$ids
			)
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * A padded 'Y-m-d H:i:s' bound for date-column comparisons.
	 *
	 * @param string $date   Y-m-d.
	 * @param string $modify Offset, e.g. '-1 day'.
	 * @param string $time   H:i:s.
	 * @return string|null
	 */
	private static function bound( string $date, string $modify, string $time ): ?string {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( false === $dt ) {
			return null;
		}
		return $dt->modify( $modify )->format( 'Y-m-d' ) . ' ' . $time;
	}

	/**
	 * Y-m-d in the site time zone from a GMT value, falling back to the local one.
	 *
	 * @param string $gmt   GMT datetime (may be the zero date).
	 * @param string $local Site-local datetime.
	 * @return string|null
	 */
	private static function day( $gmt, $local ): ?string {
		if ( is_string( $gmt ) && '' !== $gmt && self::ZERO !== $gmt ) {
			try {
				$dt = new \DateTimeImmutable( $gmt, new \DateTimeZone( 'UTC' ) );
				return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d' );
			} catch ( \Exception $e ) {
				return null;
			}
		}
		if ( is_string( $local ) && '' !== $local && self::ZERO !== $local && preg_match( '/^\d{4}-\d{2}-\d{2}/', $local ) ) {
			return substr( $local, 0, 10 );
		}
		return null;
	}
}
