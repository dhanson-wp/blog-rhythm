<?php
/**
 * Settings service tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Settings;

/**
 * Tests for BlogRhythm\Settings.
 */
class Test_Settings extends WP_UnitTestCase {

	public function test_defaults_match_spec() {
		$d = Settings::defaults();
		$this->assertSame( 'daily', $d['pace'] );
		$this->assertSame( 3, $d['target'] );
		$this->assertSame(
			array(
				'publish' => true,
				'draft'   => true,
				'idea'    => true,
				'edit'    => false,
			),
			$d['counts']
		);
		$this->assertSame( 'Write a little, most days.', $d['intention'] );
		$this->assertFalse( $d['resting'] );
		$this->assertSame( '', $d['rest_until'] );
		$this->assertFalse( $d['reminders']['enabled'] );
		$this->assertSame( 'mon', $d['reminders']['day'] );
		$this->assertSame( 9, $d['reminders']['hour'] );
		$this->assertTrue( $d['reminders']['email'] );
		$this->assertFalse( $d['reminders']['widget_note'] );
		$this->assertTrue( $d['reminders']['skip_if_done'] );
		$this->assertSame( 'Your rhythm is here whenever you are. Want to write a little?', $d['reminders']['message'] );
		$this->assertSame( 'auto', $d['look'] );
		$this->assertTrue( $d['show_summary'] );
		$this->assertTrue( $d['show_log_button'] );
	}

	public function test_sanitize_clamps_target() {
		$this->assertSame(
			1,
			Settings::sanitize(
				array(
					'pace'   => 'weekly',
					'target' => 0,
				)
			)['target']
		);
		$this->assertSame(
			7,
			Settings::sanitize(
				array(
					'pace'   => 'weekly',
					'target' => 99,
				)
			)['target']
		);
		$this->assertSame( 3, Settings::sanitize( array( 'target' => 'abc' ) )['target'] );
		$this->assertSame(
			20,
			Settings::sanitize(
				array(
					'pace'   => 'monthly',
					'target' => 20,
				)
			)['target']
		);
		$this->assertSame(
			31,
			Settings::sanitize(
				array(
					'pace'   => 'monthly',
					'target' => 99,
				)
			)['target']
		);
		$this->assertSame(
			7,
			Settings::sanitize(
				array(
					'pace'   => 'open',
					'target' => 99,
				)
			)['target']
		);
	}

	public function test_sanitize_strips_html_and_truncates_intention() {
		$out = Settings::sanitize( array( 'intention' => '<b>Hello</b> <script>alert(1)</script>world' ) );
		$this->assertStringNotContainsString( '<', $out['intention'] );
		$this->assertStringContainsString( 'Hello', $out['intention'] );

		$out = Settings::sanitize( array( 'intention' => str_repeat( 'a', 200 ) ) );
		$this->assertSame( 80, mb_strlen( $out['intention'] ) );

		$out = Settings::sanitize( array( 'intention' => '' ) );
		$this->assertSame( '', $out['intention'] );

		$out = Settings::sanitize( array( 'reminders' => array( 'message' => str_repeat( 'é', 300 ) ) ) );
		$this->assertSame( 120, mb_strlen( $out['reminders']['message'] ) );

		$out = Settings::sanitize( array( 'reminders' => array( 'message' => '' ) ) );
		$this->assertSame( Settings::defaults()['reminders']['message'], $out['reminders']['message'] );
	}

	public function test_sanitize_drops_unknown_keys_and_bad_enums() {
		$out = Settings::sanitize(
			array(
				'pace'      => 'hourly',
				'look'      => 'neon',
				'evil'      => 'x',
				'counts'    => array(
					'edit' => '1',
					'zzz'  => true,
				),
				'reminders' => array(
					'day'   => 'tue',
					'hour'  => 99,
					'extra' => 1,
				),
			)
		);
		$this->assertSame( 'daily', $out['pace'] );
		$this->assertSame( 'auto', $out['look'] );
		$this->assertArrayNotHasKey( 'evil', $out );
		$this->assertArrayNotHasKey( 'zzz', $out['counts'] );
		$this->assertTrue( $out['counts']['edit'] );
		$this->assertSame( 'mon', $out['reminders']['day'] );
		$this->assertSame( 23, $out['reminders']['hour'] );
		$this->assertArrayNotHasKey( 'extra', $out['reminders'] );
		$this->assertSame( array_keys( Settings::defaults() ), array_keys( $out ) );
	}

