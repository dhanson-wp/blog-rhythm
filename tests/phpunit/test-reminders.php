<?php
/**
 * Reminders tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Reminders;
use BlogRhythm\Settings;

/**
 * Tests for BlogRhythm\Reminders.
 */
class Test_Reminders extends WP_UnitTestCase {

	/**
	 * The writer.
	 *
	 * @var int
	 */
	private $me;

	/**
	 * Captured mail.
	 *
	 * @var array
	 */
	private $sent = array();

	/**
	 * Result the mocked wp_mail returns.
	 *
	 * @var bool
	 */
	private $mail_result = true;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		$this->me          = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->sent        = array();
		$this->mail_result = true;
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tear_down() {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		parent::tear_down();
	}

	/**
	 * Mock wp_mail.
	 *
	 * @param null|bool $short Short circuit.
	 * @param array     $atts  Mail attributes.
	 * @return bool
	 */
	public function capture_mail( $short, $atts ) {
		unset( $short );
		if ( $this->mail_result ) {
			$this->sent[] = $atts;
		}
		return $this->mail_result;
	}

	/**
	 * Site-time moment. 2026-05-13 is a Wednesday.
	 *
	 * @param string $when Local time string.
	 * @return DateTimeImmutable
	 */
	private function now( string $when = '2026-05-13 09:00:00' ): DateTimeImmutable {
		return new DateTimeImmutable( $when, wp_timezone() );
	}

	private function enable( array $reminders = array(), array $extra = array() ): void {
		Settings::update(
			$this->me,
			array_merge(
				array(
					'pace'      => 'weekly',
					'reminders' => array_merge(
						array(
							'enabled'      => true,
							'day'          => 'wed',
							'hour'         => 9,
							'email'        => true,
							'skip_if_done' => true,
							'message'      => 'Want to write a little?',
						),
						$reminders
					),
				),
				$extra
			)
		);
	}

	private function post_on( string $date ): int {
		return self::factory()->post->create(
			array(
				'post_author' => $this->me,
				'post_status' => 'publish',
				'post_date'   => $date,
			)
		);
	}

	public function test_is_due_matches_day_and_hour_in_site_tz() {
		$settings = Settings::sanitize(
			array(
				'reminders' => array(
					'enabled' => true,
					'day'     => 'wed',
					'hour'    => 9,
				),
			)
		);
		$this->assertTrue( Reminders::is_due( $settings, $this->now( '2026-05-13 09:00:00' ) ) );
		$this->assertFalse( Reminders::is_due( $settings, $this->now( '2026-05-13 08:59:00' ) ) );
		$this->assertFalse( Reminders::is_due( $settings, $this->now( '2026-05-12 10:00:00' ) ) );

		// 09:00 UTC is 05:00 in New York: not due. Site time governs.
		$utc = new DateTimeImmutable( '2026-05-13 09:00:00', new DateTimeZone( 'UTC' ) );
		$this->assertFalse( Reminders::is_due( $settings, $utc ) );
		$utc_late = new DateTimeImmutable( '2026-05-13 13:00:00', new DateTimeZone( 'UTC' ) );
		$this->assertTrue( Reminders::is_due( $settings, $utc_late ) );

		$settings['reminders']['enabled'] = false;
		$this->assertFalse( Reminders::is_due( $settings, $this->now( '2026-05-13 09:00:00' ) ) );
	}

	public function test_weekdays_option_skips_weekend() {
		$settings = Settings::sanitize(
			array(
				'reminders' => array(
					'enabled' => true,
					'day'     => 'weekdays',
					'hour'    => 9,
				),
			)
		);
		$this->assertTrue( Reminders::is_due( $settings, $this->now( '2026-05-11 10:00:00' ) ) );
		$this->assertTrue( Reminders::is_due( $settings, $this->now( '2026-05-15 10:00:00' ) ) );
		$this->assertFalse( Reminders::is_due( $settings, $this->now( '2026-05-16 10:00:00' ) ) );
		$this->assertFalse( Reminders::is_due( $settings, $this->now( '2026-05-17 10:00:00' ) ) );
	}

	public function test_run_twice_in_same_window_sends_once() {
		$this->enable();
		$this->assertSame( 1, Reminders::run( $this->now() ) );
		$this->assertSame( 0, Reminders::run( $this->now( '2026-05-13 09:30:00' ) ) );
		$this->assertCount( 1, $this->sent );
	}

