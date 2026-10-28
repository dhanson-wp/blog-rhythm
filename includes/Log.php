<?php
/**
 * Manual log: ideas a writer notes on a given day.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores manual "idea" entries and the "cleared before" date in user meta.
 */
class Log {

	const META_KEY = 'blog_rhythm_log';

	const TYPE = 'idea';

	/**
	 * Today's date (Y-m-d) in the site time zone.
	 *
	 * @return string
	 */
	public static function today(): string {
		return current_datetime()->format( 'Y-m-d' );
	}

	/**
	 * Record a manual entry for today.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when a new entry was stored; false when already present.
	 */
	public static function add_today( int $user_id ): bool {
		$data  = self::read( $user_id );
		$today = self::today();
		if ( isset( $data['entries'][ $today ] ) ) {
			return false;
		}
		$data['entries'][ $today ] = array( self::TYPE );
		self::write( $user_id, $data );
		return true;
	}

	/**
	 * Remove today's manual entry.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when something was removed.
	 */
	public static function remove_today( int $user_id ): bool {
		$data  = self::read( $user_id );
		$today = self::today();
		if ( ! isset( $data['entries'][ $today ] ) ) {
			return false;
		}
		unset( $data['entries'][ $today ] );
		self::write( $user_id, $data );
		return true;
	}

	/**
	 * Manual entries keyed by date.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, string[]>
	 */
	public static function entries( int $user_id ): array {
		return self::read( $user_id )['entries'];
	}

	/**
	 * Empty the log and hide everything before today.
	 *
	 * @param int $user_id User ID.
	 */
	public static function clear( int $user_id ): void {
		self::write(
			$user_id,
			array(
				'entries'        => array(),
				'cleared_before' => self::today(),
			)
		);
	}

	/**
	 * Date before which activity is hidden, or an empty string.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function cleared_before( int $user_id ): string {
		return self::read( $user_id )['cleared_before'];
	}

	/**
	 * Whether a value is a real Y-m-d date.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	private static function valid_date( $value ): bool {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Read and validate stored data.
	 *
	 * @param int $user_id User ID.
	 * @return array{entries: array<string, string[]>, cleared_before: string}
	 */
	private static function read( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		$out = array(
			'entries'        => array(),
			'cleared_before' => '',
		);
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		if ( isset( $raw['entries'] ) && is_array( $raw['entries'] ) ) {
			foreach ( $raw['entries'] as $date => $types ) {
				if ( self::valid_date( $date ) ) {
					$out['entries'][ $date ] = array( self::TYPE );
				}
			}
			ksort( $out['entries'] );
		}
		if ( isset( $raw['cleared_before'] ) && self::valid_date( $raw['cleared_before'] ) ) {
			$out['cleared_before'] = $raw['cleared_before'];
		}
		return $out;
	}

	/**
	 * Persist data.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Data to store.
	 */
	private static function write( int $user_id, array $data ): void {
		update_user_meta( $user_id, self::META_KEY, wp_slash( $data ) );
	}
}
