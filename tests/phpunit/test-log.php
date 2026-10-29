<?php
/**
 * Manual log tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Activity;
use BlogRhythm\Log;

/**
 * Tests for BlogRhythm\Log.
 */
class Test_Log extends WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	public function test_add_then_remove_today() {
		$today = Log::today();
		$this->assertTrue( Log::add_today( $this->user_id ) );
		$this->assertSame( array( $today => array( 'idea' ) ), Log::entries( $this->user_id ) );
		$this->assertTrue( Log::remove_today( $this->user_id ) );
		$this->assertSame( array(), Log::entries( $this->user_id ) );
		$this->assertFalse( Log::remove_today( $this->user_id ) );
	}

	public function test_add_today_twice_stores_one_entry() {
		$this->assertTrue( Log::add_today( $this->user_id ) );
		$this->assertFalse( Log::add_today( $this->user_id ) );
		$this->assertCount( 1, Log::entries( $this->user_id ) );
	}

	public function test_junk_in_stored_meta_is_ignored() {
		update_user_meta(
			$this->user_id,
			Log::META_KEY,
			array(
				'entries'        => array(
					'2026-02-30' => array( 'idea' ),
					'nope'       => array( 'idea' ),
					'2026-02-10' => array( 'idea' ),
				),
				'cleared_before' => 'yesterday',
			)
		);
		$this->assertSame( array( '2026-02-10' => array( 'idea' ) ), Log::entries( $this->user_id ) );
		$this->assertSame( '', Log::cleared_before( $this->user_id ) );

		update_user_meta( $this->user_id, Log::META_KEY, 'garbage' );
		$this->assertSame( array(), Log::entries( $this->user_id ) );
	}

	public function test_clear_hides_earlier_passive_days_and_empties_entries() {
		$today     = Log::today();
		$yesterday = ( new DateTimeImmutable( $today, wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' );

		self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
				'post_date'   => $yesterday . ' 10:00:00',
			)
		);
		update_user_meta(
			$this->user_id,
			Log::META_KEY,
			array(
				'entries'        => array( $yesterday => array( 'idea' ) ),
				'cleared_before' => '',
			)
		);
		$this->assertArrayHasKey( $yesterday, Activity::for_range( $this->user_id, $yesterday, $today ) );

		Log::clear( $this->user_id );

		$this->assertSame( array(), Log::entries( $this->user_id ) );
		$this->assertSame( $today, Log::cleared_before( $this->user_id ) );
		$this->assertSame( array(), Activity::for_range( $this->user_id, $yesterday, $today ) );
	}
}