	public function test_late_cron_run_same_day_still_sends_once() {
		$this->enable();
		$this->assertSame( 1, Reminders::run( $this->now( '2026-05-13 14:00:00' ) ) );
		$this->assertSame( 0, Reminders::run( $this->now( '2026-05-13 15:00:00' ) ) );
		$this->assertCount( 1, $this->sent );
	}

	public function test_early_run_before_hour_sends_nothing() {
		$this->enable();
		$this->assertSame( 0, Reminders::run( $this->now( '2026-05-13 08:00:00' ) ) );
	}

	public function test_daily_reminders_send_on_consecutive_days() {
		$this->enable(
			array(
				'day'          => 'daily',
				'skip_if_done' => false,
			)
		);
		$this->assertSame( 1, Reminders::run( $this->now( '2026-05-13 09:00:00' ) ) );
		$this->assertSame( 1, Reminders::run( $this->now( '2026-05-14 09:00:00' ) ) );
		$this->assertCount( 2, $this->sent );
	}

	public function test_failed_mail_is_not_recorded_and_does_not_throw() {
		$this->enable();
		$this->mail_result = false;
		$this->assertSame( 0, Reminders::run( $this->now() ) );
		$this->assertSame( '', get_user_meta( $this->me, 'blog_rhythm_reminder_last', true ) );
		$this->mail_result = true;
		$this->assertSame( 1, Reminders::run( $this->now( '2026-05-13 10:00:00' ) ) );
		$this->assertSame( '2026-05-13', get_user_meta( $this->me, 'blog_rhythm_reminder_last', true ) );
	}

	public function test_resting_user_gets_nothing() {
		$this->enable( array(), array( 'resting' => true ) );
		$this->assertSame( 0, Reminders::run( $this->now() ) );
		$this->assertCount( 0, $this->sent );
	}

	public function test_email_off_gets_nothing() {
		$this->enable( array( 'email' => false ) );
		$this->assertSame( 0, Reminders::run( $this->now() ) );
		$this->assertCount( 0, $this->sent );
	}

	public function test_reminders_off_gets_nothing() {
		$this->enable();
		Settings::update( $this->me, array( 'reminders' => array( 'enabled' => false ) ) );
		$this->assertSame( 0, Reminders::run( $this->now() ) );
	}

	public function test_skip_if_done_gets_nothing_when_shown_up() {
		$this->enable( array( 'skip_if_done' => true ) );
		$this->post_on( '2026-05-12 10:00:00' );
		$this->assertSame( 0, Reminders::run( $this->now() ) );
		$this->assertCount( 0, $this->sent );
	}

	public function test_skip_if_done_off_still_sends() {
		$this->enable( array( 'skip_if_done' => false ) );
		$this->post_on( '2026-05-12 10:00:00' );
		$this->assertSame( 1, Reminders::run( $this->now() ) );
	}

	public function test_email_contains_turn_off_link_and_no_forbidden_words() {
		$this->enable();
		Reminders::run( $this->now() );
		$this->assertCount( 1, $this->sent );
		$mail = $this->sent[0];
		$user = get_userdata( $this->me );

		$this->assertSame( $user->user_email, is_array( $mail['to'] ) ? $mail['to'][0] : $mail['to'] );
		$this->assertSame( 'A note from Blog Rhythm', $mail['subject'] );
		$this->assertStringContainsString( 'Want to write a little?', $mail['message'] );
		$this->assertStringContainsString( Reminders::turn_off_url( $this->me ), $mail['message'] );
		$this->assertStringContainsString( "Turn off these reminders:\n", $mail['message'] );
		$haystack = strtolower( $mail['subject'] . ' ' . $mail['message'] );
		foreach ( array( 'missed', 'streak', 'behind', "\u{2014}" ) as $word ) {
			$this->assertStringNotContainsString( $word, $haystack );
		}
		$this->assertDoesNotMatchRegularExpression( '/\d+ (days?|posts?)/', $haystack );
	}

	public function test_valid_token_turns_reminders_off() {
		$this->enable();
		$token = $this->token_from_url( Reminders::turn_off_url( $this->me ) );
		$this->assertTrue( Reminders::verify_token( $this->me, $token ) );

		$this->assertTrue( Reminders::turn_off( $this->me, $token ) );
		$this->assertFalse( Settings::get( $this->me )['reminders']['enabled'] );
		$this->assertSame( '', get_user_meta( $this->me, Settings::REMINDERS_ON_KEY, true ) );
	}

