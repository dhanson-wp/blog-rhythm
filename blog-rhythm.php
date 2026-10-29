<?php
/**
 * Plugin Name:       Blog Rhythm
 * Description:       A private, per-writer dashboard widget that shows your own writing rhythm.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      7.4
 * Author:            Derek Hanson
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blog-rhythm
 *
 * @package BlogRhythm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLOG_RHYTHM_VERSION', '1.0.0' );
define( 'BLOG_RHYTHM_FILE', __FILE__ );
define( 'BLOG_RHYTHM_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOG_RHYTHM_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'BlogRhythm\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = BLOG_RHYTHM_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Activation stub.
 */
function blog_rhythm_activate() {
	if ( ! wp_next_scheduled( 'blog_rhythm_send_reminders' ) ) {
		wp_schedule_event( time(), 'hourly', 'blog_rhythm_send_reminders' );
	}
}
register_activation_hook( __FILE__, 'blog_rhythm_activate' );

/**
 * Deactivation stub.
 */
function blog_rhythm_deactivate() {
	wp_clear_scheduled_hook( 'blog_rhythm_send_reminders' );
}
register_deactivation_hook( __FILE__, 'blog_rhythm_deactivate' );

/**
 * Wire up hooks.
 */
function blog_rhythm_init() {
	( new BlogRhythm\Dashboard_Widget() )->register();
	( new BlogRhythm\Settings_Page() )->register();
	( new BlogRhythm\Rest() )->register();
	( new BlogRhythm\Privacy() )->register();
	( new BlogRhythm\Reminders() )->register();
	add_action( 'blog_rhythm_send_reminders', array( 'BlogRhythm\Reminders', 'run' ) );
	// Any post write can change activity, so drop this request's memoized scan.
	add_action( 'clean_post_cache', array( 'BlogRhythm\Activity', 'flush' ), 10, 0 );
}
add_action( 'plugins_loaded', 'blog_rhythm_init' );
