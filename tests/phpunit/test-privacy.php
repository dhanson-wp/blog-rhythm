<?php
/**
 * Privacy export, eraser, and uninstall tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Log;
use BlogRhythm\Privacy;
use BlogRhythm\Settings;

/**
 * Tests for BlogRhythm\Privacy and uninstall.php.
 */
class Test_Privacy extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		$this->user_id = self::factory()->user->create(
			array(
				'role'       => 'author',
				'user_email' => 'writer@example.org',
			)
		);
	}

	private function seed_all_meta( int $user_id ): void {
		foreach ( Privacy::META_KEYS as $key ) {
			update_user_meta( $user_id, $key, 'x' );
		}
	}

	public function test_export_includes_settings_log_and_visible_activity() {
		Settings::update( $this->user_id, array( 'target' => 5 ) );
		Log::add_today( $this->user_id );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
				'post_date'   => '2026-03-10 09:00:00',
			)
		);

		$data = Privacy::export_data( $this->user_id );

		$this->assertSame( Settings::get( $this->user_id ), $data['settings'] );
		$this->assertSame( 5, $data['settings']['target'] );
		$this->assertSame( array( Log::today() => array( 'idea' ) ), $data['log']['entries'] );
		$this->assertSame( '', $data['log']['cleared_before'] );
		$this->assertArrayHasKey( '2026-03-10', $data['activity'] );
		$this->assertContains( 'published', $data['activity']['2026-03-10'] );
		$this->assertArrayHasKey( Log::today(), $data['activity'] );
		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_export_excludes_activity_before_cleared_before() {
		self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
				'post_date'   => '2020-01-05 09:00:00',
			)
		);
		Log::clear( $this->user_id );
		$today = Log::today();
		self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$data = Privacy::export_data( $this->user_id );

		$this->assertSame( $today, $data['log']['cleared_before'] );
		$this->assertArrayNotHasKey( '2020-01-05', $data['activity'] );
		$this->assertArrayHasKey( $today, $data['activity'] );
	}

	public function test_export_never_includes_other_users() {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create(
			array(
				'post_author' => $other,
				'post_status' => 'publish',
				'post_date'   => '2026-02-02 09:00:00',
			)
		);
		Log::add_today( $other );
		Settings::update( $other, array( 'intention' => 'Other persons intention' ) );

		$data = Privacy::export_data( $this->user_id );

		$this->assertSame( array(), $data['log']['entries'] );
		$this->assertSame( array(), $data['activity'] );
		$this->assertStringNotContainsString( 'Other persons intention', wp_json_encode( $data ) );

		$groups = Privacy::export_personal_data( 'writer@example.org' );
		$this->assertStringNotContainsString( 'Other persons intention', wp_json_encode( $groups ) );
		$this->assertStringNotContainsString( '2026-02-02', wp_json_encode( $groups ) );
	}

	public function test_wordpress_exporter_returns_readable_group() {
		Log::add_today( $this->user_id );
		self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
				'post_date'   => '2026-03-10 09:00:00',
			)
		);

		$result = Privacy::export_personal_data( 'writer@example.org' );

		$this->assertTrue( $result['done'] );
		foreach ( $result['data'] as $item ) {
			$this->assertSame( 'blog-rhythm', $item['group_id'] );
			$this->assertSame( 'Blog Rhythm', $item['group_label'] );
		}
		$ids = wp_list_pluck( $result['data'], 'item_id' );
		$this->assertContains( 'blog-rhythm-settings', $ids );
		$this->assertContains( 'blog-rhythm-log', $ids );
		$this->assertContains( 'blog-rhythm-activity-2026-03-10', $ids );

		$unknown = Privacy::export_personal_data( 'nobody@example.org' );
		$this->assertSame( array(), $unknown['data'] );
		$this->assertTrue( $unknown['done'] );
	}

	public function test_register_adds_exporter_and_eraser() {
		( new Privacy() )->register();
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( 'blog-rhythm', $exporters );
		$this->assertArrayHasKey( 'blog-rhythm', $erasers );
		$this->assertTrue( is_callable( $exporters['blog-rhythm']['callback'] ) );
		$this->assertTrue( is_callable( $erasers['blog-rhythm']['callback'] ) );
	}

	public function test_eraser_removes_all_plugin_meta() {
		$this->seed_all_meta( $this->user_id );
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->seed_all_meta( $other );

		$result = Privacy::erase_personal_data( 'writer@example.org' );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertSame( array(), $result['messages'] );
		$this->assertTrue( $result['done'] );
		foreach ( Privacy::META_KEYS as $key ) {
			$this->assertSame( '', get_user_meta( $this->user_id, $key, true ), $key );
			$this->assertSame( 'x', get_user_meta( $other, $key, true ), $key );
		}

		$again = Privacy::erase_personal_data( 'writer@example.org' );
		$this->assertFalse( $again['items_removed'] );

		$unknown = Privacy::erase_personal_data( 'nobody@example.org' );
		$this->assertFalse( $unknown['items_removed'] );
		$this->assertTrue( $unknown['done'] );
	}

	public function test_uninstall_removes_meta_for_all_users_and_cron_event() {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->seed_all_meta( $this->user_id );
		$this->seed_all_meta( $other );
		wp_schedule_event( time() + 3600, 'hourly', 'blog_rhythm_send_reminders' );
		$this->assertNotFalse( wp_next_scheduled( 'blog_rhythm_send_reminders' ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'blog-rhythm/blog-rhythm.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		foreach ( array( $this->user_id, $other ) as $uid ) {
			foreach ( Privacy::META_KEYS as $key ) {
				$this->assertSame( '', get_user_meta( $uid, $key, true ), "$uid $key" );
			}
		}
		$this->assertFalse( wp_next_scheduled( 'blog_rhythm_send_reminders' ) );
	}

	public function test_export_route_returns_current_user_data_only() {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		Log::add_today( $other );
		wp_set_current_user( $this->user_id );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/blog-rhythm/v1/export' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( array( 'settings', 'log', 'activity' ), array_keys( $data ) );
		$this->assertIsObject( $data['activity'] );
		$this->assertSame( array(), $data['log']['entries'] );
	}
}
