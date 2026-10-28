<?php
/**
 * Personal data export and erasure.
 *
 * @package BlogRhythm
 */

namespace BlogRhythm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into the WordPress privacy tools and builds the export payload.
 */
class Privacy {

	const SLUG = 'blog-rhythm';

	/**
	 * Every user meta key the plugin writes. uninstall.php keeps a copy of this list.
	 */
	const META_KEYS = array(
		'blog_rhythm_settings',
		'blog_rhythm_log',
		'blog_rhythm_reminders_on',
		'blog_rhythm_reminder_secret',
		'blog_rhythm_reminder_last',
	);

	/**
	 * Hook registration.
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function add_exporter( $exporters ) {
		$exporters[ self::SLUG ] = array(
			'exporter_friendly_name' => __( 'Blog Rhythm', 'blog-rhythm' ),
			'callback'               => array( self::class, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function add_eraser( $erasers ) {
		$erasers[ self::SLUG ] = array(
			'eraser_friendly_name' => __( 'Blog Rhythm', 'blog-rhythm' ),
			'callback'             => array( self::class, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Everything the plugin holds or derives for one user. Never reads another user's data.
	 *
	 * @param int $user_id User ID.
	 * @return array{settings: array, log: array, activity: array}
	 */
	public static function export_data( int $user_id ): array {
		return array(
			'settings' => Settings::get( $user_id ),
			'log'      => array(
				'entries'        => Log::entries( $user_id ),
				'cleared_before' => Log::cleared_before( $user_id ),
			),
			'activity' => Activity::for_range( $user_id, '1970-01-01', Log::today() ),
		);
	}

	/**
	 * WordPress personal data exporter callback.
	 *
	 * @param string $email_address Email address of the person.
	 * @param int    $page          Page number (single page).
	 * @return array
	 */
	public static function export_personal_data( $email_address, $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', (string) $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data  = self::export_data( $user->ID );
		$items = array();
		$label = __( 'Blog Rhythm', 'blog-rhythm' );

		$settings_rows = array();
		foreach ( self::flatten( $data['settings'] ) as $name => $value ) {
			$settings_rows[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}
		$items[] = array(
			'group_id'    => self::SLUG,
			'group_label' => $label,
			'item_id'     => 'blog-rhythm-settings',
			'data'        => $settings_rows,
		);

		$log_rows = array();
		foreach ( $data['log']['entries'] as $date => $types ) {
			$log_rows[] = array(
				'name'  => $date,
				'value' => implode( ', ', $types ),
			);
		}
		if ( '' !== $data['log']['cleared_before'] ) {
			$log_rows[] = array(
				'name'  => __( 'History cleared before', 'blog-rhythm' ),
				'value' => $data['log']['cleared_before'],
			);
		}
		$items[] = array(
			'group_id'    => self::SLUG,
			'group_label' => $label,
			'item_id'     => 'blog-rhythm-log',
			'data'        => $log_rows,
		);

		foreach ( $data['activity'] as $date => $types ) {
			$items[] = array(
				'group_id'    => self::SLUG,
				'group_label' => $label,
				'item_id'     => 'blog-rhythm-activity-' . $date,
				'data'        => array(
					array(
						/* translators: %s: date, Y-m-d. */
						'name'  => sprintf( __( 'Activity on %s', 'blog-rhythm' ), $date ),
						'value' => implode( ', ', $types ),
					),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * WordPress personal data eraser callback.
	 *
	 * @param string $email_address Email address of the person.
	 * @param int    $page          Page number (single page).
	 * @return array
	 */
	public static function erase_personal_data( $email_address, $page = 1 ): array {
		unset( $page );
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
		$user   = get_user_by( 'email', (string) $email_address );
		if ( ! $user ) {
			return $result;
		}
		foreach ( self::META_KEYS as $key ) {
			if ( metadata_exists( 'user', $user->ID, $key ) ) {
				delete_user_meta( $user->ID, $key );
				$result['items_removed'] = true;
			}
		}
		return $result;
	}

	/**
	 * Flatten settings into readable name/value pairs; nested arrays become JSON.
	 *
	 * @param array $settings Settings.
	 * @return array<string, string>
	 */
	private static function flatten( array $settings ): array {
		$out = array();
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$out[ $key ] = wp_json_encode( $value );
			} elseif ( is_bool( $value ) ) {
				$out[ $key ] = $value ? 'true' : 'false';
			} else {
				$out[ $key ] = (string) $value;
			}
		}
		return $out;
	}
}
