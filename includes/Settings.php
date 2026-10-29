<?php
/**
 * Per-user settings service.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defaults, sanitizing, and storage for a writer's settings (user meta).
 */
class Settings {

	const META_KEY = 'blog_rhythm_settings';

	const REMINDERS_ON_KEY = 'blog_rhythm_reminders_on';

	const REMINDER_SECRET_KEY = 'blog_rhythm_reminder_secret';

	const PACES = array( 'daily', 'weekly', 'monthly', 'open' );

	const REMINDER_DAYS = array( 'daily', 'weekdays', 'mon', 'wed', 'fri', 'sun' );

	const LOOKS = array( 'auto', 'dots', 'weeks' );

	const INTENTION_MAX = 80;

	const MESSAGE_MAX = 120;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'pace'            => 'daily',
			'target'          => 3,
			'counts'          => array(
				'publish' => true,
				'draft'   => true,
				'idea'    => true,
				'edit'    => false,
			),
			'intention'       => __( 'Write a little, most days.', 'blog-rhythm' ),
			'resting'         => false,
			'rest_until'      => '',
			'reminders'       => array(
				'enabled'      => false,
				'day'          => 'mon',
				'hour'         => 9,
				'email'        => true,
				'widget_note'  => false,
				'skip_if_done' => true,
				'message'      => __( 'Your rhythm is here whenever you are. Want to write a little?', 'blog-rhythm' ),
			),
			'look'            => 'auto',
			'show_summary'    => true,
			'show_log_button' => true,
		);
	}

	/**
	 * Sanitize a possibly partial settings array into a full one.
	 *
	 * Missing keys fall back to defaults; unknown keys are dropped.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( array $input ): array {
		return self::sanitize_over( self::defaults(), $input );
	}

	/**
	 * Read a user's settings (stored values merged over defaults).
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META_KEY, true );
		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Merge input over the user's stored settings, sanitize, and save.
	 *
	 * @param int                     $user_id User ID.
	 * @param array                   $input   Raw (possibly partial) input.
	 * @param \DateTimeImmutable|null $now     Current moment; defaults to now.
	 * @return array Stored settings.
	 */
	public static function update( int $user_id, array $input, ?\DateTimeImmutable $now = null ): array {
		$previous = self::get( $user_id );
		$settings = self::sanitize_over( $previous, $input );

		// update_user_meta() unslashes, so slash first to keep backslashes.
		update_user_meta( $user_id, self::META_KEY, wp_slash( $settings ) );

		if ( $settings['reminders']['enabled'] ) {
			update_user_meta( $user_id, self::REMINDERS_ON_KEY, '1' );
			if ( ! $previous['reminders']['enabled'] ) {
				update_user_meta( $user_id, self::REMINDER_SECRET_KEY, wp_generate_password( 32, false ) );
				// Switching on after today's reminder hour shouldn't send one
				// within the hour: treat today's reminder as already sent.
				$now = $now ? $now : current_datetime();
				if ( Reminders::is_due( $settings, $now ) ) {
					update_user_meta( $user_id, Reminders::LAST_KEY, Reminders::period_key( $settings, $now ) );
				}
			} else {
				self::ensure_secret( $user_id );
			}
		} else {
			delete_user_meta( $user_id, self::REMINDERS_ON_KEY );
		}

		return $settings;
	}

	/**
	 * Make sure the user has a turn-off secret, generating one if it's missing.
	 *
	 * @param int $user_id User ID.
	 */
	public static function ensure_secret( int $user_id ): void {
		if ( '' === (string) get_user_meta( $user_id, self::REMINDER_SECRET_KEY, true ) ) {
			update_user_meta( $user_id, self::REMINDER_SECRET_KEY, wp_generate_password( 32, false ) );
		}
	}

	/**
	 * Settle a rest that has already ended, so the form shows resting off.
	 *
	 * @param array              $settings Settings.
	 * @param \DateTimeImmutable $now      Current moment.
	 * @return array
	 */
	public static function settle_rest( array $settings, \DateTimeImmutable $now ): array {
		$until = isset( $settings['rest_until'] ) ? (string) $settings['rest_until'] : '';
		if ( ! empty( $settings['resting'] ) && '' !== $until && $until < $now->setTimezone( wp_timezone() )->format( 'Y-m-d' ) ) {
			$settings['resting']    = false;
			$settings['rest_until'] = '';
		}
		return $settings;
	}

	/**
	 * Whether the writer is resting as of $now.
	 *
	 * @param array              $settings Settings.
	 * @param \DateTimeImmutable $now      Current time in site time.
	 * @return bool
	 */
	public static function is_resting( array $settings, \DateTimeImmutable $now ): bool {
		if ( empty( $settings['resting'] ) ) {
			return false;
		}
		$until = isset( $settings['rest_until'] ) ? (string) $settings['rest_until'] : '';
		if ( '' === $until ) {
			return true;
		}
		return $now->format( 'Y-m-d' ) <= $until;
	}

	/**
	 * Sanitize $input, using $base for any missing keys.
	 *
	 * @param array $base  Full, valid settings to fall back to.
	 * @param array $input Raw input.
	 * @return array
	 */
	private static function sanitize_over( array $base, array $input ): array {
		$out = array();

		$out['pace'] = ( isset( $input['pace'] ) && in_array( $input['pace'], self::PACES, true ) ) ? $input['pace'] : $base['pace'];

		$max = 'monthly' === $out['pace'] ? 31 : 7;
		if ( array_key_exists( 'target', $input ) ) {
			$target = is_numeric( $input['target'] ) ? (int) $input['target'] : self::defaults()['target'];
		} else {
			$target = $base['target'];
		}
		$out['target'] = max( 1, min( $max, $target ) );

		$counts_in     = isset( $input['counts'] ) && is_array( $input['counts'] ) ? $input['counts'] : array();
		$out['counts'] = array();
		foreach ( $base['counts'] as $key => $value ) {
			$out['counts'][ $key ] = self::bool( $counts_in, $key, $value );
		}

		if ( array_key_exists( 'intention', $input ) ) {
			$out['intention'] = self::text( $input['intention'], self::INTENTION_MAX );
		} else {
			$out['intention'] = $base['intention'];
		}

		$out['resting'] = self::bool( $input, 'resting', $base['resting'] );

		if ( array_key_exists( 'rest_until', $input ) ) {
			$out['rest_until'] = self::date( $input['rest_until'] );
		} else {
			$out['rest_until'] = $base['rest_until'];
		}

		$rem_in  = isset( $input['reminders'] ) && is_array( $input['reminders'] ) ? $input['reminders'] : array();
		$rem_def = self::defaults()['reminders'];
		$rem     = $base['reminders'];

		$reminders            = array();
		$reminders['enabled'] = self::bool( $rem_in, 'enabled', $rem['enabled'] );
		$reminders['day']     = ( isset( $rem_in['day'] ) && in_array( $rem_in['day'], self::REMINDER_DAYS, true ) ) ? $rem_in['day'] : ( isset( $rem_in['day'] ) ? $rem_def['day'] : $rem['day'] );
		if ( array_key_exists( 'hour', $rem_in ) ) {
			$hour = is_numeric( $rem_in['hour'] ) ? (int) $rem_in['hour'] : $rem_def['hour'];
		} else {
			$hour = $rem['hour'];
		}
		$reminders['hour']         = max( 0, min( 23, $hour ) );
		$reminders['email']        = self::bool( $rem_in, 'email', $rem['email'] );
		$reminders['widget_note']  = self::bool( $rem_in, 'widget_note', $rem['widget_note'] );
		$reminders['skip_if_done'] = self::bool( $rem_in, 'skip_if_done', $rem['skip_if_done'] );
		if ( array_key_exists( 'message', $rem_in ) ) {
			$message              = self::text( $rem_in['message'], self::MESSAGE_MAX );
			$reminders['message'] = '' === $message ? $rem_def['message'] : $message;
		} else {
			$reminders['message'] = $rem['message'];
		}
		$out['reminders'] = $reminders;

		$out['look'] = ( isset( $input['look'] ) && in_array( $input['look'], self::LOOKS, true ) ) ? $input['look'] : $base['look'];

		$out['show_summary']    = self::bool( $input, 'show_summary', $base['show_summary'] );
		$out['show_log_button'] = self::bool( $input, 'show_log_button', $base['show_log_button'] );

		return $out;
	}

	/**
	 * Read a boolean from $source[$key], falling back to $fallback.
	 *
	 * Only true/false, 1/0, "1"/"0", and "true"/"false" are understood;
	 * anything else keeps the fallback.
	 *
	 * @param array  $source   Source array.
	 * @param string $key      Key.
	 * @param bool   $fallback Fallback.
	 * @return bool
	 */
	private static function bool( array $source, string $key, bool $fallback ): bool {
		if ( ! array_key_exists( $key, $source ) ) {
			return $fallback;
		}
		$value = $source[ $key ];
		if ( true === $value || 1 === $value || '1' === $value || 'true' === $value ) {
			return true;
		}
		if ( false === $value || 0 === $value || '0' === $value || 'false' === $value ) {
			return false;
		}
		return $fallback;
	}

	/**
	 * Strip tags, normalize, and truncate to $max characters.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	private static function text( $value, int $max ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = sanitize_text_field( wp_strip_all_tags( (string) $value ) );
		return mb_substr( $text, 0, $max );
	}

	/**
	 * Accept only a real Y-m-d calendar date.
	 *
	 * @param mixed $value Raw value.
	 * @return string Date or empty string.
	 */
	private static function date( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}
		return $value;
	}
}
