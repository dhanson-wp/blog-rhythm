<?php
/**
 * Rhythm math tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Rhythm;
use BlogRhythm\Settings;

/**
 * Tests for BlogRhythm\Rhythm.
 */
class Test_Rhythm extends WP_UnitTestCase {

	/**
	 * The writer.
	 *
	 * @var int
	 */
	private $me;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'start_of_week', 1 );
		$this->me = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	private function now( string $when = '2026-05-13 12:00:00' ): DateTimeImmutable {
		return new DateTimeImmutable( $when, wp_timezone() );
	}

	private function post( string $date, array $args = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_author' => $this->me,
					'post_status' => 'publish',
					'post_date'   => $date,
				),
				$args
			)
		);
	}

	private function counts( array $override = array() ): array {
		return array_merge(
			array(
				'publish' => true,
				'draft'   => true,
				'idea'    => true,
				'edit'    => false,
			),
			$override
		);
	}

	public function test_one_day_with_many_activities_is_one_show_up() {
		$days = Rhythm::counted_days(
			array(
				'2026-05-10' => array( 'idea', 'drafted', 'published', 'updated' ),
				'2026-05-11' => array( 'idea', 'drafted' ),
				'2026-05-12' => array( 'idea' ),
			),
			$this->counts()
		);
		$this->assertSame(
			array(
				'2026-05-10' => 'published',
				'2026-05-11' => 'drafted',
				'2026-05-12' => 'idea',
			),
			$days
		);
	}

	public function test_updated_only_counts_when_edit_enabled() {
		$activity = array( '2026-05-10' => array( 'updated' ) );
		$this->assertSame( array(), Rhythm::counted_days( $activity, $this->counts() ) );
		$this->assertSame(
			array( '2026-05-10' => 'drafted' ),
			Rhythm::counted_days( $activity, $this->counts( array( 'edit' => true ) ) )
		);
	}

	public function test_idea_excluded_when_counts_idea_false() {
		$activity = array(
			'2026-05-10' => array( 'idea' ),
			'2026-05-11' => array( 'idea', 'drafted' ),
		);
		$this->assertSame(
			array( '2026-05-11' => 'drafted' ),
			Rhythm::counted_days( $activity, $this->counts( array( 'idea' => false ) ) )
		);
	}

	public function test_week_bounds_respect_start_of_week() {
		$now = $this->now(); // Wednesday.
		$this->assertSame(
			array(
				'start' => '2026-05-11',
				'end'   => '2026-05-17',
			),
			Rhythm::period_bounds( 'week', $now )
		);

		update_option( 'start_of_week', 0 );
		$this->assertSame(
			array(
				'start' => '2026-05-10',
				'end'   => '2026-05-16',
			),
			Rhythm::period_bounds( 'week', $now )
		);

		update_option( 'start_of_week', 6 );
		$this->assertSame(
			array(
				'start' => '2026-05-09',
				'end'   => '2026-05-15',
			),
			Rhythm::period_bounds( 'week', $now )
		);

		// On the start day itself.
		update_option( 'start_of_week', 3 );
		$this->assertSame( '2026-05-13', Rhythm::period_bounds( 'week', $now )['start'] );
	}

	public function test_month_bounds() {
		$this->assertSame(
			array(
				'start' => '2026-02-01',
				'end'   => '2026-02-28',
			),
			Rhythm::period_bounds( 'month', $this->now( '2026-02-14 08:00:00' ) )
		);
		$this->assertSame(
			array(
				'start' => '2028-02-01',
				'end'   => '2028-02-29',
			),
			Rhythm::period_bounds( 'month', $this->now( '2028-02-29 23:30:00' ) )
		);
		$this->assertSame(
			array(
				'start' => '2026-05-13',
				'end'   => '2026-05-13',
			),
			Rhythm::period_bounds( 'day', $this->now() )
		);
	}

	public function test_period_mapping() {
		$this->assertSame( 'week', Rhythm::summary_period( 'daily' ) );
		$this->assertSame( 'week', Rhythm::summary_period( 'weekly' ) );
		$this->assertSame( 'week', Rhythm::summary_period( 'open' ) );
		$this->assertSame( 'month', Rhythm::summary_period( 'monthly' ) );
		$this->assertSame( 'day', Rhythm::reminder_period( 'daily' ) );
		$this->assertSame( 'week', Rhythm::reminder_period( 'weekly' ) );
		$this->assertSame( 'week', Rhythm::reminder_period( 'open' ) );
		$this->assertSame( 'month', Rhythm::reminder_period( 'monthly' ) );
	}

	public function test_summary_for_open_pace_has_zero_target() {
		Settings::update( $this->me, array( 'pace' => 'open', 'target' => 5 ) );
		$this->post( '2026-05-12 10:00:00' );

		$s = Rhythm::summary( $this->me, $this->now() );
		$this->assertSame( 0, $s['target'] );
		$this->assertSame( 1, $s['count'] );
		$this->assertSame( 'open', $s['pace'] );
		$this->assertSame( 'week', $s['period'] );
		$this->assertFalse( $s['resting'] );
		$this->assertSame( '', $s['rest_until'] );
	}

	public function test_summary_counts_only_through_today_and_period() {
		Settings::update( $this->me, array( 'pace' => 'weekly', 'target' => 3 ) );
		$this->post( '2026-05-10 10:00:00' ); // Previous week.
		$this->post( '2026-05-11 10:00:00' );
		$this->post( '2026-05-13 09:00:00' );
		$this->post( '2026-05-15 09:00:00', array( 'post_status' => 'future' ) ); // Future day.

		$s = Rhythm::summary( $this->me, $this->now() );
		$this->assertSame( 2, $s['count'] );
		$this->assertSame( 3, $s['target'] );
		$this->assertSame( 'weekly', $s['pace'] );
	}

	public function test_summary_reports_resting() {
		Settings::update( $this->me, array( 'resting' => true, 'rest_until' => '2026-05-20' ) );
		$s = Rhythm::summary( $this->me, $this->now() );
		$this->assertTrue( $s['resting'] );
		$this->assertSame( '2026-05-20', $s['rest_until'] );
	}

	public function test_has_shown_up_uses_day_for_daily_and_week_for_weekly() {
		$this->post( '2026-05-12 10:00:00' ); // Yesterday.

		Settings::update( $this->me, array( 'pace' => 'daily' ) );
		$this->assertFalse( Rhythm::has_shown_up( $this->me, $this->now() ) );

		Settings::update( $this->me, array( 'pace' => 'weekly' ) );
		$this->assertTrue( Rhythm::has_shown_up( $this->me, $this->now() ) );

		$this->post( '2026-05-13 08:00:00' );
		Settings::update( $this->me, array( 'pace' => 'daily' ) );
		$this->assertTrue( Rhythm::has_shown_up( $this->me, $this->now() ) );
	}

	public function test_has_shown_up_converts_now_to_site_time() {
		$this->post( '2026-05-12 23:30:00' );
		Settings::update( $this->me, array( 'pace' => 'daily' ) );
		// 03:00 UTC on the 13th is still the 12th in New York.
		$utc = new DateTimeImmutable( '2026-05-13 03:00:00', new DateTimeZone( 'UTC' ) );
		$this->assertTrue( Rhythm::has_shown_up( $this->me, $utc ) );
	}

	public function test_over_target_reports_actual_count_without_flag() {
		Settings::update( $this->me, array( 'pace' => 'weekly', 'target' => 1 ) );
		$this->post( '2026-05-11 10:00:00' );
		$this->post( '2026-05-12 10:00:00' );
		$this->post( '2026-05-13 10:00:00' );

		$s = Rhythm::summary( $this->me, $this->now() );
		$this->assertSame( 3, $s['count'] );
		$this->assertSame( 1, $s['target'] );
		$this->assertSame(
			array( 'count', 'target', 'pace', 'period', 'resting', 'rest_until' ),
			array_keys( $s )
		);
	}
}
