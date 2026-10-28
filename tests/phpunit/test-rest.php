<?php
/**
 * REST route tests.
 *
 * @package BlogRhythm
 */

use BlogRhythm\Log;
use BlogRhythm\Settings;

/**
 * Tests for BlogRhythm\Rest.
 */
class Test_Rest extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $this->user_id );
	}

	/**
	 * Dispatch a request.
	 *
	 * @param string     $method Method.
	 * @param string     $route  Route under blog-rhythm/v1.
	 * @param array|null $json   JSON body.
	 * @param array      $params Query params.
	 * @return WP_REST_Response
	 */
	private function request( string $method, string $route, $json = null, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/blog-rhythm/v1' . $route );
		if ( null !== $json ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $json ) );
		}
		$request->set_query_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	private function routes(): array {
		return array(
			array( 'GET', '/rhythm' ),
			array( 'GET', '/settings' ),
			array( 'POST', '/settings' ),
			array( 'POST', '/log' ),
			array( 'DELETE', '/log' ),
			array( 'POST', '/history/clear' ),
			array( 'GET', '/export' ),
		);
	}

	public function test_subscriber_gets_403_on_every_route() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		foreach ( $this->routes() as list( $method, $route ) ) {
			$this->assertSame( 403, $this->request( $method, $route, array() )->get_status(), "$method $route" );
		}
	}

	public function test_logged_out_gets_401() {
		wp_set_current_user( 0 );
		foreach ( $this->routes() as list( $method, $route ) ) {
			$this->assertSame( 401, $this->request( $method, $route, array() )->get_status(), "$method $route" );
		}
	}

	public function test_settings_post_sanitizes_payload() {
		$response = $this->request(
			'POST',
			'/settings',
			array(
				'target'    => 99,
				'intention' => '<script>alert(1)</script>Hello ' . str_repeat( 'x', 100 ),
				'bogus'     => 'nope',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 7, $data['target'] );
		$this->assertStringNotContainsString( '<', $data['intention'] );
		$this->assertLessThanOrEqual( 80, mb_strlen( $data['intention'] ) );
		$this->assertArrayNotHasKey( 'bogus', $data );

		$stored = get_user_meta( $this->user_id, Settings::META_KEY, true );
		$this->assertSame( 7, $stored['target'] );
		$this->assertArrayNotHasKey( 'bogus', $stored );

		$this->assertSame( $data, $this->request( 'GET', '/settings' )->get_data() );
	}

	public function test_settings_post_rejects_non_object_body() {
		$request = new WP_REST_Request( 'POST', '/blog-rhythm/v1/settings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( '"nope"' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'blog_rhythm_invalid_settings', $response->get_data()['code'] );
	}

	public function test_rhythm_returns_only_current_users_days() {
		$today = Log::today();
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create(
			array(
				'post_author' => $other,
				'post_status' => 'publish',
				'post_date'   => $today . ' 09:00:00',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'draft',
				'post_date'   => $today . ' 09:00:00',
			)
		);

		$data = $this->request( 'GET', '/rhythm' )->get_data();
		$this->assertSame( array( $today => 'drafted' ), (array) $data['days'] );
		$this->assertSame( $today, $data['today'] );
		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayHasKey( 'summary', $data );
		$this->assertArrayHasKey( 'note', $data );
		$this->assertNull( $data['note'] );
		$this->assertSame( (int) get_option( 'start_of_week' ), $data['week_start'] );
		update_option( 'start_of_week', 1 );
		$this->assertSame( 1, $this->request( 'GET', '/rhythm' )->get_data()['week_start'] );
	}

	public function test_rhythm_for_new_user_is_empty_not_error() {
		$response = $this->request( 'GET', '/rhythm' );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( array(), (array) $data['days'] );
		$this->assertSame( 0, $data['summary']['count'] );
		$this->assertSame( '{}', wp_json_encode( $data['days'] ) );
	}

	public function test_rhythm_request_scans_posts_once() {
		Settings::update(
			$this->user_id,
			array(
				'reminders' => array(
					'enabled'     => true,
					'day'         => 'daily',
					'hour'        => 0,
					'widget_note' => true,
				),
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
				'post_date'   => Log::today() . ' 00:30:00',
			)
		);
		\BlogRhythm\Activity::flush();
		$scans   = 0;
		$capture = static function ( $sql ) use ( &$scans ) {
			if ( false !== strpos( $sql, "post_type = 'post' AND post_author" ) ) {
				++$scans;
			}
			return $sql;
		};
		add_filter( 'query', $capture );
		$this->request( 'GET', '/rhythm' );
		remove_filter( 'query', $capture );
		$this->assertSame( 1, $scans );
	}

	public function test_rhythm_reports_whether_settings_were_ever_saved() {
		$this->assertFalse( $this->request( 'GET', '/rhythm' )->get_data()['configured'] );
		$this->assertFalse( $this->request( 'POST', '/log' )->get_data()['configured'] );

		// Saving the defaults unchanged still counts as setting up.
		$this->request( 'POST', '/settings', Settings::defaults() );
		$this->assertTrue( $this->request( 'GET', '/rhythm' )->get_data()['configured'] );
		$this->assertTrue( $this->request( 'DELETE', '/log' )->get_data()['configured'] );
		$this->assertTrue( $this->request( 'POST', '/history/clear' )->get_data()['configured'] );
	}

	public function test_finished_rest_reads_as_not_resting() {
		update_user_meta(
			$this->user_id,
			Settings::META_KEY,
			array_merge(
				Settings::defaults(),
				array(
					'resting'    => true,
					'rest_until' => '2020-01-01',
				)
			)
		);
		$data = $this->request( 'GET', '/settings' )->get_data();
		$this->assertFalse( $data['resting'] );
		$this->assertSame( '', $data['rest_until'] );
		$this->assertFalse( $this->request( 'GET', '/rhythm' )->get_data()['settings']['resting'] );

		$future = gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS );
		$data   = $this->request(
			'POST',
			'/settings',
			array(
				'resting'    => true,
				'rest_until' => $future,
			)
		)->get_data();
		$this->assertTrue( $data['resting'] );
		$this->assertSame( $future, $data['rest_until'] );
	}

	public function test_log_post_then_delete_roundtrip() {
		$today = Log::today();

		$data = $this->request( 'POST', '/log' )->get_data();
		$this->assertSame( array( $today => 'idea' ), (array) $data['days'] );
		$this->assertSame( 1, $data['summary']['count'] );

		$data = $this->request( 'DELETE', '/log' )->get_data();
		$this->assertSame( array(), (array) $data['days'] );
		$this->assertSame( 0, $data['summary']['count'] );
	}

	public function test_weeks_param_clamped() {
		foreach ( array(
			'0'   => 1,
			'999' => 53,
			'abc' => 26,
			'-4'  => 1,
		) as $input => $weeks ) {
			$response = $this->request( 'GET', '/rhythm', null, array( 'weeks' => $input ) );
			$this->assertSame( 200, $response->get_status(), $input );
		}

		$rest = new BlogRhythm\Rest();
		$this->assertSame( 1, $rest->clamp_weeks( '0' ) );
		$this->assertSame( 53, $rest->clamp_weeks( '999' ) );
		$this->assertSame( 26, $rest->clamp_weeks( 'abc' ) );
		$this->assertSame( 12, $rest->clamp_weeks( '12' ) );
	}

	public function test_weeks_range_covers_expected_start() {
		$today = Log::today();
		$old   = ( new DateTimeImmutable( $today, wp_timezone() ) )->modify( '-40 days' )->format( 'Y-m-d' );
		update_user_meta(
			$this->user_id,
			Log::META_KEY,
			array(
				'entries'        => array( $old => array( 'idea' ) ),
				'cleared_before' => '',
			)
		);

		$one = $this->request( 'GET', '/rhythm', null, array( 'weeks' => 1 ) )->get_data();
		$this->assertSame( array(), (array) $one['days'] );
		$all = $this->request( 'GET', '/rhythm', null, array( 'weeks' => 53 ) )->get_data();
		$this->assertArrayHasKey( $old, (array) $all['days'] );
	}

	public function test_clear_history_hides_earlier_days() {
		$today     = Log::today();
		$yesterday = ( new DateTimeImmutable( $today, wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' );
		update_user_meta(
			$this->user_id,
			Log::META_KEY,
			array(
				'entries'        => array( $yesterday => array( 'idea' ) ),
				'cleared_before' => '',
			)
		);

		$before = $this->request( 'GET', '/rhythm' )->get_data();
		$this->assertArrayHasKey( $yesterday, (array) $before['days'] );

		$response = $this->request( 'POST', '/history/clear' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( $yesterday, (array) $response->get_data()['days'] );
		$this->assertArrayNotHasKey( $yesterday, (array) $this->request( 'GET', '/rhythm' )->get_data()['days'] );
	}
}
