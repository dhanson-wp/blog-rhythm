<?php
/**
 * REST routes for the widget and settings page.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the blog-rhythm/v1 routes. Every route acts on the current user only.
 */
class Rest {

	const NAMESPACE = 'blog-rhythm/v1';

	const DEFAULT_WEEKS = 26;

	const MAX_WEEKS = 53;

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$weeks = array(
			'weeks' => array(
				'type'              => 'integer',
				'default'           => self::DEFAULT_WEEKS,
				'sanitize_callback' => array( $this, 'clamp_weeks' ),
				'validate_callback' => '__return_true',
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/rhythm',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rhythm' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => $weeks,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_use' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_use' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/log',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_log' ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => $weeks,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_log' ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => $weeks,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/history/clear',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clear_history' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => $weeks,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_export' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
	}

	/**
	 * Permission check shared by every route.
	 *
	 * @return bool
	 */
	public function can_use(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Clamp the weeks argument to 1..53; non-numeric falls back to the default.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function clamp_weeks( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_WEEKS;
		}
		return max( 1, min( self::MAX_WEEKS, (int) $value ) );
	}

	/**
	 * GET /rhythm.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_rhythm( $request ) {
		return rest_ensure_response( $this->payload( $this->weeks_from( $request ) ) );
	}

	/**
	 * GET /settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::get( get_current_user_id() ) );
	}

	/**
	 * GET /export.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_export() {
		$data             = Privacy::export_data( get_current_user_id() );
		$data['activity'] = (object) $data['activity'];
		return rest_ensure_response( $data );
	}

	/**
	 * POST /settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'blog_rhythm_invalid_settings',
				__( 'Settings must be sent as an object.', 'blog-rhythm' ),
				array( 'status' => 400 )
			);
		}
		return rest_ensure_response( Settings::update( get_current_user_id(), $body ) );
	}

	/**
	 * POST /log.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add_log( $request ) {
		Log::add_today( get_current_user_id() );
		return rest_ensure_response( $this->payload( $this->weeks_from( $request ) ) );
	}

	/**
	 * DELETE /log.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function remove_log( $request ) {
		Log::remove_today( get_current_user_id() );
		return rest_ensure_response( $this->payload( $this->weeks_from( $request ) ) );
	}

	/**
	 * POST /history/clear.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function clear_history( $request ) {
		Log::clear( get_current_user_id() );
		return rest_ensure_response( $this->payload( $this->weeks_from( $request ) ) );
	}

	/**
	 * Read the weeks param from a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 */
	private function weeks_from( $request ): int {
		return $this->clamp_weeks( $request->get_param( 'weeks' ) ?? self::DEFAULT_WEEKS );
	}

	/**
	 * Build the /rhythm payload for the current user.
	 *
	 * @param int $weeks Weeks of history, 1..53.
	 * @return array
	 */
	private function payload( int $weeks ): array {
		$user_id  = get_current_user_id();
		$now      = current_datetime();
		$settings = Settings::get( $user_id );

		$week_start = new \DateTimeImmutable( Rhythm::period_bounds( 'week', $now )['start'], wp_timezone() );
		$from       = $week_start->modify( '-' . ( ( $weeks - 1 ) * 7 ) . ' days' )->format( 'Y-m-d' );
		$today      = $now->format( 'Y-m-d' );

		$days = Rhythm::counted_days( Activity::for_range( $user_id, $from, $today ), $settings['counts'] );

		return array(
			'settings' => $settings,
			'days'     => (object) $days,
			'summary'  => Rhythm::summary( $user_id, $now ),
			'today'    => $today,
			'note'     => $this->note_for( $user_id, $now ),
		);
	}

	/**
	 * Optional widget note.
	 *
	 * @param int                $user_id User ID.
	 * @param \DateTimeImmutable $now     Current moment.
	 * @return string|null
	 */
	private function note_for( int $user_id, \DateTimeImmutable $now ) {
		return Reminders::note_due( $user_id, $now );
	}
}