	public function test_sanitize_rejects_invalid_rest_until() {
		$this->assertSame( '', Settings::sanitize( array( 'rest_until' => '2026-02-30' ) )['rest_until'] );
		$this->assertSame( '', Settings::sanitize( array( 'rest_until' => 'tomorrow' ) )['rest_until'] );
		$this->assertSame( '2026-03-04', Settings::sanitize( array( 'rest_until' => '2026-03-04' ) )['rest_until'] );
	}

	public function test_update_persists_and_merges_over_defaults() {
		$user = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->assertSame( Settings::defaults(), Settings::get( $user ) );

		$stored = Settings::update(
			$user,
			array(
				'pace'      => 'weekly',
				'reminders' => array( 'hour' => 7 ),
			)
		);
		$this->assertSame( 'weekly', $stored['pace'] );
		$this->assertSame( 7, $stored['reminders']['hour'] );
		$this->assertSame( 'mon', $stored['reminders']['day'] );

		// Partial update keeps previous values.
		Settings::update( $user, array( 'intention' => 'Hi' ) );
		$got = Settings::get( $user );
		$this->assertSame( 'weekly', $got['pace'] );
		$this->assertSame( 7, $got['reminders']['hour'] );
		$this->assertSame( 'Hi', $got['intention'] );
	}

	public function test_enabling_reminders_sets_flag_and_rotates_secret() {
		$user = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->assertSame( '', get_user_meta( $user, 'blog_rhythm_reminders_on', true ) );

		Settings::update( $user, array( 'reminders' => array( 'enabled' => true ) ) );
		$this->assertSame( '1', get_user_meta( $user, 'blog_rhythm_reminders_on', true ) );
		$first = get_user_meta( $user, 'blog_rhythm_reminder_secret', true );
		$this->assertSame( 32, strlen( $first ) );

		// Still on: no rotation.
		Settings::update(
			$user,
			array(
				'reminders' => array(
					'enabled' => true,
					'hour'    => 8,
				),
			)
		);
		$this->assertSame( $first, get_user_meta( $user, 'blog_rhythm_reminder_secret', true ) );

		// Off: flag deleted, secret kept.
		Settings::update( $user, array( 'reminders' => array( 'enabled' => false ) ) );
		$this->assertSame( '', get_user_meta( $user, 'blog_rhythm_reminders_on', true ) );
		$this->assertSame( $first, get_user_meta( $user, 'blog_rhythm_reminder_secret', true ) );

		// Back on: rotated.
		Settings::update( $user, array( 'reminders' => array( 'enabled' => true ) ) );
		$second = get_user_meta( $user, 'blog_rhythm_reminder_secret', true );
		$this->assertSame( 32, strlen( $second ) );
		$this->assertNotSame( $first, $second );
	}

	public function test_is_resting_respects_rest_until() {
		$now      = new DateTimeImmutable( '2026-05-10 12:00:00', new DateTimeZone( 'America/New_York' ) );
		$settings = Settings::defaults();

		$this->assertFalse( Settings::is_resting( $settings, $now ) );

		$settings['resting'] = true;
		$this->assertTrue( Settings::is_resting( $settings, $now ) );

		$settings['rest_until'] = '2026-05-09';
		$this->assertFalse( Settings::is_resting( $settings, $now ) );

		$settings['rest_until'] = '2026-05-10';
		$this->assertTrue( Settings::is_resting( $settings, $now ) );

		$settings['rest_until'] = '2026-05-11';
		$this->assertTrue( Settings::is_resting( $settings, $now ) );
	}
}
