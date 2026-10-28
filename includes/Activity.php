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

		foreach ( self::passive( $user_id ) as $day => $types ) {
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
	 * Passive activity for all time: day => set of types.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, array<string, true>>
	 */
	private static function passive( int $user_id ): array {
		$posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'author'                 => $user_id,
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$days = array();
		if ( ! $posts ) {
			return $days;
		}

		$parents = array();
		foreach ( $posts as $post ) {
			if ( (int) $post->post_author !== $user_id ) {
				continue;
			}
			$parents[ (int) $post->ID ] = $post;
		}
		if ( ! $parents ) {
			return $days;
		}

		// All revisions of those posts (any author), so we know whether
		// post_modified can be trusted for posts without revisions.
		$revisions = get_posts(
			array(
				'post_type'              => 'revision',
				'post_status'            => 'inherit',
				'post_parent__in'        => array_keys( $parents ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$has_revisions = array();
		$others_saved  = array();
		$mine          = array();
		foreach ( $revisions as $rev ) {
			$pid = (int) $rev->post_parent;
			if ( ! isset( $parents[ $pid ] ) ) {
				continue;
			}
			$has_revisions[ $pid ] = true;
			if ( (int) $rev->post_author === $user_id ) {
				$mine[] = $rev;
			} else {
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

		foreach ( $mine as $rev ) {
			$pid = (int) $rev->post_parent;
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
