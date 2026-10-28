<?php
/**
 * Optional, gentle reminders.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Due logic, hourly cron send, and the signed turn-off link.
 */
class Reminders {

	const CRON_HOOK = 'blog_rhythm_send_reminders';

	const LAST_KEY = 'blog_rhythm_reminder_last';

	const ACTION = 'blog_rhythm_turn_off';

	const WEEKDAYS = array(
		'mon' => 1,
		'wed' => 3,
		'fri' => 5,
		'sun' => 7,
	);

	/**
	 * Register the turn-off handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle_turn_off' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_turn_off' ) );
	}

	/**
	 * Whether a reminder is due: enabled, the reminder day matches, and the
	 * reminder hour has been reached (cron fires on traffic, so late is fine).
	 *
	 * @param array              $settings Settings.
	 * @param \DateTimeImmutable $now      Current moment.
	 * @return bool
	 */
	public static function is_due( array $settings, \DateTimeImmutable $now ): bool {
		if ( empty( $settings['reminders']['enabled'] ) ) {
			return false;
		}
		$local = $now->setTimezone( wp_timezone() );
		$day   = (string) $settings['reminders']['day'];
		$dow   = (int) $local->format( 'N' );

		if ( 'daily' === $day ) {
			$matches = true;
		} elseif ( 'weekdays' === $day ) {
			$matches = $dow <= 5;
		} else {
			$matches = isset( self::WEEKDAYS[ $day ] ) && self::WEEKDAYS[ $day ] === $dow;
		}

		return $matches && (int) $local->format( 'G' ) >= (int) $settings['reminders']['hour'];
	}

	/**
	 * The reminder occasion's site date, so each reminder day sends at most once.
	 *
	 * @param array              $settings Settings.
	 * @param \DateTimeImmutable $now      Current moment.
	 * @return string Y-m-d.
	 */
	public static function period_key( array $settings, \DateTimeImmutable $now ): string {
		unset( $settings );
		return $now->setTimezone( wp_timezone() )->format( 'Y-m-d' );
	}

	/**
	 * Send due reminders.
	 *
	 * @param \DateTimeImmutable|null $now Current moment; defaults to now.
	 * @return int Emails sent.
	 */
	public static function run( ?\DateTimeImmutable $now = null ): int {
		$now   = $now ? $now : current_datetime();
		$sent  = 0;
		$users = get_users(
			array(
				'meta_key'   => Settings::REMINDERS_ON_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
			)
		);

		foreach ( $users as $user_id ) {
			$user_id  = (int) $user_id;
			$settings = Settings::get( $user_id );

			if ( ! self::is_due( $settings, $now ) || Settings::is_resting( $settings, $now ) ) {
				continue;
			}
			if ( empty( $settings['reminders']['email'] ) ) {
				continue;
			}
			if ( ! empty( $settings['reminders']['skip_if_done'] ) && Rhythm::has_shown_up( $user_id, $now ) ) {
				continue;
			}
			$key = self::period_key( $settings, $now );
			if ( get_user_meta( $user_id, self::LAST_KEY, true ) === $key ) {
				continue;
			}
			$user = get_userdata( $user_id );
			if ( ! $user || '' === $user->user_email ) {
				continue;
			}

			$ok = wp_mail(
				$user->user_email,
				__( 'A note from Blog Rhythm', 'blog-rhythm' ),
				self::body( $user_id, $settings )
			);
			if ( true === $ok ) {
				update_user_meta( $user_id, self::LAST_KEY, $key );
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * The widget note, when one is due today.
	 *
	 * @param int                $user_id User ID.
	 * @param \DateTimeImmutable $now     Current moment.
	 * @return string|null
	 */
	public static function note_due( int $user_id, \DateTimeImmutable $now ): ?string {
		$settings = Settings::get( $user_id );
		if ( empty( $settings['reminders']['enabled'] ) || empty( $settings['reminders']['widget_note'] ) ) {
			return null;
		}
		if ( Settings::is_resting( $settings, $now ) || ! self::is_due( $settings, $now ) ) {
			return null;
		}
		if ( Rhythm::has_shown_up( $user_id, $now ) ) {
			return null;
		}
		return (string) $settings['reminders']['message'];
	}

	/**
	 * Signed link that turns a writer's reminders off.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function turn_off_url( int $user_id ): string {
		return add_query_arg(
			array(
				'action' => self::ACTION,
				'user'   => $user_id,
				'token'  => self::token( $user_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Check a turn-off token. No expiry; invalid once the secret rotates.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token.
	 * @return bool
	 */
	public static function verify_token( int $user_id, string $token ): bool {
		if ( '' === $token ) {
			return false;
		}
		$expected = self::token( $user_id );
		if ( '' === $expected ) {
			return false;
		}
		return hash_equals( $expected, $token );
	}

	/**
	 * Turn reminders off when the token is good.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token.
	 * @return bool Whether reminders were turned off.
	 */
	public static function turn_off( int $user_id, string $token ): bool {
		if ( $user_id < 1 || ! self::verify_token( $user_id, $token ) ) {
			return false;
		}
		Settings::update( $user_id, array( 'reminders' => array( 'enabled' => false ) ) );
		return true;
	}

	/**
	 * admin-post handler for the turn-off link.
	 */
	public static function handle_turn_off(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Signed link; the HMAC token is the credential.
		$user_id = isset( $_GET['user'] ) ? absint( wp_unslash( $_GET['user'] ) ) : 0;
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable

		if ( self::turn_off( $user_id, $token ) ) {
			wp_die(
				esc_html__( 'Reminders are off. You can turn them back on any time from Blog Rhythm in your dashboard.', 'blog-rhythm' ),
				esc_html__( 'Reminders are off', 'blog-rhythm' ),
				array( 'response' => 200 )
			);
		}

		wp_die(
			esc_html__( "This link doesn't work anymore. You can change reminders from Blog Rhythm in your dashboard.", 'blog-rhythm' ),
			esc_html__( 'Link not valid', 'blog-rhythm' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * HMAC token for a user, or '' when there is no secret.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function token( int $user_id ): string {
		$secret = (string) get_user_meta( $user_id, Settings::REMINDER_SECRET_KEY, true );
		if ( '' === $secret ) {
			return '';
		}
		return hash_hmac( 'sha256', (string) $user_id, $secret );
	}

	/**
	 * Plain-text email body.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function body( int $user_id, array $settings ): string {
		return $settings['reminders']['message'] . "\n\n"
			. __( 'Turn off these reminders:', 'blog-rhythm' ) . "\n"
			. self::turn_off_url( $user_id ) . "\n";
	}
}