	public function test_turn_off_url_shape() {
		$this->enable();
		$url = Reminders::turn_off_url( $this->me );
		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertStringContainsString( 'action=blog_rhythm_turn_off', $url );
		$this->assertStringContainsString( 'user=' . $this->me, $url );
		$this->assertStringContainsString( 'token=', $url );
	}

	public function test_tampered_or_old_token_is_rejected() {
		$this->enable();
		$token = $this->token_from_url( Reminders::turn_off_url( $this->me ) );
		$this->assertFalse( Reminders::verify_token( $this->me, $token . 'x' ) );
		$this->assertFalse( Reminders::verify_token( $this->me, '' ) );
		$this->assertFalse( Reminders::verify_token( $this->me + 1, $token ) );
		$this->assertFalse( Reminders::turn_off( $this->me, 'nope' ) );
		$this->assertTrue( Settings::get( $this->me )['reminders']['enabled'] );
	}

	public function test_missing_secret_rejects_everything() {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->assertFalse( Reminders::verify_token( $other, hash_hmac( 'sha256', (string) $other, '' ) ) );
	}

	public function test_token_invalid_after_reenabling() {
		$this->enable();
		$token = $this->token_from_url( Reminders::turn_off_url( $this->me ) );
		Settings::update( $this->me, array( 'reminders' => array( 'enabled' => false ) ) );
		Settings::update( $this->me, array( 'reminders' => array( 'enabled' => true ) ) );
		$this->assertFalse( Reminders::verify_token( $this->me, $token ) );
	}

	public function test_handler_shows_confirmation_page() {
		$this->enable();
		$token = $this->token_from_url( Reminders::turn_off_url( $this->me ) );
		add_filter( 'wp_die_handler', array( $this, 'die_handler' ) );

		$_GET['user']  = (string) $this->me;
		$_GET['token'] = $token;
		try {
			Reminders::handle_turn_off();
			$this->fail( 'Expected wp_die.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'Reminders are off.', $e->getMessage() );
		}
		$this->assertFalse( Settings::get( $this->me )['reminders']['enabled'] );

		$_GET['token'] = 'bad';
		try {
			Reminders::handle_turn_off();
			$this->fail( 'Expected wp_die.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'work anymore', $e->getMessage() );
		}
		unset( $_GET['user'], $_GET['token'] );
		remove_filter( 'wp_die_handler', array( $this, 'die_handler' ) );
	}

	/**
	 * Throwing wp_die handler.
	 *
	 * @return callable
	 */
	public function die_handler() {
		return static function ( $message ) {
			throw new WPDieException( is_string( $message ) ? $message : 'die' );
		};
	}

	public function test_note_due_only_when_widget_note_on() {
		$this->enable( array( 'widget_note' => false ) );
		$this->assertNull( Reminders::note_due( $this->me, $this->now() ) );

		$this->enable( array( 'widget_note' => true ) );
		$this->assertSame( 'Want to write a little?', Reminders::note_due( $this->me, $this->now() ) );
	}

	public function test_note_due_respects_day_hour_rest_and_shown_up() {
		$this->enable(
			array(
				'widget_note' => true,
				'email'       => false,
			)
		);
		$this->assertSame( 'Want to write a little?', Reminders::note_due( $this->me, $this->now( '2026-05-13 15:00:00' ) ) );
		$this->assertNull( Reminders::note_due( $this->me, $this->now( '2026-05-13 08:00:00' ) ) );
		$this->assertNull( Reminders::note_due( $this->me, $this->now( '2026-05-14 10:00:00' ) ) );

		$this->post_on( '2026-05-12 10:00:00' );
		$this->assertNull( Reminders::note_due( $this->me, $this->now() ) );
	}

	public function test_note_due_null_when_resting() {
		$this->enable( array( 'widget_note' => true ), array( 'resting' => true ) );
		$this->assertNull( Reminders::note_due( $this->me, $this->now() ) );
	}

	public function test_period_key_is_site_date() {
		$settings = Settings::sanitize( array() );
		$this->assertSame( '2026-05-13', Reminders::period_key( $settings, $this->now( '2026-05-13 21:00:00' ) ) );
	}

	/**
	 * Pull the token out of a turn-off URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function token_from_url( string $url ): string {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
		return (string) $args['token'];
	}
}
